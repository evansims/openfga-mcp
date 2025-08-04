<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use Exception;
use OpenFGA\MCP\Completions\{ChunkIdCompletionProvider, ClassNameCompletionProvider, MethodNameCompletionProvider, SdkCompletionProvider, SectionNameCompletionProvider};
use OpenFGA\MCP\Documentation\{DocumentationIndex, DocumentationIndexSingleton};
use OpenFGA\MCP\Models\Factories\DocumentationFactory;
use OpenFGA\MCP\Models\{GuideDocumentation};
use OpenFGA\MCP\Responses\{getClassDocumentationException, getClassDocumentationNotFoundException, getClassDocumentationResponse, getDocumentationChunkException, getDocumentationChunkNotFoundException, getDocumentationChunkResponse, getDocumentationSectionException, getDocumentationSectionNotFoundException, getDocumentationSectionResponse, getMethodDocumentationException, getMethodDocumentationNotFoundException, getMethodDocumentationResponse, getSdkDocumentationException, getSdkDocumentationNotFoundException, getSdkDocumentationResponse, listDocumentationException, listDocumentationResponse, searchDocumentationException, searchDocumentationNoResultsResponse, searchDocumentationResponse};
use PhpMcp\Server\Attributes\{CompletionProvider, McpResource, McpResourceTemplate};

use function count;
use function implode;
use function in_array;
use function sprintf;
use function strlen;

