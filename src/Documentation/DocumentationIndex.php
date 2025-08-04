<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Documentation;

use RuntimeException;

use function array_slice;
use function count;
use function is_string;
use function strlen;

final class DocumentationIndex
{
    private const int CHUNK_SIZE_LIMIT = 5000;

    private const string DOCS_PATH = __DIR__ . '/../../docs';

    /**
     * @var array<string, array{id: string, sdk: string, content: string, metadata: array{section: string|null, class: string|null, method: string|null, line_count: int, size_bytes: int}, prev_chunk?: string, next_chunk?: string}>
     */
    private array $chunks = [];

    /**
     * @var array<string, array{name: string, file: string, sections: array<string, array{line_start: int, chunks: array<string>}>, classes: array<string, array{namespace: string|null, methods: array<string, array{signature: string|null, parameters: array<mixed>, returns: string|null, chunk_id: string|null}>, chunk_id: string|null}>, chunks: array<string>, source: string|null, generated: string|null}>
     */
    private array $index = [];

    private bool $initialized = false;

    /**
     * @var array<string>
     */
    private array $sdkList = [];

    /**
     * @param string $chunkId
     *
     * @throws RuntimeException
     *
     * @return array{id: string, sdk: string, content: string, metadata: array{section: string|null, class: string|null, method: string|null, line_count: int, size_bytes: int}, prev_chunk?: string, next_chunk?: string}|null
     */
    public function getChunk(string $chunkId): ?array
    {
        $this->ensureInitialized();

        return $this->chunks[$chunkId] ?? null;
    }

    /**
     * @param string $sdk
     * @param string $section
     *
     * @throws RuntimeException
     *
     * @return array<array{id: string, sdk: string, content: string, metadata: array{section: string|null, class: string|null, method: string|null, line_count: int, size_bytes: int}, prev_chunk?: string, next_chunk?: string}>
     */
    public function getChunksBySection(string $sdk, string $section): array
    {
        $this->ensureInitialized();
        $sdkKey = strtolower($sdk);

        if (! isset($this->index[$sdkKey]['sections'][$section])) {
            return [];
        }

        $sectionData = $this->index[$sdkKey]['sections'][$section] ?? null;

        if (null === $sectionData) {
            return [];
        }

        $chunkIds = $sectionData['chunks'];
        $chunks = [];

        foreach ($chunkIds as $chunkId) {
            if (isset($this->chunks[$chunkId])) {
                $chunks[] = $this->chunks[$chunkId];
            }
        }

        return $chunks;
    }

    /**
     * @param string $sdk
     * @param string $className
     *
     * @throws RuntimeException
     *
     * @return array{class: string, sdk: string, namespace: string|null, methods: array<string, array{signature: string|null, parameters: array<mixed>, returns: string|null, chunk_id: string|null}>, content: string, metadata: array<mixed>}|null
     */
    public function getClassDocumentation(string $sdk, string $className): ?array
    {
        $this->ensureInitialized();
        $sdkKey = strtolower($sdk);

        if (! isset($this->index[$sdkKey]['classes'][$className])) {
            return null;
        }

        $classInfo = $this->index[$sdkKey]['classes'][$className];
        $chunkId = $classInfo['chunk_id'];

        if (null === $chunkId || ! isset($this->chunks[$chunkId])) {
            return null;
        }

        $chunk = $this->chunks[$chunkId];

        return [
            'class' => $className,
            'sdk' => $sdkKey,
            'namespace' => $classInfo['namespace'],
            'methods' => $classInfo['methods'],
            'content' => $chunk['content'],
            'metadata' => $chunk['metadata'],
        ];
    }

