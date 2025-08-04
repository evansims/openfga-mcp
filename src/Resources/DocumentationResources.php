<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use Exception;
use OpenFGA\MCP\Documentation\DocumentationIndex;
use PhpMcp\Server\Attributes\{McpResource, McpResourceTemplate};

use function count;
use function in_array;
use function sprintf;
use function strlen;

final readonly class DocumentationResources extends AbstractResources
{
    private DocumentationIndex $index;

    public function __construct()
    {
        $this->index = new DocumentationIndex;
    }

    /**
     * @return mixed[]
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/class/{className}',
        name: 'SDK Class Documentation',
        description: 'Get detailed documentation for a specific class',
        mimeType: 'text/markdown',
    )]
    public function getClassDocumentation(string $sdk, string $className): array
    {
        try {
            $this->index->initialize();

            $classDoc = $this->index->getClassDocumentation($sdk, $className);

            if (null === $classDoc) {
                $overview = $this->index->getSdkOverview($sdk);

                return [
                    '❌ Class documentation not found',
                    'requested_class' => $className,
                    'sdk' => $sdk,
                    'available_classes' => null !== $overview ? $overview['classes'] : [],
                ];
            }

            return [
                '✅ Class Documentation: ' . $className,
                'content' => $classDoc['content'],
                'metadata' => [
                    'class' => $className,
                    'sdk' => $sdk,
                    'namespace' => $classDoc['namespace'],
                    'methods' => array_keys($classDoc['methods']),
                    'method_count' => count($classDoc['methods']),
                ],
            ];
        } catch (Exception $exception) {
            return [
                '❌ Error loading class documentation',
                'class' => $className,
                'sdk' => $sdk,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  string                   $sdk
     * @param  string                   $chunkId
     * @return array<int|string, mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/chunk/{chunkId}',
        name: 'Documentation Chunk',
        description: 'Get a specific content chunk by ID',
        mimeType: 'text/markdown',
    )]
    public function getDocumentationChunk(string $sdk, string $chunkId): array
    {
        try {
            $this->index->initialize();

            $chunk = $this->index->getChunk($chunkId);

            if (null === $chunk || $chunk['sdk'] !== $sdk) {
                return [
                    '❌ Documentation chunk not found',
                    'requested_chunk' => $chunkId,
                    'sdk' => $sdk,
                    'note' => 'Chunk ID may be invalid or belong to a different SDK',
                ];
            }

            $navigation = [];

            if (isset($chunk['prev_chunk'])) {
                $navigation['previous'] = $chunk['prev_chunk'];
            }

            if (isset($chunk['next_chunk'])) {
                $navigation['next'] = $chunk['next_chunk'];
            }

            return [
                '✅ Documentation Chunk: ' . $chunkId,
                'content' => $chunk['content'],
                'metadata' => array_merge($chunk['metadata'] ?? [], [
                    'chunk_id' => $chunkId,
                    'sdk' => $sdk,
                ]),
                'navigation' => $navigation,
            ];
        } catch (Exception $exception) {
            return [
                '❌ Error loading documentation chunk',
                'chunk_id' => $chunkId,
                'sdk' => $sdk,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  string                   $sdk
     * @param  string                   $sectionName
     * @return array<int|string, mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/section/{sectionName}',
        name: 'Documentation Section',
        description: 'Get content for a specific documentation section',
        mimeType: 'text/markdown',
    )]
    public function getDocumentationSection(string $sdk, string $sectionName): array
    {
        try {
            $this->index->initialize();

            $chunks = $this->index->getChunksBySection($sdk, $sectionName);

            if ([] === $chunks) {
                $overview = $this->index->getSdkOverview($sdk);

                return [
                    '❌ Documentation section not found',
                    'requested_section' => $sectionName,
                    'sdk' => $sdk,
                    'available_sections' => null !== $overview ? $overview['sections'] : [],
                ];
            }

            $content = implode("\n\n---\n\n", array_map(static fn (array $chunk): string => $chunk['content'], $chunks));

            return [
                '✅ Documentation Section: ' . $sectionName,
                'content' => $content,
                'metadata' => [
                    'section' => $sectionName,
                    'sdk' => $sdk,
                    'chunk_count' => count($chunks),
                    'total_size' => strlen($content),
                ],
            ];
        } catch (Exception $exception) {
            return [
                '❌ Error loading documentation section',
                'section' => $sectionName,
                'sdk' => $sdk,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  string                   $sdk
     * @param  string                   $className
     * @param  string                   $methodName
     * @return array<int|string, mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/method/{className}/{methodName}',
        name: 'SDK Method Documentation',
        description: 'Get detailed documentation for a specific method',
        mimeType: 'text/markdown',
    )]
    public function getMethodDocumentation(string $sdk, string $className, string $methodName): array
    {
        try {
            $this->index->initialize();

            $methodDoc = $this->index->getMethodDocumentation($sdk, $className, $methodName);

            if (null === $methodDoc) {
                $classDoc = $this->index->getClassDocumentation($sdk, $className);

                return [
                    '❌ Method documentation not found',
                    'requested_method' => $methodName,
                    'class' => $className,
                    'sdk' => $sdk,
                    'available_methods' => null !== $classDoc ? array_keys($classDoc['methods']) : [],
                ];
            }

            return [
                '✅ Method Documentation: ' . $className . '::' . $methodName,
                'content' => $methodDoc['content'],
                'metadata' => [
                    'method' => $methodName,
                    'class' => $className,
                    'sdk' => $sdk,
                    'signature' => $methodDoc['signature'],
                    'parameters' => $methodDoc['parameters'],
                    'returns' => $methodDoc['returns'],
                ],
            ];
        } catch (Exception $exception) {
            return [
                '❌ Error loading method documentation',
                'method' => $methodName,
                'class' => $className,
                'sdk' => $sdk,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  string                   $sdk
     * @return array<int|string, mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}',
        name: 'SDK Documentation Overview',
        description: 'Get overview and sections for a specific SDK or general documentation',
        mimeType: 'application/json',
    )]
    public function getSdkDocumentation(string $sdk): array
    {
        try {
            $this->index->initialize();

            $overview = $this->index->getSdkOverview($sdk);

            if (null === $overview) {
                return [
                    '❌ Documentation not found',
                    'requested_sdk' => $sdk,
                    'available_sdks' => $this->index->getSdkList(),
                    'available_general' => ['general', 'authoring'],
                ];
            }

            // Check if this is general documentation or SDK documentation
            $isGeneralDoc = in_array($sdk, ['general', 'authoring'], true);
            $title = $isGeneralDoc ? '✅ Documentation' : '✅ SDK Documentation';

            $result = [
                $title . ': ' . $overview['name'],
                'type' => $isGeneralDoc ? 'general' : 'sdk',
                'sdk' => $sdk,
                'name' => $overview['name'],
                'source' => $overview['source'] ?? null,
                'generated' => $overview['generated'] ?? null,
                'sections' => $overview['sections'],
                'total_chunks' => $overview['total_chunks'],
            ];

            // Only add classes for SDK documentation
            if (false === $isGeneralDoc) {
                $result['classes'] = $overview['classes'];
                $result['endpoints'] = [
                    sprintf('openfga://docs/%s/section/{section}', $sdk) => 'Available sections: ' . implode(', ', $overview['sections']),
                    sprintf('openfga://docs/%s/class/{class}', $sdk) => 'Available classes: ' . implode(', ', $overview['classes']),
                ];
            } else {
                $result['endpoints'] = [
                    sprintf('openfga://docs/%s/section/{section}', $sdk) => 'Available sections: ' . implode(', ', $overview['sections']),
                ];
            }

            return $result;
        } catch (Exception $exception) {
            return [
                '❌ Error loading documentation',
                'sdk' => $sdk,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return mixed[]
     */
    #[McpResource(
        uri: 'openfga://docs',
        name: 'OpenFGA Documentation Index',
        description: 'List all available OpenFGA SDK documentation and guides',
        mimeType: 'application/json',
    )]
    public function listDocumentation(): array
    {
        try {
            $this->index->initialize();

            $sdkList = $this->index->getSdkList();
            $documentation = [];

            foreach ($sdkList as $sdk) {
                $overview = $this->index->getSdkOverview($sdk);

                if (null !== $overview) {
                    $documentation[] = [
                        'sdk' => $sdk,
                        'name' => $overview['name'],
                        'sections' => count($overview['sections']),
                        'classes' => count($overview['classes']),
                        'chunks' => $overview['total_chunks'],
                        'uri' => 'openfga://docs/' . $sdk,
                    ];
                }
            }

            $generalDocs = [];

            foreach (['authoring', 'general'] as $docType) {
                $overview = $this->index->getSdkOverview($docType);

                if (null !== $overview) {
                    $generalDocs[] = [
                        'type' => $docType,
                        'name' => $overview['name'],
                        'sections' => count($overview['sections']),
                        'chunks' => $overview['total_chunks'],
                        'uri' => 'openfga://docs/' . $docType,
                    ];
                }
            }

            return [
                '✅ Documentation Available',
                'sdk_documentation' => $documentation,
                'general_documentation' => $generalDocs,
                'total_sdks' => count($documentation),
                'endpoints' => [
                    'openfga://docs' => 'List all documentation',
                    'openfga://docs/{sdk}' => 'Get SDK overview',
                    'openfga://docs/{sdk}/class/{className}' => 'Get class documentation',
                    'openfga://docs/{sdk}/method/{className}/{methodName}' => 'Get method documentation',
                    'openfga://docs/{sdk}/section/{sectionName}' => 'Get documentation section',
                    'openfga://docs/{sdk}/chunk/{chunkId}' => 'Get specific content chunk',
                ],
            ];
        } catch (Exception $exception) {
            return [
                '❌ Failed to load documentation index',
                'error' => $exception->getMessage(),
                'note' => 'Ensure documentation files exist in the docs/ directory',
            ];
        }
    }

    /**
     * @param  string                   $query
     * @return array<int|string, mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/search/{query}',
        name: 'Search Documentation',
        description: 'Search across all documentation content',
        mimeType: 'application/json',
    )]
    public function searchDocumentation(string $query): array
    {
        try {
            $this->index->initialize();

            $results = $this->index->searchChunks($query, null, 20);

            if ([] === $results) {
                return [
                    '❌ No results found',
                    'query' => $query,
                    'suggestion' => 'Try broader search terms or check spelling',
                    'available_sdks' => $this->index->getSdkList(),
                ];
            }

            return [
                '✅ Search Results for: ' . $query,
                'query' => $query,
                'total_results' => count($results),
                'results' => array_map(static fn ($result): array => [
                    'chunk_id' => $result['chunk_id'],
                    'sdk' => $result['sdk'],
                    'score' => $result['score'],
                    'preview' => $result['preview'],
                    'metadata' => $result['metadata'],
                    'uri' => sprintf('openfga://docs/%s/chunk/%s', $result['sdk'], $result['chunk_id']),
                ], $results),
            ];
        } catch (Exception $exception) {
            return [
                '❌ Search failed',
                'query' => $query,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
