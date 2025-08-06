<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use InvalidArgumentException;
use OpenFGA\MCP\Models\SearchResult;
use ReflectionClass;

describe('SearchResult', function (): void {
    describe('constructor validation', function (): void {
        it('creates valid SearchResult with all required fields', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.85,
                preview: 'This is a preview of the search result',
                metadata: ['section' => 'installation', 'type' => 'guide'],
                uri: 'openfga://docs/php/chunk/chunk-123',
            );

            expect($result)->toBeInstanceOf(SearchResult::class);
        });

        it('throws exception for empty chunk ID', function (): void {
            expect(fn () => new SearchResult(
                chunkId: '',
                sdk: 'php',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class, 'Chunk ID cannot be empty');
        });

        it('throws exception for empty SDK', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: '',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class, 'SDK identifier cannot be empty');
        });

        it('throws exception for invalid SDK format', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'PHP-SDK', // Should be lowercase only
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class, 'SDK identifier does not match required pattern: must contain only lowercase letters');
        });

        it('throws exception for SDK with numbers', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php8',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class, 'SDK identifier does not match required pattern: must contain only lowercase letters');
        });

        it('throws exception for score below 0', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: -0.1,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for score above 1', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 1.5,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class);
        });

        it('accepts score of exactly 0', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.0,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            );

            expect($result)->toBeInstanceOf(SearchResult::class);
        });

        it('accepts score of exactly 1', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 1.0,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php',
            );

            expect($result)->toBeInstanceOf(SearchResult::class);
        });

        it('throws exception for empty preview', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.85,
                preview: '',
                metadata: [],
                uri: 'openfga://docs/php',
            ))->toThrow(InvalidArgumentException::class, 'Search preview cannot be empty');
        });

        it('throws exception for invalid URI', function (): void {
            expect(fn () => new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'not a valid uri',
            ))->toThrow(InvalidArgumentException::class, 'Result URI must be a valid URI');
        });

        it('accepts valid openfga:// URI', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'openfga://docs/php/chunk/123',
            );

            expect($result)->toBeInstanceOf(SearchResult::class);
        });

        it('accepts valid http:// URI', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-123',
                sdk: 'php',
                score: 0.85,
                preview: 'Preview',
                metadata: [],
                uri: 'http://example.com/docs',
            );

            expect($result)->toBeInstanceOf(SearchResult::class);
        });
    });

    describe('jsonSerialize', function (): void {
        it('serializes all fields correctly', function (): void {
            $metadata = [
                'section' => 'installation',
                'type' => 'guide',
                'version' => '1.0.0',
            ];

            $result = new SearchResult(
                chunkId: 'chunk-456',
                sdk: 'python',
                score: 0.92,
                preview: 'Python SDK installation guide',
                metadata: $metadata,
                uri: 'openfga://docs/python/chunk/chunk-456',
            );

            $json = $result->jsonSerialize();

            expect($json)->toBeArray();
            expect($json['chunk_id'])->toBe('chunk-456');
            expect($json['sdk'])->toBe('python');
            expect($json['score'])->toBe(0.92);
            expect($json['preview'])->toBe('Python SDK installation guide');
            expect($json['metadata'])->toBe($metadata);
            expect($json['uri'])->toBe('openfga://docs/python/chunk/chunk-456');
        });

        it('handles empty metadata array', function (): void {
            $result = new SearchResult(
                chunkId: 'chunk-789',
                sdk: 'go',
                score: 0.75,
                preview: 'Go SDK guide',
                metadata: [],
                uri: 'openfga://docs/go',
            );

            $json = $result->jsonSerialize();

            expect($json['metadata'])->toBe([]);
        });

        it('handles complex metadata structures', function (): void {
            $metadata = [
                'nested' => [
                    'data' => [
                        'value' => 'test',
                    ],
                ],
                'array' => [1, 2, 3],
                'boolean' => true,
                'null' => null,
            ];

            $result = new SearchResult(
                chunkId: 'chunk-complex',
                sdk: 'java',
                score: 0.5,
                preview: 'Complex metadata test',
                metadata: $metadata,
                uri: 'openfga://docs/java',
            );

            $json = $result->jsonSerialize();

            expect($json['metadata'])->toBe($metadata);
        });
    });

    describe('SDK validation patterns', function (): void {
        it('accepts various valid SDK names', function (): void {
            $validSdks = ['php', 'python', 'go', 'java', 'javascript', 'dotnet', 'ruby'];

            foreach ($validSdks as $sdk) {
                $result = new SearchResult(
                    chunkId: 'chunk-test',
                    sdk: $sdk,
                    score: 0.5,
                    preview: 'Test',
                    metadata: [],
                    uri: "openfga://docs/{$sdk}",
                );

                expect($result)->toBeInstanceOf(SearchResult::class);
            }
        });

        it('rejects invalid SDK names', function (): void {
            $invalidSdks = ['PHP', 'php-sdk', 'php_sdk', 'php.sdk', '123php', 'php!'];

            foreach ($invalidSdks as $sdk) {
                expect(fn () => new SearchResult(
                    chunkId: 'chunk-test',
                    sdk: $sdk,
                    score: 0.5,
                    preview: 'Test',
                    metadata: [],
                    uri: 'openfga://docs/test',
                ))->toThrow(InvalidArgumentException::class);
            }
        });
    });

    describe('immutability', function (): void {
        it('is a readonly class', function (): void {
            $reflection = new ReflectionClass(SearchResult::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        it('is a final class', function (): void {
            $reflection = new ReflectionClass(SearchResult::class);
            expect($reflection->isFinal())->toBeTrue();
        });
    });
});