    /**
     * @param string $sdk
     * @param string $className
     * @param string $methodName
     *
     * @throws RuntimeException
     *
     * @return array{method: string, class: string, sdk: string, signature: string|null, parameters: array<mixed>, returns: string|null, content: string}|null
     */
    public function getMethodDocumentation(string $sdk, string $className, string $methodName): ?array
    {
        $this->ensureInitialized();
        $classDoc = $this->getClassDocumentation($sdk, $className);

        if (null === $classDoc || ! isset($classDoc['methods'][$methodName])) {
            return null;
        }

        $methodInfo = $classDoc['methods'][$methodName];
        $chunkId = $methodInfo['chunk_id'];

        if (null === $chunkId || ! isset($this->chunks[$chunkId])) {
            return null;
        }

        $chunk = $this->chunks[$chunkId];

        return [
            'method' => $methodName,
            'class' => $className,
            'sdk' => $sdk,
            'signature' => $methodInfo['signature'],
            'parameters' => $methodInfo['parameters'],
            'returns' => $methodInfo['returns'],
            'content' => $chunk['content'],
        ];
    }

    /**
     * @throws RuntimeException
     *
     * @return array<string>
     */
    public function getSdkList(): array
    {
        $this->ensureInitialized();

        return $this->sdkList;
    }

