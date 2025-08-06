<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Documentation\{DocumentationIndex, DocumentationIndexSingleton};
use PhpMcp\Server\Attributes\McpTool;
use RuntimeException;

use function array_slice;
use function count;
use function in_array;
use function is_array;
use function is_scalar;
use function sprintf;
use function strlen;

final readonly class DocumentationTools extends AbstractTools
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private ClientInterface $client,
    ) {
    }

    /**
     * Find documentation similar to provided content.
     *
     * @param string      $content              Reference content to find similar documentation
     * @param string|null $sdk                  Limit search to specific SDK
     * @param float       $similarity_threshold Minimum similarity score (0.0-1.0, default: 0.5)
     * @param int         $limit                Maximum number of results (default: 5)
     *
     * @throws RuntimeException If documentation index initialization fails
     *
     * @return string Markdown-formatted related documentation
     */
    #[McpTool(name: 'find_similar_documentation')]
    public function findSimilarDocumentation(
        string $content,
        ?string $sdk = null,
        float $similarity_threshold = 0.5,
        int $limit = 5,
    ): string {
        if ('' === trim($content)) {
            return '❌ Content cannot be empty';
        }

        if (0.0 > $similarity_threshold || 1.0 < $similarity_threshold) {
            return '❌ Similarity threshold must be between 0.0 and 1.0';
        }

        if (1 > $limit || 20 < $limit) {
            return '❌ Limit must be between 1 and 20';
        }

        $validSdks = ['php', 'go', 'python', 'java', 'dotnet', 'js', 'laravel', null];

        if (! in_array($sdk, $validSdks, true)) {
            return '❌ Invalid SDK. Must be one of: php, go, python, java, dotnet, js, laravel';
        }

        $index = DocumentationIndexSingleton::getInstance();

        if (! $index->isInitialized()) {
            $index->initialize();
        }

        // Extract key terms from content for similarity matching
        $keyTerms = $this->extractKeyTerms($content);

        if ([] === $keyTerms) {
            return '❌ Could not extract meaningful terms from the provided content';
        }

        // Search for similar content using key terms
        /** @var array<string, array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>, similarity?: float, content?: string}> $similarChunks */
        $similarChunks = [];

        foreach ($keyTerms as $keyTerm) {
            $chunks = $index->searchChunks($keyTerm, $sdk, $limit * 2);

            foreach ($chunks as $chunk) {
                // Get content from the chunk to calculate similarity
                // We need to get the actual content - the preview is just a snippet
                $chunkContent = $this->getChunkContent($chunk['chunk_id'], $index);
                $similarity = $this->calculateSimilarity($content, $chunkContent);

                if ($similarity >= $similarity_threshold) {
                    // Add similarity and content to the chunk
                    $chunkWithSimilarity = $chunk;
                    $chunkWithSimilarity['similarity'] = $similarity;
                    $chunkWithSimilarity['content'] = $chunkContent;

                    $chunkKey = $chunk['sdk'] . '::' . $chunk['chunk_id'];

                    if (! isset($similarChunks[$chunkKey])) {
                        $similarChunks[$chunkKey] = $chunkWithSimilarity;
                    } elseif (isset($similarChunks[$chunkKey]['similarity'])) {
                        $existingSim = $similarChunks[$chunkKey]['similarity'];

                        if ($existingSim < $similarity) {
                            $similarChunks[$chunkKey] = $chunkWithSimilarity;
                        }
                    }
                }
            }
        }

        // Sort by similarity score
        usort($similarChunks, static function (array $a, array $b): int {
            // Since we know similarity is added above, we can safely access it
            $aSimilarity = isset($a['similarity']) && is_numeric($a['similarity']) ? $a['similarity'] : 0.0;
            $bSimilarity = isset($b['similarity']) && is_numeric($b['similarity']) ? $b['similarity'] : 0.0;

            return $bSimilarity <=> $aSimilarity;
        });

        // Apply limit
        $similarChunks = array_slice($similarChunks, 0, $limit);

        if ([] === $similarChunks) {
            return "## Similar Documentation\n\nNo similar documentation found (threshold: {$similarity_threshold})" .
                   (null !== $sdk ? ' in SDK: ' . $sdk : '') .
                   "\n\nTry:\n- Lowering the similarity threshold\n- Providing more specific content\n- Removing SDK filter for broader results";
        }

        // Build markdown response
        $markdown = "## Similar Documentation\n\n";
        $markdown .= sprintf('**Similarity Threshold:** %s%s', $similarity_threshold, PHP_EOL);

        if (null !== $sdk) {
            $markdown .= sprintf('**SDK Filter:** %s%s', $sdk, PHP_EOL);
        }
        $markdown .= '**Found:** ' . count($similarChunks) . " similar document(s)\n\n";
        $markdown .= "---\n\n";

        foreach ($similarChunks as $chunkIndex => $chunk) {
            $markdown .= $this->formatSimilarResult($chunk, $chunkIndex + 1);
        }

        return $markdown;
    }

    /**
     * Search for code examples in documentation.
     *
     * @param string      $query           Code pattern or concept to find
     * @param string|null $language        Programming language filter (php, go, python, java, csharp, javascript, typescript)
     * @param bool        $include_context Include surrounding explanatory context
     * @param int         $limit           Maximum number of examples to return (default: 5)
     * @param int         $offset          Pagination offset for results (default: 0)
     *
     * @throws RuntimeException If documentation index initialization fails
     *
     * @return string Markdown-formatted code examples with descriptions
     */
    #[McpTool(name: 'search_code_examples')]
    public function searchCodeExamples(
        string $query,
        ?string $language = null,
        bool $include_context = true,
        int $limit = 5,
        int $offset = 0,
    ): string {
        if ('' === trim($query)) {
            return '❌ Search query cannot be empty';
        }

        if (1 > $limit || 20 < $limit) {
            return '❌ Limit must be between 1 and 20';
        }

        if (0 > $offset) {
            return '❌ Offset cannot be negative';
        }

        $validLanguages = ['php', 'go', 'python', 'java', 'csharp', 'javascript', 'typescript', null];

        if (! in_array($language, $validLanguages, true)) {
            return '❌ Invalid language. Must be one of: php, go, python, java, csharp, javascript, typescript';
        }

        $index = DocumentationIndexSingleton::getInstance();

        if (! $index->isInitialized()) {
            $index->initialize();
        }

        // Map language to SDK if applicable
        $sdk = $this->mapLanguageToSdk($language);

        // Search for code-related chunks
        $allChunks = $index->searchChunks($query, $sdk);
        $codeExamples = [];

        foreach ($allChunks as $allChunk) {
            // Get the full content for this chunk
            $chunkContent = $this->getChunkContent($allChunk['chunk_id'], $index);
            $chunkWithContent = $allChunk;
            $chunkWithContent['content'] = $chunkContent;

            $examples = $this->extractCodeFromChunk($chunkWithContent, $language);

            foreach ($examples as $example) {
                $example['chunk'] = $allChunk;
                $codeExamples[] = $example;
            }
        }

        $totalExamples = count($codeExamples);

        if (0 === $totalExamples) {
            return "## Code Examples

No code examples found for: **{$query}**" .
                   (null !== $language ? sprintf(' (language: %s)', $language) : '') .
                   "\n\nTry:\n- Searching for specific method or class names\n- Using OpenFGA terminology (e.g., 'check', 'expand', 'tuples')\n- Removing language filter for broader results";
        }

        // Apply pagination
        $paginatedExamples = array_slice($codeExamples, $offset, $limit);
        $currentPage = (int) floor($offset / $limit) + 1;
        $totalPages = (int) ceil($totalExamples / $limit);

        // Build markdown response
        $markdown = "## Code Examples\n\n";
        $markdown .= "**Search:** `{$query}`\n";

        if (null !== $language) {
            $markdown .= sprintf('**Language:** %s%s', $language, PHP_EOL);
        }
        $markdown .= '**Results:** Showing ' . ($offset + 1) . '-' . min($offset + $limit, $totalExamples) . " of {$totalExamples} examples\n\n";
        $markdown .= "---\n\n";

        foreach ($paginatedExamples as $exampleIndex => $example) {
            $exampleNumber = $offset + $exampleIndex + 1;
            $markdown .= $this->formatCodeExample($example, $exampleNumber, $include_context);
        }

        // Add pagination info
        if (1 < $totalPages) {
            $markdown .= "\n---\n\n### Pagination\n\n";

            if (1 < $currentPage) {
                $prevOffset = max(0, $offset - $limit);
                $markdown .= sprintf('- **Previous page:** Use offset=%d%s', $prevOffset, PHP_EOL);
            }

            if ($currentPage < $totalPages) {
                $nextOffset = $offset + $limit;
                $markdown .= sprintf('- **Next page:** Use offset=%d%s', $nextOffset, PHP_EOL);
            }
        }

        return $markdown;
    }

    /**
     * Advanced documentation search with filtering and pagination.
     *
     * @param string      $query       Search query to find in documentation
     * @param string|null $sdk         Filter by specific SDK (php, go, python, java, dotnet, js, laravel)
     * @param string      $search_type Type of search: content (default), class, method, or section
     * @param int         $limit       Maximum number of results to return (default: 10)
     * @param int         $offset      Pagination offset for results (default: 0)
     *
     * @throws RuntimeException If documentation index initialization fails
     *
     * @return string Markdown-formatted search results with pagination metadata
     */
    #[McpTool(name: 'search_documentation')]
    public function searchDocumentation(
        string $query,
        ?string $sdk = null,
        string $search_type = 'content',
        int $limit = 10,
        int $offset = 0,
    ): string {
        if ('' === trim($query)) {
            return '❌ Search query cannot be empty';
        }

        if (1 > $limit || 50 < $limit) {
            return '❌ Limit must be between 1 and 50';
        }

        if (0 > $offset) {
            return '❌ Offset cannot be negative';
        }

        $validSearchTypes = ['content', 'class', 'method', 'section'];

        if (! in_array($search_type, $validSearchTypes, true)) {
            return '❌ Invalid search_type. Must be one of: ' . implode(', ', $validSearchTypes);
        }

        $validSdks = ['php', 'go', 'python', 'java', 'dotnet', 'js', 'laravel', null];

        if (! in_array($sdk, $validSdks, true)) {
            return '❌ Invalid SDK. Must be one of: php, go, python, java, dotnet, js, laravel';
        }

        $index = DocumentationIndexSingleton::getInstance();

        if (! $index->isInitialized()) {
            $index->initialize();
        }

        // Get all results first for total count
        $allResults = $this->performSearch($index, $query, $sdk, $search_type);
        $totalResults = count($allResults);

        if (0 === $totalResults) {
            $markdown = '## Documentation Search Results

';
            $markdown .= "**Query:** `{$query}`
";

            if (null !== $sdk) {
                $markdown .= sprintf('**SDK Filter:** %s%s', $sdk, PHP_EOL);
            }
            $markdown .= sprintf('**Search Type:** %s%s', $search_type, PHP_EOL);
            $markdown .= "\nNo results found for query: **{$query}**";

            if (null !== $sdk) {
                $markdown .= sprintf(' (filtered by SDK: %s)', $sdk);
            }

            return $markdown . "\n\nTry:\n- Using different keywords\n- Checking spelling\n- Using broader search terms";
        }

        // Apply pagination
        $paginatedResults = array_slice($allResults, $offset, $limit);
        $currentPage = (int) floor($offset / $limit) + 1;
        $totalPages = (int) ceil($totalResults / $limit);

        // Build markdown response
        $markdown = "## Documentation Search Results\n\n";
        $markdown .= "**Query:** `{$query}`\n";

        if (null !== $sdk) {
            $markdown .= sprintf('**SDK Filter:** %s%s', $sdk, PHP_EOL);
        }
        $markdown .= sprintf('**Search Type:** %s%s', $search_type, PHP_EOL);
        $markdown .= '**Results:** Showing ' . ($offset + 1) . '-' . min($offset + $limit, $totalResults) . " of {$totalResults} total results\n";
        $markdown .= "**Page:** {$currentPage} of {$totalPages}\n\n";
        $markdown .= "---\n\n";

        foreach ($paginatedResults as $resultIndex => $result) {
            $resultNumber = $offset + (int) $resultIndex + 1;
            $markdown .= $this->formatSearchResult($result, $resultNumber);
        }

        // Add pagination info
        if (1 < $totalPages) {
            $markdown .= "\n---\n\n### Pagination\n\n";

            if (1 < $currentPage) {
                $prevOffset = max(0, $offset - $limit);
                $markdown .= sprintf('- **Previous page:** Use offset=%d%s', $prevOffset, PHP_EOL);
            }

            if ($currentPage < $totalPages) {
                $nextOffset = $offset + $limit;
                $markdown .= sprintf('- **Next page:** Use offset=%d%s', $nextOffset, PHP_EOL);
            }
        }

        return $markdown;
    }

    /**
     * Calculate similarity between two pieces of content.
     *
     * @param  string $content1
     * @param  string $content2
     * @return float  Similarity score between 0 and 1
     */
    private function calculateSimilarity(string $content1, string $content2): float
    {
        if ('' === $content1 || '' === $content2) {
            return 0.0;
        }

        // Extract terms from both contents
        $terms1 = $this->extractKeyTerms($content1);
        $terms2 = $this->extractKeyTerms($content2);

        if ([] === $terms1 || [] === $terms2) {
            return 0.0;
        }

        // Calculate Jaccard similarity
        $intersection = count(array_intersect($terms1, $terms2));
        $union = count(array_unique(array_merge($terms1, $terms2)));

        // Union will always be at least 1 since we checked both term arrays are non-empty
        $jaccard = (float) ($intersection / $union);

        // Also check for exact phrase matches for higher similarity
        $phrases = [
            'authorization model',
            'permission check',
            'tuple creation',
            'relationship tuples',
            'access control',
            'openfga',
        ];

        $phraseBonus = 0.0;

        foreach ($phrases as $phrase) {
            if (false !== stripos($content1, $phrase) && false !== stripos($content2, $phrase)) {
                $phraseBonus += 0.1;
            }
        }

        // Combine scores (cap at 1.0)
        return min(1.0, $jaccard + $phraseBonus);
    }

    /**
     * Extract code examples from a chunk.
     *
     * @param  array<string, mixed>        $chunk
     * @param  string|null                 $language
     * @return array<array<string, mixed>>
     */
    private function extractCodeFromChunk(array $chunk, ?string $language): array
    {
        $content = isset($chunk['content']) && is_scalar($chunk['content']) ? (string) $chunk['content'] : '';
        $examples = [];

        // Match code blocks with optional language specification
        $pattern = '/```(\w+)?\n(.*?)\n```/s';
        $result = preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if (false !== $result && 0 < $result) {
            foreach ($matches as $match) {
                // When preg_match_all succeeds with PREG_SET_ORDER, capture groups exist
                // Optional groups will be empty strings if they don't match
                /** @var array{0: string, 1: string, 2: string} $match */
                $codeLang = $match[1];
                $codeContent = $match[2];

                // Filter by language if specified
                if (null !== $language) {
                    $langMatch = false;

                    switch ($language) {
                        case 'php':
                            $langMatch = 'php' === $codeLang || false !== stripos($codeContent, '<?php');

                            break;

                        case 'go':
                            $langMatch = 'go' === $codeLang || false !== stripos($codeContent, 'func ');

                            break;

                        case 'python':
                            $langMatch = 'python' === $codeLang || 'py' === $codeLang || false !== stripos($codeContent, 'def ');

                            break;

                        case 'java':
                            $langMatch = 'java' === $codeLang || false !== stripos($codeContent, 'public class');

                            break;

                        case 'csharp':
                            $langMatch = 'csharp' === $codeLang || 'cs' === $codeLang || false !== stripos($codeContent, 'using ');

                            break;

                        case 'javascript':
                        case 'typescript':
                            $langMatch = in_array($codeLang, ['javascript', 'js', 'typescript', 'ts'], true);

                            break;
                    }

                    if (! $langMatch) {
                        continue;
                    }
                }

                $examples[] = [
                    'language' => '' !== $codeLang ? $codeLang : 'unknown',
                    'code' => $codeContent,
                    'context' => $this->extractContext($content, $match[0]),
                ];
            }
        }

        return $examples;
    }

    /**
     * Extract context around code.
     *
     * @param string $content
     * @param string $codeBlock
     */
    private function extractContext(string $content, string $codeBlock): string
    {
        $position = strpos($content, $codeBlock);

        if (false === $position) {
            return '';
        }

        // Get text before the code block (up to 200 chars)
        $beforeStart = max(0, $position - 200);
        $beforeText = substr($content, $beforeStart, $position - $beforeStart);

        // Get text after the code block (up to 200 chars)
        $afterStart = $position + strlen($codeBlock);
        $afterText = substr($content, $afterStart, 200);

        // Clean up and combine
        $beforeText = trim(preg_replace('/\s+/', ' ', $beforeText) ?? '');
        $afterText = trim(preg_replace('/\s+/', ' ', $afterText) ?? '');

        $context = '';

        if ('' !== $beforeText) {
            $context .= '...' . $beforeText;
        }
        $context .= ' [CODE] ';

        if ('' !== $afterText) {
            $context .= $afterText . '...';
        }

        return trim($context);
    }

    /**
     * Extract key terms from content for similarity matching.
     *
     * @param  string        $content
     * @return array<string>
     */
    private function extractKeyTerms(string $content): array
    {
        // Remove code blocks and special characters
        $cleanContent = preg_replace('/```[\s\S]*?```/', '', $content);
        $cleanContent ??= '';
        $cleanContent = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $cleanContent);
        $cleanContent ??= '';

        // Extract words
        $words = preg_split('/\s+/', strtolower($cleanContent));
        $words = false !== $words ? $words : [];

        // Filter out common words and short words
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'in', 'of', 'to', 'for', 'with', 'from', 'up', 'out', 'if', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once'];

        $termCounts = [];

        foreach ($words as $word) {
            if (2 < strlen($word) && ! in_array($word, $stopWords, true)) {
                if (! isset($termCounts[$word])) {
                    $termCounts[$word] = 0;
                }
                ++$termCounts[$word];
            }
        }

        // Sort by frequency and take top terms
        arsort($termCounts);
        $keyTerms = array_keys(array_slice($termCounts, 0, 10));

        // Add OpenFGA-specific terms if present
        $openfgaTerms = ['openfga', 'authorization', 'permission', 'tuple', 'relation', 'check', 'expand', 'store', 'model', 'user', 'object'];

        foreach ($openfgaTerms as $openfgaTerm) {
            if (false !== stripos($content, $openfgaTerm) && ! in_array($openfgaTerm, $keyTerms, true)) {
                $keyTerms[] = $openfgaTerm;
            }
        }

        return array_slice($keyTerms, 0, 15);
    }

    /**
     * Format a code example as markdown.
     *
     * @param array<string, mixed> $example
     * @param int                  $number
     * @param bool                 $includeContext
     */
    private function formatCodeExample(array $example, int $number, bool $includeContext): string
    {
        $markdown = "### Example {$number}\n\n";

        $chunk = isset($example['chunk']) && is_array($example['chunk']) ? $example['chunk'] : [];

        // Add metadata
        if (isset($chunk['sdk']) && '' !== $chunk['sdk']) {
            $chunkSdk = is_scalar($chunk['sdk']) ? (string) $chunk['sdk'] : '';
            $markdown .= "**SDK:** `{$chunkSdk}`  \n";
        }

        // Access class and method from metadata
        $metadata = isset($chunk['metadata']) && is_array($chunk['metadata']) ? $chunk['metadata'] : [];

        if (isset($metadata['class']) && '' !== $metadata['class']) {
            $chunkClass = is_scalar($metadata['class']) ? (string) $metadata['class'] : '';
            $markdown .= sprintf('**Class:** `%s`', $chunkClass);

            if (isset($metadata['method']) && '' !== $metadata['method']) {
                $chunkMethod = is_scalar($metadata['method']) ? (string) $metadata['method'] : '';
                $markdown .= sprintf(' **Method:** `%s`', $chunkMethod);
            }
            $markdown .= "  \n";
        }

        if (isset($example['language']) && '' !== $example['language'] && 'unknown' !== $example['language']) {
            $exampleLang = is_scalar($example['language']) ? (string) $example['language'] : '';
            $markdown .= "**Language:** `{$exampleLang}`  \n";
        }

        $markdown .= "\n";

        // Add context if requested
        if ($includeContext && isset($example['context']) && '' !== $example['context']) {
            $context = is_scalar($example['context']) ? (string) $example['context'] : '';
            $markdown .= "**Context:**\n> " . str_replace('[CODE]', '*(see code below)*', $context) . "\n\n";
        }

        // Add code
        $lang = isset($example['language']) && is_scalar($example['language']) ? (string) $example['language'] : '';
        $code = isset($example['code']) && is_scalar($example['code']) ? (string) $example['code'] : '';
        $markdown .= "```{$lang}\n{$code}\n```\n";

        return $markdown . "\n---\n\n";
    }

    /**
     * Format a search result as markdown.
     *
     * @param array<string, mixed> $result
     * @param int                  $number
     */
    private function formatSearchResult(array $result, int $number): string
    {
        $markdown = sprintf('### %d. ', $number);

        // Build title based on available metadata
        $title = '';
        $metadata = isset($result['metadata']) && is_array($result['metadata']) ? $result['metadata'] : [];

        if (isset($metadata['class']) && '' !== $metadata['class']) {
            $title .= is_scalar($metadata['class']) ? (string) $metadata['class'] : '';

            if (isset($metadata['method']) && '' !== $metadata['method']) {
                $title .= '::' . (is_scalar($metadata['method']) ? (string) $metadata['method'] : '');
            }
        } elseif (isset($metadata['section']) && '' !== $metadata['section']) {
            $title .= is_scalar($metadata['section']) ? (string) $metadata['section'] : '';
        } else {
            $title .= 'Documentation Chunk';
        }

        $markdown .= $title . "\n\n";

        // Add metadata
        if (isset($result['sdk']) && '' !== $result['sdk']) {
            $sdkName = is_scalar($result['sdk']) ? (string) $result['sdk'] : '';
            $markdown .= "**SDK:** `{$sdkName}`  \n";
        }

        if (isset($result['source']) && '' !== $result['source']) {
            $sourceName = is_scalar($result['source']) ? (string) $result['source'] : '';
            $markdown .= "**Source:** `{$sourceName}`  \n";
        }

        if (isset($result['score']) && 0.0 !== $result['score']) {
            $scoreValue = is_numeric($result['score']) ? (float) $result['score'] : 0.0;
            $markdown .= '**Relevance:** ' . (string) round($scoreValue * 100.0) . "%  \n";
        }

        $markdown .= "\n";

        // Add preview
        if (isset($result['preview']) && '' !== $result['preview']) {
            $preview = is_scalar($result['preview']) ? (string) $result['preview'] : '';

            // Limit preview length
            if (500 < strlen($preview)) {
                $preview = substr($preview, 0, 497) . '...';
            }
            $markdown .= "**Preview:**\n```\n{$preview}\n```\n";
        }

        // Add navigation
        if (isset($result['chunk_id']) && '' !== $result['chunk_id']) {
            $sdk = isset($result['sdk']) && is_scalar($result['sdk']) ? (string) $result['sdk'] : 'unknown';
            $id = isset($result['chunk_id']) && is_scalar($result['chunk_id']) ? (string) $result['chunk_id'] : 'unknown';
            $markdown .= "\n**Reference:** `{$sdk}::{$id}`\n";
        }

        return $markdown . "\n---\n\n";
    }

    /**
     * Format a similar documentation result as markdown.
     *
     * @param array<string, mixed> $chunk
     * @param int                  $number
     */
    private function formatSimilarResult(array $chunk, int $number): string
    {
        $markdown = sprintf('### %d. ', $number);

        // Build title
        $title = '';
        $metadata = isset($chunk['metadata']) && is_array($chunk['metadata']) ? $chunk['metadata'] : [];

        if (isset($metadata['class']) && '' !== $metadata['class']) {
            $title .= is_scalar($metadata['class']) ? (string) $metadata['class'] : '';

            if (isset($metadata['method']) && '' !== $metadata['method']) {
                $title .= '::' . (is_scalar($metadata['method']) ? (string) $metadata['method'] : '');
            }
        } elseif (isset($metadata['section']) && '' !== $metadata['section']) {
            $title .= is_scalar($metadata['section']) ? (string) $metadata['section'] : '';
        } else {
            $title .= 'Related Documentation';
        }

        $markdown .= $title . "\n\n";

        // Add metadata
        if (isset($chunk['sdk']) && '' !== $chunk['sdk']) {
            $chunkSdkValue = is_scalar($chunk['sdk']) ? (string) $chunk['sdk'] : '';
            $markdown .= "**SDK:** `{$chunkSdkValue}`  \n";
        }

        if (isset($chunk['similarity']) && 0.0 !== $chunk['similarity']) {
            $simScore = is_numeric($chunk['similarity']) ? (float) $chunk['similarity'] : 0.0;
            $markdown .= '**Similarity Score:** ' . (string) round($simScore * 100.0) . "%  \n";
        }

        if (isset($chunk['source']) && '' !== $chunk['source']) {
            $sourceValue = is_scalar($chunk['source']) ? (string) $chunk['source'] : '';
            $markdown .= "**Source:** `{$sourceValue}`  \n";
        }

        $markdown .= "\n";

        // Add content preview
        if (isset($chunk['content']) && '' !== $chunk['content']) {
            $preview = is_scalar($chunk['content']) ? (string) $chunk['content'] : '';

            // Limit preview length
            if (800 < strlen($preview)) {
                $preview = substr($preview, 0, 797) . '...';
            }
            $markdown .= "**Content:**\n\n" . $preview . "\n";
        }

        // Add reference
        if (isset($chunk['chunk_id']) && '' !== $chunk['chunk_id']) {
            $sdk = isset($chunk['sdk']) && is_scalar($chunk['sdk']) ? (string) $chunk['sdk'] : 'unknown';
            $id = isset($chunk['chunk_id']) && is_scalar($chunk['chunk_id']) ? (string) $chunk['chunk_id'] : 'unknown';
            $markdown .= "\n**Reference:** `{$sdk}::{$id}`\n";
        }

        return $markdown . "\n---\n\n";
    }

    /**
     * Get the full content of a chunk by its ID.
     *
     * @param string             $chunkId
     * @param DocumentationIndex $index
     */
    private function getChunkContent(string $chunkId, DocumentationIndex $index): string
    {
        // For now, we'll use the chunk ID to get content
        // In a real implementation, we'd fetch this from the index
        $chunk = $index->getChunkById($chunkId);

        if (null === $chunk) {
            return '';
        }

        return $chunk['content'] ?? '';
    }

    /**
     * Map programming language to SDK.
     *
     * @param string|null $language
     */
    private function mapLanguageToSdk(?string $language): ?string
    {
        if (null === $language) {
            return null;
        }

        $mapping = [
            'php' => 'php',
            'go' => 'go',
            'python' => 'python',
            'java' => 'java',
            'csharp' => 'dotnet',
            'javascript' => 'js',
            'typescript' => 'js',
        ];

        return $mapping[$language] ?? null;
    }

    /**
     * Perform search based on search type.
     *
     * @param DocumentationIndex $index
     * @param string             $query
     * @param string|null        $sdk
     * @param string             $searchType
     *
     * @throws RuntimeException If search fails
     *
     * @return array<array<string, mixed>>
     */
    private function performSearch(DocumentationIndex $index, string $query, ?string $sdk, string $searchType): array
    {
        return match ($searchType) {
            'class' => $this->searchForClasses($index, $query, $sdk),
            'method' => $this->searchForMethods($index, $query, $sdk),
            'section' => $this->searchForSections($index, $query, $sdk),
            default => $index->searchChunks($query, $sdk),
        };
    }

    /**
     * Search specifically for classes.
     *
     * @param DocumentationIndex $index
     * @param string             $query
     * @param string|null        $sdk
     *
     * @throws RuntimeException If search fails
     *
     * @return array<array<string, mixed>>
     */
    private function searchForClasses(DocumentationIndex $index, string $query, ?string $sdk): array
    {
        $allChunks = $index->searchChunks($query, $sdk);
        $classResults = [];

        foreach ($allChunks as $allChunk) {
            $metadata = $allChunk['metadata'];

            if (isset($metadata['class']) && '' !== $metadata['class'] && is_scalar($metadata['class']) && false !== stripos((string) $metadata['class'], $query)) {
                $classResults[] = $allChunk;
            }
        }

        return $classResults;
    }

    /**
     * Search specifically for methods.
     *
     * @param DocumentationIndex $index
     * @param string             $query
     * @param string|null        $sdk
     *
     * @throws RuntimeException If search fails
     *
     * @return array<array<string, mixed>>
     */
    private function searchForMethods(DocumentationIndex $index, string $query, ?string $sdk): array
    {
        $allChunks = $index->searchChunks($query, $sdk);
        $methodResults = [];

        foreach ($allChunks as $allChunk) {
            $metadata = $allChunk['metadata'];

            if (isset($metadata['method']) && '' !== $metadata['method'] && is_scalar($metadata['method']) && false !== stripos((string) $metadata['method'], $query)) {
                $methodResults[] = $allChunk;
            }
        }

        return $methodResults;
    }

    /**
     * Search specifically for sections.
     *
     * @param DocumentationIndex $index
     * @param string             $query
     * @param string|null        $sdk
     *
     * @throws RuntimeException If search fails
     *
     * @return array<array<string, mixed>>
     */
    private function searchForSections(DocumentationIndex $index, string $query, ?string $sdk): array
    {
        $allChunks = $index->searchChunks($query, $sdk);
        $sectionResults = [];

        foreach ($allChunks as $allChunk) {
            $metadata = $allChunk['metadata'];

            if (isset($metadata['section']) && '' !== $metadata['section'] && is_scalar($metadata['section']) && false !== stripos((string) $metadata['section'], $query)) {
                $sectionResults[] = $allChunk;
            }
        }

        return $sectionResults;
    }
}
