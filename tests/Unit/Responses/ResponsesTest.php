<?php

declare(strict_types=1);

namespace Tests\Unit\Responses;

use OpenFGA\MCP\Models\{GuideDocumentation, SdkDocumentation};
use OpenFGA\MCP\Responses\{AbstractResponse, getClassDocumentationException, getClassDocumentationNotFoundException, getClassDocumentationResponse, getDocumentationChunkException, getDocumentationChunkNotFoundException, getDocumentationChunkResponse, getDocumentationSectionException, getDocumentationSectionNotFoundException, getDocumentationSectionResponse, getMethodDocumentationException, getMethodDocumentationNotFoundException, getMethodDocumentationResponse, getSdkDocumentationException, getSdkDocumentationNotFoundException, getSdkDocumentationResponse, listDocumentationException, listDocumentationResponse, searchDocumentationException, searchDocumentationNoResultsResponse, searchDocumentationResponse};
use ReflectionClass;

// No cleanup needed since we're not using Mockery

describe('Response Classes', function (): void {
    describe('AbstractResponse', function (): void {
        it('is an abstract base class', function (): void {
            $reflection = new ReflectionClass(AbstractResponse::class);
            expect($reflection->isAbstract())->toBeTrue();
        });

        it('is readonly', function (): void {
            $reflection = new ReflectionClass(AbstractResponse::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });
    });

    describe('listDocumentationResponse', function (): void {
        it('serializes to JSON correctly', function (): void {
            // Create real SdkDocumentation instance
            $sdkDoc = new SdkDocumentation(
                'php',
                'PHP SDK',
                10,  // sections count
                5,   // classes count
                20,  // chunks count
                'openfga://docs/php',
            );

            // Create real GuideDocumentation instance
            $guideDoc = new GuideDocumentation(
                'guide',
                'Getting Started',
                3,   // sections count
                6,   // chunks count
                'openfga://docs/guide/getting-started',
            );

            $response = new listDocumentationResponse(
                [$sdkDoc],
                [$guideDoc],
            );

            $json = $response->jsonSerialize();

            expect($json)->toHaveKey('status');
            expect($json['status'])->toBe('✅ OpenFGA Documentation Available');
            expect($json)->toHaveKey('sdk_documentation');
            expect($json['sdk_documentation'])->toHaveCount(1);
            expect($json)->toHaveKey('guides_documentation');
            expect($json['guides_documentation'])->toHaveCount(1);
            expect($json)->toHaveKey('total_sdks');
            expect($json['total_sdks'])->toBe(2);
            expect($json)->toHaveKey('endpoints');
        });

        it('uses create factory method', function (): void {
            // Create real SdkDocumentation instance
            $sdkDoc = new SdkDocumentation(
                'go',
                'Go SDK',
                1,   // sections count
                0,   // classes count
                3,   // chunks count
                'openfga://docs/go',
            );

            $result = listDocumentationResponse::create([$sdkDoc], []);

            expect($result)->toBeArray();
            expect($result)->toHaveKey('status');
            expect($result)->toHaveKey('sdk_documentation');
            expect($result['total_sdks'])->toBe(1);
        });

        it('accepts custom status and endpoints', function (): void {
            $response = new listDocumentationResponse(
                [],
                [],
                'Custom Status',
                10,
                ['custom' => 'endpoint'],
            );

            $json = $response->jsonSerialize();

            expect($json['status'])->toBe('Custom Status');
            expect($json['total_sdks'])->toBe(10);
            expect($json['endpoints'])->toBe(['custom' => 'endpoint']);
        });
    });

    describe('getClassDocumentationResponse', function (): void {
        it('stores class documentation data', function (): void {
            $response = new getClassDocumentationResponse(
                'OpenFGAClient',
                'php',
                'Class description',
                'OpenFGA\\SDK',
                ['method1', 'method2'],
                2,
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('getDocumentationChunkResponse', function (): void {
        it('stores chunk data', function (): void {
            $response = new getDocumentationChunkResponse(
                'chunk-123',
                'php',
                'Chunk content',
                ['metadata' => 'value'],
                ['prev' => 'chunk-122', 'next' => 'chunk-124'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('getDocumentationSectionResponse', function (): void {
        it('stores section data', function (): void {
            $response = new getDocumentationSectionResponse(
                'installation',
                'php',
                'Section content',
                5,
                1024,
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('getMethodDocumentationResponse', function (): void {
        it('stores method documentation', function (): void {
            $response = new getMethodDocumentationResponse(
                'check',
                'OpenFGAClient',
                'php',
                'Check permission',
                'public function check($store_id, $tuple): bool',
                ['store_id', 'tuple'],
                'bool',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('getSdkDocumentationResponse', function (): void {
        it('stores SDK documentation', function (): void {
            $response = new getSdkDocumentationResponse(
                'python',
                'Python SDK',
                'SDK',
                ['installation', 'usage'],
                100,
                ['endpoint1', 'endpoint2'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('searchDocumentationResponse', function (): void {
        it('stores search results', function (): void {
            $results = [
                ['title' => 'Result 1', 'content' => 'Content 1'],
                ['title' => 'Result 2', 'content' => 'Content 2'],
            ];

            $response = new searchDocumentationResponse(
                'test query',
                $results,
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('searchDocumentationNoResultsResponse', function (): void {
        it('stores no results message', function (): void {
            $response = new searchDocumentationNoResultsResponse(
                'rare query',
                ['suggestion1', 'suggestion2'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('Exception Response Classes', function (): void {
        it('getClassDocumentationException extends AbstractResponse', function (): void {
            $response = new getClassDocumentationException(
                'InvalidClass',
                'php',
                'Error message',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getClassDocumentationNotFoundException extends AbstractResponse', function (): void {
            $response = new getClassDocumentationNotFoundException(
                'NonExistentClass',
                'php',
                ['ExistingClass1', 'ExistingClass2'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getDocumentationChunkException extends AbstractResponse', function (): void {
            $response = new getDocumentationChunkException(
                'chunk-999',
                'php',
                'Chunk error',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getDocumentationChunkNotFoundException extends AbstractResponse', function (): void {
            $response = new getDocumentationChunkNotFoundException(
                'chunk-404',
                'php',
                'Chunk not found',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getDocumentationSectionException extends AbstractResponse', function (): void {
            $response = new getDocumentationSectionException(
                'invalid-section',
                'php',
                'Section error',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getDocumentationSectionNotFoundException extends AbstractResponse', function (): void {
            $response = new getDocumentationSectionNotFoundException(
                'missing-section',
                'php',
                ['section1', 'section2'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getMethodDocumentationException extends AbstractResponse', function (): void {
            $response = new getMethodDocumentationException(
                'method',
                'Class',
                'php',
                'Method error',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getMethodDocumentationNotFoundException extends AbstractResponse', function (): void {
            $response = new getMethodDocumentationNotFoundException(
                'missingMethod',
                'Class',
                'php',
                ['method1', 'method2'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getSdkDocumentationException extends AbstractResponse', function (): void {
            $response = new getSdkDocumentationException(
                'invalid-sdk',
                'SDK error',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('getSdkDocumentationNotFoundException extends AbstractResponse', function (): void {
            $response = new getSdkDocumentationNotFoundException(
                'unknown-sdk',
                ['php', 'python', 'go'],
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('listDocumentationException extends AbstractResponse', function (): void {
            $response = new listDocumentationException(
                'Failed to list documentation',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });

        it('searchDocumentationException extends AbstractResponse', function (): void {
            $response = new searchDocumentationException(
                'Search failed',
                'bad query',
            );

            expect($response)->toBeInstanceOf(AbstractResponse::class);
        });
    });

    describe('Response Classes Common Behavior', function (): void {
        it('all response classes are final', function (): void {
            $responseClasses = [
                listDocumentationResponse::class,
                getClassDocumentationResponse::class,
                getDocumentationChunkResponse::class,
                getDocumentationSectionResponse::class,
                getMethodDocumentationResponse::class,
                getSdkDocumentationResponse::class,
                searchDocumentationResponse::class,
                searchDocumentationNoResultsResponse::class,
                getClassDocumentationException::class,
                getClassDocumentationNotFoundException::class,
                getDocumentationChunkException::class,
                getDocumentationChunkNotFoundException::class,
                getDocumentationSectionException::class,
                getDocumentationSectionNotFoundException::class,
                getMethodDocumentationException::class,
                getMethodDocumentationNotFoundException::class,
                getSdkDocumentationException::class,
                getSdkDocumentationNotFoundException::class,
                listDocumentationException::class,
                searchDocumentationException::class,
            ];

            foreach ($responseClasses as $class) {
                $reflection = new ReflectionClass($class);
                expect($reflection->isFinal())->toBeTrue("{$class} should be final");
            }
        });

        it('all response classes are readonly', function (): void {
            $responseClasses = [
                listDocumentationResponse::class,
                getClassDocumentationResponse::class,
                getDocumentationChunkResponse::class,
                getDocumentationSectionResponse::class,
                getMethodDocumentationResponse::class,
                getSdkDocumentationResponse::class,
                searchDocumentationResponse::class,
                searchDocumentationNoResultsResponse::class,
                getClassDocumentationException::class,
                getClassDocumentationNotFoundException::class,
                getDocumentationChunkException::class,
                getDocumentationChunkNotFoundException::class,
                getDocumentationSectionException::class,
                getDocumentationSectionNotFoundException::class,
                getMethodDocumentationException::class,
                getMethodDocumentationNotFoundException::class,
                getSdkDocumentationException::class,
                getSdkDocumentationNotFoundException::class,
                listDocumentationException::class,
                searchDocumentationException::class,
            ];

            foreach ($responseClasses as $class) {
                $reflection = new ReflectionClass($class);
                expect($reflection->isReadOnly())->toBeTrue("{$class} should be readonly");
            }
        });
    });
});