    /**
     * @param string $sdk
     *
     * @throws RuntimeException
     *
     * @return array{sdk: string, name: string, file: string, sections: array<string>, classes: array<string>, total_chunks: int, source: string|null, generated: string|null}|null
     */
    public function getSdkOverview(string $sdk): ?array
    {
        $this->ensureInitialized();
        $sdkKey = strtolower($sdk);

        if (! isset($this->index[$sdkKey])) {
            return null;
        }

        $sdkData = $this->index[$sdkKey];

        return [
            'sdk' => $sdkKey,
            'name' => $sdkData['name'],
            'file' => $sdkData['file'],
            'sections' => array_keys($sdkData['sections'] ?? []),
            'classes' => array_keys($sdkData['classes'] ?? []),
            'total_chunks' => count($sdkData['chunks'] ?? []),
            'source' => $sdkData['source'] ?? null,
            'generated' => $sdkData['generated'] ?? null,
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->scanDocumentationFiles();
        $this->buildIndex();
        $this->initialized = true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @param string  $query
     * @param ?string $sdk
     * @param int     $limit
     *
     * @throws RuntimeException
     *
     * @return array<array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>}>
     */
    public function searchChunks(string $query, ?string $sdk = null, int $limit = 10): array
    {
        $this->ensureInitialized();
        $results = [];
        $queryLower = strtolower($query);

        foreach ($this->chunks as $chunkId => $chunk) {
            if (null !== $sdk && $chunk['sdk'] !== strtolower($sdk)) {
                continue;
            }

            $content = strtolower($chunk['content']);
            $score = $this->calculateRelevanceScore($queryLower, $content, $chunk['metadata']);

            if (0 < $score) {
                $results[] = [
                    'chunk_id' => $chunkId,
                    'sdk' => $chunk['sdk'],
                    'score' => $score,
                    'preview' => $this->generatePreview($chunk['content'], $query),
                    'metadata' => $chunk['metadata'],
                ];
            }
        }

        usort(
            $results,
            /**
             * @param array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>} $a
             * @param array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>} $b
             */
            static fn (array $a, array $b): int => $b['score'] <=> $a['score'],
        );

        return array_slice($results, 0, $limit);
    }

    private function buildIndex(): void
    {
        // Build navigation links between chunks for each SDK
        foreach ($this->index as $sdkData) {
            if (! isset($sdkData['chunks'])) {
                continue;
            }

            if ([] === $sdkData['chunks']) {
                continue;
            }
            $chunkIds = $sdkData['chunks'];
            $counter = count($chunkIds);

            for ($i = 0; $i < $counter; ++$i) {
                $currentId = $chunkIds[$i];

                if (0 < $i) {
                    $this->chunks[$currentId]['prev_chunk'] = $chunkIds[$i - 1];
                }

                if ($i < count($chunkIds) - 1) {
                    $this->chunks[$currentId]['next_chunk'] = $chunkIds[$i + 1];
                }
            }
        }
    }

    /**
     * @param array<mixed> $metadata
     * @param string       $query
     * @param string       $content
     */
    private function calculateRelevanceScore(string $query, string $content, array $metadata): float
    {
        $score = 0.0;
        $queryTerms = explode(' ', $query);

        foreach ($queryTerms as $queryTerm) {
            $termCount = substr_count($content, $queryTerm);
            $score += (float) $termCount * 1.0;

            /** @var mixed $class */
            $class = $metadata['class'] ?? null;

            if (null !== $class && is_string($class) && false !== stripos($class, $queryTerm)) {
                $score += 5.0;
            }

            /** @var mixed $method */
            $method = $metadata['method'] ?? null;

            if (null !== $method && is_string($method) && false !== stripos($method, $queryTerm)) {
                $score += 3.0;
            }

            /** @var mixed $section */
            $section = $metadata['section'] ?? null;

            if (null !== $section && is_string($section) && false !== stripos($section, $queryTerm)) {
                $score += 2.0;
            }
        }

        return $score;
    }

    /**
     * @param array<string> $lines
     * @param string        $sdk
     * @param ?string       $section
     * @param ?string       $class
     * @param ?string       $method
     */
    private function createChunk(string $sdk, array $lines, ?string $section, ?string $class, ?string $method): void
    {
        $content = implode("\n", $lines);
        $chunkId = $sdk . '_chunk_' . str_pad((string) count($this->chunks), 6, '0', STR_PAD_LEFT);

        $metadata = [
            'section' => $section,
            'class' => $class,
            'method' => $method,
            'line_count' => count($lines),
            'size_bytes' => strlen($content),
        ];

        $this->chunks[$chunkId] = [
            'id' => $chunkId,
            'sdk' => $sdk,
            'content' => $content,
            'metadata' => $metadata,
        ];

        $this->index[$sdk]['chunks'][] = $chunkId;

        if (null !== $section && isset($this->index[$sdk]['sections'][$section])) {
            $this->index[$sdk]['sections'][$section]['chunks'][] = $chunkId;
        }

        if (null !== $class && isset($this->index[$sdk]['classes'][$class])) {
            if (null === $this->index[$sdk]['classes'][$class]['chunk_id']) {
                $this->index[$sdk]['classes'][$class]['chunk_id'] = $chunkId;
            }

            if (null !== $method && isset($this->index[$sdk]['classes'][$class]['methods'][$method])) {
                $this->index[$sdk]['classes'][$class]['methods'][$method]['chunk_id'] = $chunkId;
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function ensureInitialized(): void
    {
        if (! $this->initialized) {
            $this->initialize();
        }
    }

    private function extractClassNameFromSource(string $sourceFile): ?string
    {
        if (1 === preg_match('/\/([^\/]+)\.(php|go|py|java|cs|js|ts)$/', $sourceFile, $matches)) {
            // When preg_match returns 1, capturing groups are guaranteed to be set
            /** @var array{0: non-falsy-string, 1: non-empty-string, 2: non-empty-string} $matches */
            return $matches[1];
        }

        return null;
    }

    private function generatePreview(string $content, string $query, int $previewLength = 200): string
    {
        $queryLower = strtolower($query);
        $contentLower = strtolower($content);
        $position = strpos($contentLower, $queryLower);

        if (false === $position) {
            $queryTerms = explode(' ', $queryLower);

            foreach ($queryTerms as $queryTerm) {
                $position = strpos($contentLower, $queryTerm);

                if (false !== $position) {
                    break;
                }
            }
        }

        if (false === $position) {
            $position = 0;
        }

        $start = max(0, $position - 50);
        $end = min(strlen($content), $position + $previewLength);

        $preview = substr($content, $start, $end - $start);

        if (0 < $start) {
            $preview = '...' . ltrim($preview);
        }

        if ($end < strlen($content)) {
            return rtrim($preview) . '...';
        }

        return $preview;
    }

    private function parseDocumentationFile(string $file, string $sdk): void
    {
        $content = file_get_contents($file);

        if (false === $content) {
            return;
        }

        $lines = explode("\n", $content);
        $currentSection = null;
        $currentClass = null;
        $currentMethod = null;
        $buffer = [];
        $lineNumber = 0;
        $inSourceBlock = false;

        foreach ($lines as $line) {
            ++$lineNumber;

            if (1 === preg_match('/^> Compiled from: (.+)$/', $line, $matches)) {
                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $this->index[$sdk]['source'] = trim($matches[1]);
            }

            if (1 === preg_match('/^> Generated: (.+)$/', $line, $matches)) {
                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $this->index[$sdk]['generated'] = trim($matches[1]);
            }

            if (1 === preg_match('/^<!-- Source: (.+) -->$/', $line, $matches)) {
                if ([] !== $buffer) {
                    $this->createChunk($sdk, $buffer, $currentSection, $currentClass, $currentMethod);
                    $buffer = [];
                }
                $inSourceBlock = true;

                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $currentClass = $this->extractClassNameFromSource(trim($matches[1]));

                continue;
            }

            if (1 === preg_match('/^<!-- End of .+ -->$/', $line)) {
                if ([] !== $buffer) {
                    $this->createChunk($sdk, $buffer, $currentSection, $currentClass, $currentMethod);
                    $buffer = [];
                }
                $inSourceBlock = false;
                $currentClass = null;
                $currentMethod = null;

                continue;
            }

            if (1 === preg_match('/^## (.+)$/', $line, $matches)) {
                if ([] !== $buffer) {
                    $this->createChunk($sdk, $buffer, $currentSection, $currentClass, $currentMethod);
                    $buffer = [];
                }

                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $currentSection = trim($matches[1]);

                if (! isset($this->index[$sdk]['sections'][$currentSection])) {
                    $this->index[$sdk]['sections'][$currentSection] = [
                        'line_start' => $lineNumber,
                        'chunks' => [],
                    ];
                }
            }

            if (1 === preg_match('/^### (.+)$/', $line, $matches) && $inSourceBlock) {
                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $currentClass = trim($matches[1]);

                if (! isset($this->index[$sdk]['classes'][$currentClass])) {
                    $this->index[$sdk]['classes'][$currentClass] = [
                        'namespace' => null,
                        'methods' => [],
                        'chunk_id' => null,
                    ];
                }
            }

            if (null !== $currentClass && 1 === preg_match('/^##### (.+)$/', $line, $matches)) {
                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $currentMethod = trim($matches[1]);

                if (! isset($this->index[$sdk]['classes'][$currentClass]['methods'][$currentMethod])) {
                    $this->index[$sdk]['classes'][$currentClass]['methods'][$currentMethod] = [
                        'signature' => null,
                        'parameters' => [],
                        'returns' => null,
                        'chunk_id' => null,
                    ];
                }
            }

            $buffer[] = $line;

            if (self::CHUNK_SIZE_LIMIT <= count($buffer)) {
                $this->createChunk($sdk, $buffer, $currentSection, $currentClass, $currentMethod);
                $buffer = [];
            }
        }

        if ([] !== $buffer) {
            $this->createChunk($sdk, $buffer, $currentSection, $currentClass, $currentMethod);
        }
    }

    private function parseGeneralDocumentation(string $file, string $key): void
    {
        $content = file_get_contents($file);

        if (false === $content) {
            return;
        }

        $lines = explode("\n", $content);
        $currentSection = null;
        $buffer = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            ++$lineNumber;

            // Match both ## and ### headers for sections (but not #### or deeper)
            if (1 === preg_match('/^(##|###) (.+)$/', $line, $matches)) {
                if ([] !== $buffer) {
                    $this->createChunk($key, $buffer, $currentSection, null, null);
                    $buffer = [];
                }

                /** @var array{0: non-falsy-string, 1: '###'|'##', 2: non-empty-string} $matches */
                $currentSection = trim($matches[2]);

                // Handle JSX components more carefully
                // Replace <ProductName .../> with "OpenFGA"
                $cleaned = preg_replace('/<ProductName[^>]*\/>/', 'OpenFGA', $currentSection);
                $currentSection = $cleaned ?? $currentSection;

                // Clean up any remaining markdown/JSX formatting
                $currentSection = strip_tags($currentSection);
                // Remove JSX attributes like format={...}
                $cleaned = preg_replace('/\s*\{[^}]*\}\s*/', ' ', $currentSection);
                $currentSection = $cleaned ?? $currentSection;
                // Clean up any remaining < or > characters
                $currentSection = str_replace(['<', '>'], '', $currentSection);
                // Clean up multiple spaces
                $cleaned = preg_replace('/\s+/', ' ', $currentSection);
                $currentSection = $cleaned ?? $currentSection;
                $currentSection = trim($currentSection);

                // If section name is empty after cleaning, use the original with basic cleanup
                if ('' === trim($currentSection)) {
                    $currentSection = trim($matches[2]);
                    // Just remove the most problematic characters
                    $currentSection = str_replace(['<', '>', '{', '}', '/'], '', $currentSection);
                    $currentSection = trim($currentSection);

                    // If still empty, use a placeholder
                    if ('' === trim($currentSection)) {
                        $currentSection = 'Section ' . $lineNumber;
                    }
                }

                if (! isset($this->index[$key]['sections'][$currentSection])) {
                    $this->index[$key]['sections'][$currentSection] = [
                        'line_start' => $lineNumber,
                        'chunks' => [],
                    ];
                }
            }

            $buffer[] = $line;

            if (self::CHUNK_SIZE_LIMIT <= count($buffer)) {
                $this->createChunk($key, $buffer, $currentSection, null, null);
                $buffer = [];
            }
        }

        if ([] !== $buffer) {
            $this->createChunk($key, $buffer, $currentSection, null, null);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function scanDocumentationFiles(): void
    {
        if (! is_dir(self::DOCS_PATH)) {
            throw new RuntimeException('Documentation directory not found: ' . self::DOCS_PATH);
        }

        $files = glob(self::DOCS_PATH . '/*.md');

        if (false === $files) {
            throw new RuntimeException('Failed to scan documentation directory');
        }

        foreach ($files as $file) {
            $filename = basename($file);

            if (1 === preg_match('/^([A-Z]+)_SDK\.md$/', $filename, $matches)) {
                /** @var array{0: non-falsy-string, 1: non-empty-string} $matches */
                $sdkName = strtolower($matches[1]);
                $this->sdkList[] = $sdkName;
                $this->index[$sdkName] = [
                    'name' => $matches[1] . ' SDK',
                    'file' => $file,
                    'sections' => [],
                    'classes' => [],
                    'chunks' => [],
                    'source' => null,
                    'generated' => null,
                ];

                $this->parseDocumentationFile($file, $sdkName);
            } elseif ('AUTHORING_OPENFGA_MODELS.md' === $filename) {
                $this->index['authoring'] = [
                    'name' => 'Model Authoring Guide',
                    'file' => $file,
                    'sections' => [],
                    'classes' => [],
                    'chunks' => [],
                    'source' => null,
                    'generated' => null,
                ];
                $this->parseGeneralDocumentation($file, 'authoring');
            } elseif ('OPENFGA_DOCS.md' === $filename) {
                $this->index['general'] = [
                    'name' => 'OpenFGA Documentation',
                    'file' => $file,
                    'sections' => [],
                    'classes' => [],
                    'chunks' => [],
                    'source' => null,
                    'generated' => null,
                ];
                $this->parseGeneralDocumentation($file, 'general');
            }
        }
    }
}
