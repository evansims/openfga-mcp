<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Factories;

use OpenFGA\MCP\Models\Builders\SearchResultBuilder;
use OpenFGA\MCP\Models\Factories\DocumentationFactory;
use OpenFGA\MCP\Models\{GuideDocumentation, SdkDocumentation, SearchResult};
use stdClass;

describe('DocumentationFactory', function (): void {
    describe('createGuideDocumentation', function (): void {
        it('creates GuideDocumentation from valid overview', function (): void {
            $overview = [
                'name' => 'Getting Started Guide',
                'sections' => ['intro', 'installation', 'configuration'],
                'total_chunks' => 25,
            ];

            $guide = DocumentationFactory::createGuideDocumentation('gettingstarted', $overview);

            expect($guide)->toBeInstanceOf(GuideDocumentation::class);
            $json = $guide->jsonSerialize();
            expect($json['type'])->toBe('gettingstarted');
            expect($json['name'])->toBe('Getting Started Guide');
            expect($json['sections'])->toBe(3);
            expect($json['chunks'])->toBe(25);
            expect($json['uri'])->toBe('openfga://docs/gettingstarted');
        });

        it('returns null when required fields are missing', function (): void {
            // Missing name
            $overview = [
                'sections' => ['intro'],
                'total_chunks' => 10,
            ];
            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);
            expect($guide)->toBeNull();

            // Missing sections
            $overview = [
                'name' => 'Guide',
                'total_chunks' => 10,
            ];
            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);
            expect($guide)->toBeNull();

            // Missing total_chunks
            $overview = [
                'name' => 'Guide',
                'sections' => ['intro'],
            ];
            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);
            expect($guide)->toBeNull();
        });

        it('handles non-scalar name values', function (): void {
            $overview = [
                'name' => ['array', 'name'], // Non-scalar
                'sections' => ['intro'],
                'total_chunks' => 10,
            ];

            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);

            // Non-scalar name becomes empty string, which fails validation
            expect($guide)->toBeNull();
        });

        it('handles non-array sections', function (): void {
            $overview = [
                'name' => 'Guide',
                'sections' => 'not-an-array', // Non-array
                'total_chunks' => 10,
            ];

            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);

            expect($guide)->toBeInstanceOf(GuideDocumentation::class);
            $json = $guide->jsonSerialize();
            expect($json['sections'])->toBe(0); // Converted to 0
        });

        it('handles non-numeric total_chunks', function (): void {
            $overview = [
                'name' => 'Guide',
                'sections' => ['intro'],
                'total_chunks' => 'not-a-number', // Non-numeric
            ];

            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);

            expect($guide)->toBeInstanceOf(GuideDocumentation::class);
            $json = $guide->jsonSerialize();
            expect($json['chunks'])->toBe(0); // Converted to 0
        });

        it('catches validation errors and returns null', function (): void {
            $overview = [
                'name' => 'Guide',
                'sections' => ['intro'],
                'total_chunks' => -10, // Negative chunks should fail validation
            ];

            $guide = DocumentationFactory::createGuideDocumentation('test', $overview);

            // Since GuideDocumentation validates non-negative chunks
            expect($guide)->toBeNull();
        });
    });

    describe('createSdkDocumentation', function (): void {
        it('creates SdkDocumentation from valid overview', function (): void {
            $overview = [
                'name' => 'PHP SDK Documentation',
                'sections' => ['installation', 'usage', 'api'],
                'classes' => ['Client', 'Model', 'Response'],
                'total_chunks' => 50,
            ];

            $sdk = DocumentationFactory::createSdkDocumentation('php', $overview);

            expect($sdk)->toBeInstanceOf(SdkDocumentation::class);
            $json = $sdk->jsonSerialize();
            expect($json['sdk'])->toBe('php');
            expect($json['name'])->toBe('PHP SDK Documentation');
            expect($json['sections'])->toBe(3);
            expect($json['classes'])->toBe(3);
            expect($json['chunks'])->toBe(50);
            expect($json['uri'])->toBe('openfga://docs/php');
        });

        it('returns null when required fields are missing', function (): void {
            // Missing name
            $overview = [
                'sections' => ['intro'],
                'classes' => ['Client'],
                'total_chunks' => 10,
            ];
            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);
            expect($sdk)->toBeNull();

            // Missing sections
            $overview = [
                'name' => 'SDK',
                'classes' => ['Client'],
                'total_chunks' => 10,
            ];
            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);
            expect($sdk)->toBeNull();

            // Missing classes
            $overview = [
                'name' => 'SDK',
                'sections' => ['intro'],
                'total_chunks' => 10,
            ];
            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);
            expect($sdk)->toBeNull();

            // Missing total_chunks
            $overview = [
                'name' => 'SDK',
                'sections' => ['intro'],
                'classes' => ['Client'],
            ];
            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);
            expect($sdk)->toBeNull();
        });

        it('handles non-scalar name values', function (): void {
            $overview = [
                'name' => new stdClass, // Non-scalar object
                'sections' => ['intro'],
                'classes' => ['Client'],
                'total_chunks' => 10,
            ];

            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);

            // Non-scalar name becomes empty string, which fails validation
            expect($sdk)->toBeNull();
        });

        it('handles non-array sections and classes', function (): void {
            $overview = [
                'name' => 'SDK',
                'sections' => 'not-an-array', // Non-array
                'classes' => 123, // Non-array
                'total_chunks' => 10,
            ];

            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);

            expect($sdk)->toBeInstanceOf(SdkDocumentation::class);
            $json = $sdk->jsonSerialize();
            expect($json['sections'])->toBe(0); // Converted to 0
            expect($json['classes'])->toBe(0); // Converted to 0
        });

        it('catches validation errors and returns null', function (): void {
            $overview = [
                'name' => 'SDK',
                'sections' => ['intro'],
                'classes' => ['Client'],
                'total_chunks' => -5, // Negative chunks should fail validation
            ];

            $sdk = DocumentationFactory::createSdkDocumentation('test', $overview);

            // Since SdkDocumentation validates non-negative chunks
            expect($sdk)->toBeNull();
        });
    });

    describe('createSdkDocumentationList', function (): void {
        it('creates multiple SDK documentation instances', function (): void {
            $sdkList = ['php', 'python', 'go'];

            $overviewProvider = function (string $sdk): ?array {
                return match ($sdk) {
                    'php' => [
                        'name' => 'PHP SDK',
                        'sections' => ['intro'],
                        'classes' => ['Client'],
                        'total_chunks' => 10,
                    ],
                    'python' => [
                        'name' => 'Python SDK',
                        'sections' => ['intro', 'api'],
                        'classes' => ['Client', 'Model'],
                        'total_chunks' => 20,
                    ],
                    'go' => [
                        'name' => 'Go SDK',
                        'sections' => ['intro', 'api', 'examples'],
                        'classes' => ['Client', 'Model', 'Response'],
                        'total_chunks' => 30,
                    ],
                    default => null,
                };
            };

            $documentation = DocumentationFactory::createSdkDocumentationList($sdkList, $overviewProvider);

            expect($documentation)->toHaveCount(3);
            expect($documentation[0])->toBeInstanceOf(SdkDocumentation::class);
            expect($documentation[1])->toBeInstanceOf(SdkDocumentation::class);
            expect($documentation[2])->toBeInstanceOf(SdkDocumentation::class);

            $json0 = $documentation[0]->jsonSerialize();
            expect($json0['sdk'])->toBe('php');
            expect($json0['name'])->toBe('PHP SDK');

            $json1 = $documentation[1]->jsonSerialize();
            expect($json1['sdk'])->toBe('python');
            expect($json1['name'])->toBe('Python SDK');

            $json2 = $documentation[2]->jsonSerialize();
            expect($json2['sdk'])->toBe('go');
            expect($json2['name'])->toBe('Go SDK');
        });

        it('skips SDKs with null overview', function (): void {
            $sdkList = ['php', 'unknown', 'python'];

            $overviewProvider = function (string $sdk): ?array {
                return match ($sdk) {
                    'php' => [
                        'name' => 'PHP SDK',
                        'sections' => ['intro'],
                        'classes' => ['Client'],
                        'total_chunks' => 10,
                    ],
                    'python' => [
                        'name' => 'Python SDK',
                        'sections' => ['intro'],
                        'classes' => ['Client'],
                        'total_chunks' => 20,
                    ],
                    default => null, // 'unknown' returns null
                };
            };

            $documentation = DocumentationFactory::createSdkDocumentationList($sdkList, $overviewProvider);

            expect($documentation)->toHaveCount(2);
            $json0 = $documentation[0]->jsonSerialize();
            expect($json0['sdk'])->toBe('php');
            $json1 = $documentation[1]->jsonSerialize();
            expect($json1['sdk'])->toBe('python');
        });

        it('skips SDKs that fail to create documentation', function (): void {
            $sdkList = ['valid', 'invalid', 'second'];

            $overviewProvider = function (string $sdk): ?array {
                return match ($sdk) {
                    'valid' => [
                        'name' => 'Valid SDK',
                        'sections' => ['intro'],
                        'classes' => ['Client'],
                        'total_chunks' => 10,
                    ],
                    'invalid' => [
                        // Missing required fields
                        'name' => 'Invalid SDK',
                    ],
                    'second' => [
                        'name' => 'Second SDK',
                        'sections' => ['intro'],
                        'classes' => ['Client'],
                        'total_chunks' => 20,
                    ],
                    default => null,
                };
            };

            $documentation = DocumentationFactory::createSdkDocumentationList($sdkList, $overviewProvider);

            expect($documentation)->toHaveCount(2);
            $json0 = $documentation[0]->jsonSerialize();
            expect($json0['sdk'])->toBe('valid');
            $json1 = $documentation[1]->jsonSerialize();
            expect($json1['sdk'])->toBe('second');
        });

        it('handles empty SDK list', function (): void {
            $sdkList = [];

            $overviewProvider = fn (string $sdk): ?array => null;

            $documentation = DocumentationFactory::createSdkDocumentationList($sdkList, $overviewProvider);

            expect($documentation)->toBe([]);
        });
    });

    describe('createSearchResult', function (): void {
        it('creates SearchResult from valid result array', function (): void {
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'php',
                'score' => 85.5, // Will be normalized to 0.855
                'preview' => 'This is a preview text',
                'metadata' => ['section' => 'installation'],
            ];

            $searchResult = DocumentationFactory::createSearchResult($result);

            expect($searchResult)->toBeInstanceOf(SearchResult::class);
            $json = $searchResult->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-123');
            expect($json['sdk'])->toBe('php');
            expect($json['score'])->toBe(0.855);
            expect($json['preview'])->toBe('This is a preview text');
            expect($json['metadata'])->toBe(['section' => 'installation']);
            expect($json['uri'])->toBe('openfga://docs/php/chunk/chunk-123');
        });

        it('returns null when required fields are missing', function (): void {
            // Missing chunk_id
            $result = [
                'sdk' => 'php',
                'score' => 85.5,
                'preview' => 'Preview',
            ];
            expect(DocumentationFactory::createSearchResult($result))->toBeNull();

            // Missing sdk
            $result = [
                'chunk_id' => 'chunk-123',
                'score' => 85.5,
                'preview' => 'Preview',
            ];
            expect(DocumentationFactory::createSearchResult($result))->toBeNull();

            // Missing score
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'php',
                'preview' => 'Preview',
            ];
            expect(DocumentationFactory::createSearchResult($result))->toBeNull();

            // Missing preview
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'php',
                'score' => 85.5,
            ];
            expect(DocumentationFactory::createSearchResult($result))->toBeNull();
        });

        it('normalizes score to 0.0-1.0 range', function (): void {
            // Score > 100 gets clamped to 1.0
            $result = [
                'chunk_id' => 'chunk-1',
                'sdk' => 'php',
                'score' => 150.0,
                'preview' => 'Preview',
            ];
            $searchResult = DocumentationFactory::createSearchResult($result);
            expect($searchResult->jsonSerialize()['score'])->toBe(1.0);

            // Score < 0 gets clamped to 0.0
            $result = [
                'chunk_id' => 'chunk-2',
                'sdk' => 'php',
                'score' => -50.0,
                'preview' => 'Preview',
            ];
            $searchResult = DocumentationFactory::createSearchResult($result);
            expect($searchResult->jsonSerialize()['score'])->toBe(0.0);

            // Score in range gets normalized
            $result = [
                'chunk_id' => 'chunk-3',
                'sdk' => 'php',
                'score' => 50.0,
                'preview' => 'Preview',
            ];
            $searchResult = DocumentationFactory::createSearchResult($result);
            expect($searchResult->jsonSerialize()['score'])->toBe(0.5);
        });

        it('handles non-scalar values', function (): void {
            $result = [
                'chunk_id' => ['array', 'value'], // Non-scalar
                'sdk' => new stdClass, // Non-scalar
                'score' => 'not-a-number', // Non-numeric
                'preview' => 12345, // Numeric but scalar
            ];

            $searchResult = DocumentationFactory::createSearchResult($result);

            // Non-scalar chunk_id and sdk become empty strings which fail validation
            expect($searchResult)->toBeNull();
        });

        it('handles missing metadata', function (): void {
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'php',
                'score' => 85.5,
                'preview' => 'Preview',
                // No metadata
            ];

            $searchResult = DocumentationFactory::createSearchResult($result);

            expect($searchResult)->toBeInstanceOf(SearchResult::class);
            $json = $searchResult->jsonSerialize();
            expect($json['metadata'])->toBe([]); // Default empty array
        });

        it('handles non-array metadata', function (): void {
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'php',
                'score' => 85.5,
                'preview' => 'Preview',
                'metadata' => 'not-an-array', // Non-array
            ];

            $searchResult = DocumentationFactory::createSearchResult($result);

            expect($searchResult)->toBeInstanceOf(SearchResult::class);
            $json = $searchResult->jsonSerialize();
            expect($json['metadata'])->toBe([]); // Converted to empty array
        });

        it('catches validation errors and returns null', function (): void {
            $result = [
                'chunk_id' => 'chunk-123',
                'sdk' => 'INVALID-SDK', // Invalid SDK format (uppercase)
                'score' => 85.5,
                'preview' => 'Preview',
            ];

            $searchResult = DocumentationFactory::createSearchResult($result);

            // Since SearchResult validates SDK format
            expect($searchResult)->toBeNull();
        });
    });

    describe('createSearchResults', function (): void {
        it('creates multiple SearchResult instances', function (): void {
            $results = [
                [
                    'chunk_id' => 'chunk-1',
                    'sdk' => 'php',
                    'score' => 90.0,
                    'preview' => 'First preview',
                ],
                [
                    'chunk_id' => 'chunk-2',
                    'sdk' => 'python',
                    'score' => 80.0,
                    'preview' => 'Second preview',
                ],
                [
                    'chunk_id' => 'chunk-3',
                    'sdk' => 'go',
                    'score' => 70.0,
                    'preview' => 'Third preview',
                ],
            ];

            $searchResults = DocumentationFactory::createSearchResults($results);

            expect($searchResults)->toHaveCount(3);
            expect($searchResults[0])->toBeInstanceOf(SearchResult::class);
            expect($searchResults[1])->toBeInstanceOf(SearchResult::class);
            expect($searchResults[2])->toBeInstanceOf(SearchResult::class);

            $json0 = $searchResults[0]->jsonSerialize();
            expect($json0['chunk_id'])->toBe('chunk-1');
            expect($json0['score'])->toBe(0.9);

            $json1 = $searchResults[1]->jsonSerialize();
            expect($json1['chunk_id'])->toBe('chunk-2');
            expect($json1['score'])->toBe(0.8);

            $json2 = $searchResults[2]->jsonSerialize();
            expect($json2['chunk_id'])->toBe('chunk-3');
            expect($json2['score'])->toBe(0.7);
        });

        it('skips invalid results', function (): void {
            $results = [
                [
                    'chunk_id' => 'valid-1',
                    'sdk' => 'php',
                    'score' => 90.0,
                    'preview' => 'Valid preview',
                ],
                [
                    // Missing required fields
                    'chunk_id' => 'invalid',
                ],
                [
                    'chunk_id' => 'valid-2',
                    'sdk' => 'python',
                    'score' => 80.0,
                    'preview' => 'Another valid preview',
                ],
            ];

            $searchResults = DocumentationFactory::createSearchResults($results);

            expect($searchResults)->toHaveCount(2);
            $json0 = $searchResults[0]->jsonSerialize();
            expect($json0['chunk_id'])->toBe('valid-1');
            $json1 = $searchResults[1]->jsonSerialize();
            expect($json1['chunk_id'])->toBe('valid-2');
        });

        it('handles empty results array', function (): void {
            $searchResults = DocumentationFactory::createSearchResults([]);

            expect($searchResults)->toBe([]);
        });
    });

    describe('searchResultBuilder', function (): void {
        it('creates a new SearchResultBuilder instance', function (): void {
            $builder = DocumentationFactory::searchResultBuilder();

            expect($builder)->toBeInstanceOf(SearchResultBuilder::class);
        });

        it('can build a SearchResult using the builder', function (): void {
            $builder = DocumentationFactory::searchResultBuilder();

            $result = $builder
                ->withChunkId('chunk-builder')
                ->withSdk('php')
                ->withScore(0.75)
                ->withPreview('Built with builder')
                ->withMetadata(['type' => 'example'])
                ->build();

            expect($result)->toBeInstanceOf(SearchResult::class);
            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-builder');
            expect($json['sdk'])->toBe('php');
            expect($json['score'])->toBe(0.75);
            expect($json['preview'])->toBe('Built with builder');
            expect($json['metadata'])->toBe(['type' => 'example']);
        });
    });

    describe('searchResultBuilderFromArray', function (): void {
        it('creates a pre-populated SearchResultBuilder', function (): void {
            $data = [
                'chunk_id' => 'chunk-prepop',
                'sdk' => 'python',
                'score' => 0.85,
                'preview' => 'Pre-populated builder',
                'metadata' => ['source' => 'array'],
            ];

            $builder = DocumentationFactory::searchResultBuilderFromArray($data);

            expect($builder)->toBeInstanceOf(SearchResultBuilder::class);

            $result = $builder->build();
            expect($result)->toBeInstanceOf(SearchResult::class);

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-prepop');
            expect($json['sdk'])->toBe('python');
            expect($json['score'])->toBe(0.85);
            expect($json['preview'])->toBe('Pre-populated builder');
            expect($json['metadata'])->toBe(['source' => 'array']);
        });

        it('can override pre-populated values', function (): void {
            $data = [
                'chunk_id' => 'original',
                'sdk' => 'python',
                'score' => 0.5,
                'preview' => 'Original preview',
            ];

            $builder = DocumentationFactory::searchResultBuilderFromArray($data);

            $result = $builder
                ->withChunkId('overridden')
                ->withScore(0.95)
                ->build();

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('overridden');
            expect($json['sdk'])->toBe('python'); // Not overridden
            expect($json['score'])->toBe(0.95); // Overridden
            expect($json['preview'])->toBe('Original preview'); // Not overridden
        });

        it('handles partial data array', function (): void {
            $data = [
                'chunk_id' => 'partial',
                'sdk' => 'go',
            ];

            $builder = DocumentationFactory::searchResultBuilderFromArray($data);

            $result = $builder
                ->withScore(0.6)
                ->withPreview('Added preview')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('partial');
            expect($json['sdk'])->toBe('go');
            expect($json['score'])->toBe(0.6);
            expect($json['preview'])->toBe('Added preview');
        });
    });
});
