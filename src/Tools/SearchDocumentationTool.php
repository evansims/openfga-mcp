<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use Exception;
use OpenFGA\MCP\Documentation\DocumentationIndex;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use RuntimeException;

use function array_slice;
use function count;
use function in_array;
use function is_string;
use function sprintf;
use function strlen;

final readonly class SearchDocumentationTool extends AbstractTools
{
    private DocumentationIndex $index;

    public function __construct()
    {
        $this->index = new DocumentationIndex;
    }

    /**
     * @return array<int|string, mixed>
     */
    #[McpTool(
        name: 'find_similar_documentation',
        description: 'Find documentation sections similar to a given text or concept',
    )]
    public function findSimilarDocumentation(
        #[Schema(description: 'Text or concept to find similar content for')]
        string $reference_text,
        #[Schema(description: 'Filter by SDK (optional)')]
        ?string $sdk = null,
        #[Schema(description: 'Maximum number of results (default: 5, max: 20)', minimum: 1, maximum: 20)]
        int $limit = 5,
        #[Schema(description: 'Minimum similarity score (default: 0.1)', minimum: 0.0, maximum: 1.0)]
        float $min_score = 0.1,
    ): array {
        $referenceText = $reference_text;
        $minScore = $min_score;

        if ('' === $referenceText) {
            return [
                '❌ Reference text is required',
                'usage' => [
                    'reference_text' => 'Text or concept to find similar content for (required)',
                    'sdk' => 'Filter by SDK (optional)',
                    'limit' => 'Number of results (default: 5, max: 20)',
                    'min_score' => 'Minimum similarity score (default: 0.1)',
                ],
                'examples' => [
                    'Find similar' => '{"reference_text": "This shows how to create a new store"}',
                    'Concept search' => '{"reference_text": "authorization model with relations"}',
                ],
            ];
        }

        try {
            $this->index->initialize();

            $keywords = $this->extractKeywords($referenceText);
            $searchQuery = implode(' ', $keywords);

            $results = $this->index->searchChunks($searchQuery, $sdk, min($limit * 2, 40));
            $similarResults = [];

            foreach ($results as $result) {
                $similarity = $this->calculateSimilarity($referenceText, $result['preview']);

                if ($similarity >= $minScore) {
                    $similarResults[] = [
                        'chunk_id' => $result['chunk_id'],
                        'sdk' => $result['sdk'],
                        'similarity_score' => $similarity,
                        'search_score' => $result['score'],
                        'combined_score' => ($similarity * 0.6) + ($result['score'] * 0.4),
                        'preview' => $result['preview'],
                        'metadata' => $result['metadata'],
                        'uri' => sprintf('openfga://docs/%s/chunk/%s', $result['sdk'], $result['chunk_id']),
                    ];
                }
            }

            usort(
                $similarResults,
                /**
                 * @param array{chunk_id: string, sdk: string, similarity_score: float, search_score: float, combined_score: float, preview: string, metadata: mixed, uri: string} $a
                 * @param array{chunk_id: string, sdk: string, similarity_score: float, search_score: float, combined_score: float, preview: string, metadata: mixed, uri: string} $b
                 */
                static fn (array $a, array $b): int => $b['combined_score'] <=> $a['combined_score'],
            );
            $similarResults = array_slice($similarResults, 0, $limit);

            if ([] === $similarResults) {
                return [
                    '❌ No similar documentation found',
                    'reference_text' => substr($referenceText, 0, 100) . '...',
                    'min_score' => $minScore,
                    'suggestion' => 'Try lowering min_score or using different reference text',
                ];
            }

            return [
                '✅ Similar Documentation Found',
                'reference_text' => substr($referenceText, 0, 100) . '...',
                'total_results' => count($similarResults),
                'results' => $similarResults,
            ];
        } catch (Exception $exception) {
            return [
                '❌ Similarity search failed',
                'reference_text' => substr($referenceText, 0, 100) . '...',
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    #[McpTool(
        name: 'search_code_examples',
        description: 'Find code examples in documentation with language filtering',
    )]
    public function searchCodeExamples(
        #[Schema(description: 'Search terms for code examples')]
        string $query,
        #[Schema(description: 'Programming language: php, go, python, java, csharp, javascript, typescript')]
        ?string $language = null,
        #[Schema(description: 'Filter by SDK (optional)')]
        ?string $sdk = null,
        #[Schema(description: 'Maximum number of results (default: 10, max: 20)', minimum: 1, maximum: 20)]
        int $limit = 10,
    ): array {
        if ('' === $query) {
            return [
                '❌ Search query is required',
                'usage' => [
                    'query' => 'Search terms for code examples (required)',
                    'language' => 'Programming language: php, go, python, java, csharp, javascript, typescript',
                    'sdk' => 'Filter by SDK (optional)',
                    'limit' => 'Number of results (default: 10, max: 20)',
                ],
                'examples' => [
                    'PHP examples' => '{"query": "createStore", "language": "php"}',
                    'Authorization examples' => '{"query": "check permission", "sdk": "go"}',
                    'Authentication setup' => '{"query": "client credentials", "language": "python"}',
                ],
            ];
        }

        try {
            $this->index->initialize();

            $results = $this->index->searchChunks($query, $sdk, min($limit, 20));
            $codeExamples = [];

            foreach ($results as $result) {
                $chunk = $this->index->getChunk($result['chunk_id']);

                if (null === $chunk) {
                    continue;
                }

                $examples = $this->extractCodeExamples($chunk['content'], $language);

                foreach ($examples as $example) {
                    $codeExamples[] = [
                        'sdk' => $result['sdk'],
                        'chunk_id' => $result['chunk_id'],
                        'language' => $example['language'],
                        'code' => $example['code'],
                        'description' => $example['description'],
                        'relevance_score' => $result['score'],
                        'context' => [
                            'section' => $result['metadata']['section'] ?? null,
                            'class' => $result['metadata']['class'] ?? null,
                            'method' => $result['metadata']['method'] ?? null,
                        ],
                    ];
                }
            }

            if ([] === $codeExamples) {
                return [
                    '❌ No code examples found',
                    'query' => $query,
                    'language_filter' => $language,
                    'sdk_filter' => $sdk,
                    'suggestions' => [
                        'Try broader search terms',
                        'Remove language or SDK filters',
                        'Search for method names or class names instead',
                    ],
                ];
            }

            usort(
                $codeExamples,
                /**
                 * @param array{sdk: string, chunk_id: string, language: string, code: string, description: string, relevance_score: float, context: array<string, mixed>} $a
                 * @param array{sdk: string, chunk_id: string, language: string, code: string, description: string, relevance_score: float, context: array<string, mixed>} $b
                 */
                static fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score'],
            );
            $codeExamples = array_slice($codeExamples, 0, $limit);

            return [
                '✅ Code Examples Found',
                'query' => $query,
                'language_filter' => $language,
                'sdk_filter' => $sdk,
                'total_examples' => count($codeExamples),
                'examples' => $codeExamples,
            ];
        } catch (Exception $exception) {
            return [
                '❌ Code example search failed',
                'query' => $query,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    #[McpTool(
        name: 'search_documentation',
        description: 'Search for content across OpenFGA SDK documentation with advanced filtering options',
    )]
    public function searchDocumentation(
        #[Schema(description: 'Search terms to look for in documentation')]
        string $query,
        #[Schema(description: 'Filter by SDK: php, go, python, java, dotnet, js, laravel')]
        ?string $sdk = null,
        #[Schema(description: 'Maximum number of results (default: 10, max: 50)', minimum: 1, maximum: 50)]
        int $limit = 10,
        #[Schema(description: 'Search type: content, class, method, section', enum: ['content', 'class', 'method', 'section'])]
        string $search_type = 'content',
        #[Schema(description: 'Include full chunk content in results')]
        bool $include_content = false,
    ): array {
        $searchType = $search_type;
        $includeContent = $include_content;

        if ('' === $query) {
            return [
                '❌ Search query is required',
                'usage' => [
                    'query' => 'Search terms (required)',
                    'sdk' => 'Filter by SDK (optional): php, go, python, java, dotnet, js, laravel',
                    'limit' => 'Number of results (default: 10, max: 50)',
                    'search_type' => 'Search type: content, class, method, section (default: content)',
                    'include_content' => 'Include full chunk content in results (default: false)',
                ],
                'examples' => [
                    'Basic search' => '{"query": "authentication"}',
                    'SDK-specific' => '{"query": "check permission", "sdk": "php"}',
                    'Method search' => '{"query": "createStore", "search_type": "method"}',
                    'With content' => '{"query": "batch check", "include_content": true}',
                ],
            ];
        }

        if (50 < $limit) {
            $limit = 50;
        }

        try {
            $this->index->initialize();

            $results = match ($searchType) {
                'class' => $this->searchClasses($query, $sdk, $limit),
                'method' => $this->searchMethods($query, $sdk, $limit),
                'section' => $this->searchSections($query, $sdk, $limit),
                default => $this->searchContent($query, $sdk, $limit, $includeContent),
            };

            if ([] === $results) {
                $availableSDKs = $this->index->getSdkList();

                return [
                    '❌ No results found',
                    'query' => $query,
                    'search_type' => $searchType,
                    'sdk_filter' => $sdk,
                    'results' => [],
                    'suggestions' => [
                        'Try broader search terms',
                        'Check spelling and try synonyms',
                        'Remove SDK filter to search all documentation',
                        'Try different search_type: content, class, method, section',
                    ],
                    'available_sdks' => $availableSDKs,
                ];
            }

            return [
                '✅ Search Results',
                'query' => $query,
                'search_type' => $searchType,
                'sdk_filter' => $sdk,
                'total_results' => count($results),
                'results' => $results,
                'next_steps' => [
                    'Use openfga://docs/{sdk}/chunk/{chunk_id} to get full content',
                    'Use openfga://docs/{sdk}/class/{class} for complete class documentation',
                    'Use search_code_examples tool to find specific code snippets',
                ],
            ];
        } catch (Exception $exception) {
            return [
                '❌ Search failed',
                'query' => $query,
                'error' => $exception->getMessage(),
                'troubleshooting' => [
                    'Ensure documentation files exist in docs/ directory',
                    'Try reinitializing the documentation index',
                    'Check server logs for detailed error information',
                ],
            ];
        }
    }

    private function calculateSimilarity(string $text1, string $text2): float
    {
        $words1Split = preg_split('/\W+/', strtolower($text1), -1, PREG_SPLIT_NO_EMPTY);
        $words2Split = preg_split('/\W+/', strtolower($text2), -1, PREG_SPLIT_NO_EMPTY);
        $words1 = array_unique(false !== $words1Split ? $words1Split : []);
        $words2 = array_unique(false !== $words2Split ? $words2Split : []);

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        if ([] === $union) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    /**
     * @param  string                                                            $content
     * @param  ?string                                                           $languageFilter
     * @return array<array{language: string, code: string, description: string}>
     */
    private function extractCodeExamples(string $content, ?string $languageFilter): array
    {
        $examples = [];
        $lines = explode("\n", $content);
        $inCodeBlock = false;
        $currentCode = [];
        $codeLanguage = null;
        $precedingText = '';
        $counter = count($lines);

        for ($i = 0; $i < $counter; ++$i) {
            $line = $lines[$i];

            $codeBlockMatch = preg_match('/^```(\w*)$/', $line, $matches);

            if (false !== $codeBlockMatch && 1 === $codeBlockMatch) {
                if ($inCodeBlock) {
                    $language = $codeLanguage ?? 'plaintext';

                    if (null === $languageFilter || $this->languageMatches($language, $languageFilter)) {
                        $examples[] = [
                            'language' => $language,
                            'code' => implode("\n", $currentCode),
                            'description' => $this->extractDescription($precedingText),
                        ];
                    }

                    $currentCode = [];
                    $inCodeBlock = false;
                    $codeLanguage = null;
                    $precedingText = '';
                } else {
                    $inCodeBlock = true;
                    // Capture group can be empty for code blocks without language specification
                    $codeLanguage = (isset($matches[1]) && '' !== $matches[1]) ? $matches[1] : 'plaintext';
                    $precedingText = $this->getPrecedingText($lines, $i, 3);
                }
            } elseif ($inCodeBlock) {
                $currentCode[] = $line;
            }
        }

        return $examples;
    }

    private function extractDescription(string $text): string
    {
        $text = trim($text);

        $matchResult = preg_match('/(?:Example|Usage|Sample|Code):\s*(.+)$/i', $text, $matches);

        if (1 === $matchResult && isset($matches[1])) {
            return trim($matches[1]);
        }

        $sentences = preg_split('/[.!?]+/', $text);
        $sentencesArray = false !== $sentences ? $sentences : [$text];

        return trim($sentencesArray[count($sentencesArray) - 1] ?? $text);
    }

    /**
     * @param  string             $text
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but', 'in', 'with', 'to', 'for', 'of', 'as', 'by'];
        $words = preg_split('/\W+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordsArray = false !== $words ? $words : [];
        $keywords = array_filter($wordsArray, static fn (string $word): bool => 2 < strlen($word) && ! in_array($word, $stopWords, true));

        return array_unique(array_slice($keywords, 0, 10));
    }

    /**
     * @param array<int, string> $lines
     * @param int                $currentIndex
     * @param int                $lookback
     */
    private function getPrecedingText(array $lines, int $currentIndex, int $lookback = 3): string
    {
        $start = max(0, $currentIndex - $lookback);
        $precedingLines = array_slice($lines, $start, $currentIndex - $start);

        return implode(' ', array_filter($precedingLines, static fn (string $line): bool => '' !== trim($line)));
    }

    private function languageMatches(string $detected, string $filter): bool
    {
        $aliases = [
            'php' => ['php'],
            'go' => ['go', 'golang'],
            'python' => ['python', 'py'],
            'java' => ['java'],
            'csharp' => ['csharp', 'cs', 'c#'],
            'javascript' => ['javascript', 'js'],
            'typescript' => ['typescript', 'ts'],
            'bash' => ['bash', 'shell', 'sh'],
        ];

        $detectedLower = strtolower($detected);
        $filterLower = strtolower($filter);

        foreach ($aliases as $alias) {
            if (in_array($detectedLower, $alias, true) && in_array($filterLower, $alias, true)) {
                return true;
            }
        }

        return $detectedLower === $filterLower;
    }

    /**
     * @param string  $query
     * @param ?string $sdk
     * @param int     $limit
     *
     * @throws RuntimeException
     *
     * @return array<array<mixed>>
     */
    private function searchClasses(string $query, ?string $sdk, int $limit): array
    {
        $results = $this->index->searchChunks($query, $sdk, $limit * 2);
        $classResults = [];

        foreach ($results as $result) {
            /** @var mixed $class */
            $class = $result['metadata']['class'] ?? null;

            if (null !== $class && is_string($class) && '' !== $class) {
                $sdk = $result['sdk'];
                $classResults[] = [
                    'class_name' => $class,
                    'sdk' => $sdk,
                    'relevance_score' => $result['score'],
                    'section' => $result['metadata']['section'] ?? null,
                    'preview' => $result['preview'],
                    'uri' => sprintf('openfga://docs/%s/class/%s', $sdk, $class),
                ];
            }
        }

        $uniqueClasses = [];

        foreach ($classResults as $classResult) {
            $key = $classResult['sdk'] . '::' . $classResult['class_name'];

            if (! isset($uniqueClasses[$key]) || $uniqueClasses[$key]['relevance_score'] < $classResult['relevance_score']) {
                $uniqueClasses[$key] = $classResult;
            }
        }

        usort(
            $uniqueClasses,
            /**
             * @param array{class_name: string, sdk: string, relevance_score: float, section: mixed, preview: string, uri: string} $a
             * @param array{class_name: string, sdk: string, relevance_score: float, section: mixed, preview: string, uri: string} $b
             */
            static fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score'],
        );

        return array_slice($uniqueClasses, 0, $limit);
    }

    /**
     * @param string  $query
     * @param ?string $sdk
     * @param int     $limit
     * @param bool    $includeContent
     *
     * @throws RuntimeException
     *
     * @return array<array<mixed>>
     */
    private function searchContent(string $query, ?string $sdk, int $limit, bool $includeContent): array
    {
        $results = $this->index->searchChunks($query, $sdk, $limit);

        return array_map(function (array $result) use ($includeContent): array {
            $chunkId = $result['chunk_id'];
            $sdk = $result['sdk'];
            $output = [
                'chunk_id' => $chunkId,
                'sdk' => $sdk,
                'relevance_score' => $result['score'],
                'preview' => $result['preview'],
                'metadata' => $result['metadata'],
                'uri' => sprintf('openfga://docs/%s/chunk/%s', $sdk, $chunkId),
            ];

            if ($includeContent) {
                $chunk = $this->index->getChunk($chunkId);
                $output['full_content'] = null !== $chunk ? $chunk['content'] : 'Content not available';
            }

            return $output;
        }, $results);
    }

    /**
     * @param string  $query
     * @param ?string $sdk
     * @param int     $limit
     *
     * @throws RuntimeException
     *
     * @return array<array<mixed>>
     */
    private function searchMethods(string $query, ?string $sdk, int $limit): array
    {
        $results = $this->index->searchChunks($query, $sdk, $limit * 2);
        $methodResults = [];

        foreach ($results as $result) {
            /** @var mixed $method */
            $method = $result['metadata']['method'] ?? null;

            if (null !== $method && is_string($method) && '' !== $method) {
                /** @var mixed $classValue */
                $classValue = $result['metadata']['class'] ?? 'Unknown';
                $class = is_string($classValue) ? $classValue : 'Unknown';
                $sdk = $result['sdk'];
                $methodResults[] = [
                    'method_name' => $method,
                    'class_name' => $class,
                    'sdk' => $sdk,
                    'relevance_score' => $result['score'],
                    'section' => $result['metadata']['section'] ?? null,
                    'preview' => $result['preview'],
                    'uri' => sprintf('openfga://docs/%s/method/%s/%s', $sdk, $class, $method),
                ];
            }
        }

        usort(
            $methodResults,
            /**
             * @param array{method_name: string, class_name: string, sdk: string, relevance_score: float, section: mixed, preview: string, uri: string} $a
             * @param array{method_name: string, class_name: string, sdk: string, relevance_score: float, section: mixed, preview: string, uri: string} $b
             */
            static fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score'],
        );

        return array_slice($methodResults, 0, $limit);
    }

    /**
     * @param string  $query
     * @param ?string $sdk
     * @param int     $limit
     *
     * @throws RuntimeException
     *
     * @return array<array<mixed>>
     */
    private function searchSections(string $query, ?string $sdk, int $limit): array
    {
        $results = $this->index->searchChunks($query, $sdk, $limit * 2);
        $sectionResults = [];

        foreach ($results as $result) {
            /** @var mixed $section */
            $section = $result['metadata']['section'] ?? null;

            if (null !== $section && is_string($section) && '' !== $section) {
                $sdk = $result['sdk'];
                $key = $sdk . '::' . $section;

                if (! isset($sectionResults[$key])) {
                    $sectionResults[$key] = [
                        'section_name' => $section,
                        'sdk' => $sdk,
                        'relevance_score' => $result['score'],
                        'chunk_count' => 1,
                        'preview' => $result['preview'],
                        'uri' => sprintf('openfga://docs/%s/section/%s', $sdk, $section),
                    ];
                } else {
                    ++$sectionResults[$key]['chunk_count'];
                    $sectionResults[$key]['relevance_score'] = max($sectionResults[$key]['relevance_score'], $result['score']);
                }
            }
        }

        usort(
            $sectionResults,
            /**
             * @param array{section_name: string, sdk: string, relevance_score: float, chunk_count: int, preview: string, uri: string} $a
             * @param array{section_name: string, sdk: string, relevance_score: float, chunk_count: int, preview: string, uri: string} $b
             */
            static fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score'],
        );

        return array_slice($sectionResults, 0, $limit);
    }
}