final readonly class DocumentationResources extends AbstractResources
{
    private DocumentationIndex $index;

    public function __construct()
    {
        $this->index = DocumentationIndexSingleton::getInstance();
    }

    /**
     * Get detailed documentation for a specific class.
     *
     * @param  string                                                                                                                                                                                                                                                                                                                $sdk
     * @param  string                                                                                                                                                                                                                                                                                                                $className
     * @return array{status: string, class: string, sdk: string, error: string}|array{status: string, content: string, metadata: array{class: string, sdk: string, namespace: string|null, methods: array<string>, method_count: int}}|array{status: string, requested_class: string, sdk: string, available_classes: array<string>}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/class/{className}',
        name: 'get_class_documentation',
        mimeType: 'text/markdown',
    )]
    public function getClassDocumentation(
        #[CompletionProvider(provider: SdkCompletionProvider::class)]
        string $sdk,
        #[CompletionProvider(provider: ClassNameCompletionProvider::class)]
        string $className,
    ): array {
        try {
            $this->index->initialize();

            $classDoc = $this->index->getClassDocumentation($sdk, $className);

            if (null === $classDoc) {
                $overview = $this->index->getSdkOverview($sdk);

                return getClassDocumentationNotFoundException::create(
                    requestedClass: $className,
                    sdk: $sdk,
                    availableClasses: null !== $overview ? $overview['classes'] : [],
                );
            }

            return getClassDocumentationResponse::create(
                className: $className,
                sdk: $sdk,
                content: $classDoc['content'],
                namespace: $classDoc['namespace'] ?? null,
                methods: array_keys($classDoc['methods']),
                methodCount: count($classDoc['methods']),
            );
        } catch (Exception $exception) {
            return getClassDocumentationException::create(
                className: $className,
                sdk: $sdk,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * Get a specific content chunk by ID.
     *
     * @param  string                                                                                                                                                                                                                                                  $sdk
     * @param  string                                                                                                                                                                                                                                                  $chunkId
     * @return array{status: string, chunk_id: string, sdk: string, error: string}|array{status: string, content: string, metadata: array<string, mixed>, navigation: array<string, string>}|array{status: string, requested_chunk: string, sdk: string, note: string}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/chunk/{chunkId}',
        name: 'get_documentation_chunk',
        mimeType: 'text/markdown',
    )]
    public function getDocumentationChunk(
        #[CompletionProvider(provider: SdkCompletionProvider::class)]
        string $sdk,
        #[CompletionProvider(provider: ChunkIdCompletionProvider::class)]
        string $chunkId,
    ): array {
        try {
            $this->index->initialize();

            $chunk = $this->index->getChunk($chunkId);

            if (null === $chunk || $chunk['sdk'] !== $sdk) {
                return getDocumentationChunkNotFoundException::create(
                    requestedChunk: $chunkId,
                    sdk: $sdk,
                );
            }

            $navigation = [];

            if (isset($chunk['prev_chunk'])) {
                $navigation['previous'] = $chunk['prev_chunk'];
            }

            if (isset($chunk['next_chunk'])) {
                $navigation['next'] = $chunk['next_chunk'];
            }

            return getDocumentationChunkResponse::create(
                chunkId: $chunkId,
                sdk: $sdk,
                content: $chunk['content'],
                metadata: $chunk['metadata'] ?? [],
                navigation: $navigation,
            );
        } catch (Exception $exception) {
            return getDocumentationChunkException::create(
                chunkId: $chunkId,
                sdk: $sdk,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * Get content for a specific documentation section.
     *
     * @param  string                                                                                                                                                                                                                                                                                       $sdk
     * @param  string                                                                                                                                                                                                                                                                                       $sectionName
     * @return array{status: string, content: string, metadata: array{section: string, sdk: string, chunk_count: int, total_size: int}}|array{status: string, requested_section: string, sdk: string, available_sections: array<string>}|array{status: string, section: string, sdk: string, error: string}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/section/{sectionName}',
        name: 'get_documentation_section',
        mimeType: 'text/markdown',
    )]
    public function getDocumentationSection(
        #[CompletionProvider(provider: SdkCompletionProvider::class)]
        string $sdk,
        #[CompletionProvider(provider: SectionNameCompletionProvider::class)]
        string $sectionName,
    ): array {
        try {
            $this->index->initialize();

            $chunks = $this->index->getChunksBySection($sdk, $sectionName);

            if ([] === $chunks) {
                $overview = $this->index->getSdkOverview($sdk);

                return getDocumentationSectionNotFoundException::create(
                    requestedSection: $sectionName,
                    sdk: $sdk,
                    availableSections: null !== $overview ? $overview['sections'] : [],
                );
            }

            $content = implode("\n\n---\n\n", array_map(static fn (array $chunk): string => $chunk['content'], $chunks));

            return getDocumentationSectionResponse::create(
                sectionName: $sectionName,
                sdk: $sdk,
                content: $content,
                chunkCount: count($chunks),
                totalSize: strlen($content),
            );
        } catch (Exception $exception) {
            return getDocumentationSectionException::create(
                sectionName: $sectionName,
                sdk: $sdk,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * Get detailed documentation for a specific SDK class method.
     *
     * @param  string                                                                                                                                                                                                                                                                                                                                                                     $sdk        the name of the SDK
     * @param  string                                                                                                                                                                                                                                                                                                                                                                     $className  the name of the class containing the method
     * @param  string                                                                                                                                                                                                                                                                                                                                                                     $methodName the name of the method to get documentation for
     * @return array{status: string, content: string, metadata: array{method: string, class: string, sdk: string, signature: string|null, parameters: array<mixed>, returns: string|null}}|array{status: string, method: string, class: string, sdk: string, error: string}|array{status: string, requested_method: string, class: string, sdk: string, available_methods: array<string>}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}/method/{className}/{methodName}',
        name: 'get_sdk_method_documentation',
        mimeType: 'text/markdown',
    )]
    public function getMethodDocumentation(
        #[CompletionProvider(provider: SdkCompletionProvider::class)]
        string $sdk,
        #[CompletionProvider(provider: ClassNameCompletionProvider::class)]
        string $className,
        #[CompletionProvider(provider: MethodNameCompletionProvider::class)]
        string $methodName,
    ): array {
        try {
            $this->index->initialize();

            $methodDoc = $this->index->getMethodDocumentation($sdk, $className, $methodName);

            if (null === $methodDoc) {
                $classDoc = $this->index->getClassDocumentation($sdk, $className);

                return getMethodDocumentationNotFoundException::create(
                    requestedMethod: $methodName,
                    className: $className,
                    sdk: $sdk,
                    availableMethods: null !== $classDoc ? array_keys($classDoc['methods']) : [],
                );
            }

            return getMethodDocumentationResponse::create(
                methodName: $methodName,
                className: $className,
                sdk: $sdk,
                content: $methodDoc['content'],
                signature: $methodDoc['signature'] ?? null,
                parameters: $methodDoc['parameters'] ?? [],
                returns: $methodDoc['returns'] ?? null,
            );
        } catch (Exception $exception) {
            return getMethodDocumentationException::create(
                methodName: $methodName,
                className: $className,
                sdk: $sdk,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * Get overview and sections for a specific SDK or general documentation.
     *
     * @param  string                                                                                                                                                                                                                                                                                                                                                                   $sdk the name of the SDK, 'general' for general documentation, or 'authoring' for authorization model best practice
     * @return array{status: string, requested_sdk: string, available_sdks: array<string>, available_general: array<string>}|array{status: string, sdk: string, error: string}|array{status: string, type: string, sdk: string, name: string, source: string|null, generated: string|null, sections: array<string>, total_chunks: int, classes?: int, endpoints: array<string, string>}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/{sdk}',
        name: 'get_documentation_overview',
        mimeType: 'application/json',
    )]
    public function getSdkDocumentation(
        #[CompletionProvider(provider: SdkCompletionProvider::class)]
        string $sdk,
    ): array {
        try {
            $this->index->initialize();

            $overview = $this->index->getSdkOverview($sdk);

            if (null === $overview) {
                return getSdkDocumentationNotFoundException::create(
                    requestedSdk: $sdk,
                    availableSdks: $this->index->getSdkList(),
                );
            }

            // Check if this is general documentation or SDK documentation
            $isGeneralDoc = in_array($sdk, ['general', 'authoring'], true);
            $status = $isGeneralDoc ? '✅ Documentation' : '✅ SDK Documentation';

            // Build endpoints based on documentation type
            $endpoints = [];

            if ($isGeneralDoc) {
                $endpoints[sprintf('openfga://docs/%s/section/{section}', $sdk)] = 'Available sections: ' . implode(', ', $overview['sections']);
            } else {
                $endpoints[sprintf('openfga://docs/%s/section/{section}', $sdk)] = 'Available sections: ' . implode(', ', $overview['sections']);
                $endpoints[sprintf('openfga://docs/%s/class/{class}', $sdk)] = 'Available classes: ' . implode(', ', $overview['classes']);
            }

            return getSdkDocumentationResponse::create(
                sdk: $sdk,
                name: $overview['name'],
                type: $isGeneralDoc ? 'general' : 'sdk',
                sections: $overview['sections'],
                totalChunks: $overview['total_chunks'],
                endpoints: $endpoints,
                source: $overview['source'] ?? null,
                generated: $overview['generated'] ?? null,
                classes: $isGeneralDoc ? null : count($overview['classes']),
                status: $status,
            );
        } catch (Exception $exception) {
            return getSdkDocumentationException::create(
                sdk: $sdk,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * Returns an index of all available OpenFGA documentation, including SDKs and best practice guides.
     *
     * @return array{status:string, error:string, note:string}|array{status:string, sdk_documentation:array<array{sdk:string, name:string, sections:int, classes:int, chunks:int, uri:string}>, guides_documentation:array<array{type:string, name:string, sections:int, chunks:int, uri:string}>, total_sdks:int, endpoints:array<string, string>}
     */
    #[McpResource(
        uri: 'openfga://docs',
        name: 'get_documentation_index',
        mimeType: 'application/json',
    )]
    public function listDocumentation(): array
    {
        try {
            $this->index->initialize();

            $sdkList = $this->index->getSdkList();

            $documentation = DocumentationFactory::createSdkDocumentationList(
                $sdkList,
                fn (string $sdk): ?array => $this->index->getSdkOverview($sdk),
            );

            $generalDocs = [];
            $guideTypes = ['authoring', 'general'];

            foreach ($guideTypes as $guideType) {
                $overview = $this->index->getSdkOverview($guideType);

                if (null !== $overview) {
                    $guide = DocumentationFactory::createGuideDocumentation($guideType, $overview);

                    if ($guide instanceof GuideDocumentation) {
                        $generalDocs[] = $guide;
                    }
                }
            }

            return listDocumentationResponse::create(
                sdkDocumentation: $documentation,
                guidesDocumentation: $generalDocs,
            );
        } catch (Exception $exception) {
            return listDocumentationException::create(
                error: $exception->getMessage(),
                note: 'Failed to initialize documentation index or retrieve documentation list',
            );
        }
    }

    /**
     * Search across all OpenFGA documentation content, including SDKs and best practice guides. Returns a JSON response with search results.
     *
     * @param  string                                                                                                                                                                                                                                                                                                        $query the search query string to find relevant documentation content
     * @return array{status:string, query:string, error:string}|array{status:string, query:string, suggestion:string, available_sdks:array<string>}|array{status:string, query:string, total_results:int, results:array<array{chunk_id:string, sdk:string, score:float, preview:string, metadata:array<mixed>, uri:string}>}
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://docs/search/{query}',
        name: 'search_documentation',
        mimeType: 'application/json',
    )]
    public function searchDocumentation(string $query): array
    {
        try {
            $this->index->initialize();

            $results = $this->index->searchChunks($query, null, 20);

            if ([] === $results) {
                return searchDocumentationNoResultsResponse::create(
                    query: $query,
                    availableSdks: $this->index->getSdkList(),
                );
            }

            $searchResults = DocumentationFactory::createSearchResults($results);

            if ([] === $searchResults) {
                return searchDocumentationNoResultsResponse::create(
                    query: $query,
                    availableSdks: $this->index->getSdkList(),
                    suggestion: 'No valid search results found. Please try a different query.',
                );
            }

            return searchDocumentationResponse::create(
                query: $query,
                results: $searchResults,
                totalResults: count($searchResults),
            );
        } catch (Exception $exception) {
            return searchDocumentationException::create(
                query: $query,
                error: $exception->getMessage(),
            );
        }
    }
}
