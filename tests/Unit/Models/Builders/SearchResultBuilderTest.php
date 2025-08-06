<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Builders;

use InvalidArgumentException;
use OpenFGA\MCP\Models\Builders\SearchResultBuilder;
use OpenFGA\MCP\Models\SearchResult;
use stdClass;

describe('SearchResultBuilder', function (): void {
    describe('create method', function (): void {
        it('creates a new builder instance', function (): void {
            $builder = SearchResultBuilder::create();

            expect($builder)->toBeInstanceOf(SearchResultBuilder::class);
        });
    });

    describe('fluent interface', function (): void {
        it('builds a complete SearchResult with all fields', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-001')
                ->withSdk('php')
                ->withScore(0.95)
                ->withPreview('This is a preview text')
                ->withMetadata(['key' => 'value'])
                ->withUri('openfga://docs/php/chunk/chunk-001')
                ->build();

            expect($result)->toBeInstanceOf(SearchResult::class);

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-001');
            expect($json['sdk'])->toBe('php');
            expect($json['score'])->toBe(0.95);
            expect($json['preview'])->toBe('This is a preview text');
            expect($json['metadata'])->toBe(['key' => 'value']);
            expect($json['uri'])->toBe('openfga://docs/php/chunk/chunk-001');
        });

        it('returns self for method chaining', function (): void {
            $builder = SearchResultBuilder::create();

            expect($builder->withChunkId('test'))->toBe($builder);
            expect($builder->withSdk('php'))->toBe($builder);
            expect($builder->withScore(0.5))->toBe($builder);
            expect($builder->withPreview('preview'))->toBe($builder);
            expect($builder->withMetadata([]))->toBe($builder);
            expect($builder->withUri('openfga://test'))->toBe($builder);
            expect($builder->addMetadata('key', 'value'))->toBe($builder);
        });
    });

    describe('auto-generate URI', function (): void {
        it('auto-generates URI when not explicitly set', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-auto')
                ->withSdk('python')
                ->withScore(0.8)
                ->withPreview('Auto URI test')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['uri'])->toBe('openfga://docs/python/chunk/chunk-auto');
        });

        it('uses explicit URI when provided', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-explicit')
                ->withSdk('go')
                ->withScore(0.7)
                ->withPreview('Explicit URI test')
                ->withUri('custom://uri/path')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['uri'])->toBe('custom://uri/path');
        });

        it('disables auto-generation when URI is set explicitly', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-test')
                    ->withSdk('java')
                    ->withScore(0.6)
                    ->withPreview('Test')
                    ->withUri('') // Empty URI disables auto-generation
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'Result URI must be a valid URI');
        });
    });

    describe('metadata handling', function (): void {
        it('adds individual metadata entries', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-meta')
                ->withSdk('ruby')
                ->withScore(0.9)
                ->withPreview('Metadata test')
                ->addMetadata('section', 'installation')
                ->addMetadata('version', '2.0.0')
                ->addMetadata('type', 'guide')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['metadata'])->toBe([
                'section' => 'installation',
                'version' => '2.0.0',
                'type' => 'guide',
            ]);
        });

        it('overwrites metadata with withMetadata', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-overwrite')
                ->withSdk('dotnet')
                ->withScore(0.85)
                ->withPreview('Overwrite test')
                ->addMetadata('old', 'value')
                ->withMetadata(['new' => 'metadata'])
                ->build();

            $json = $result->jsonSerialize();
            expect($json['metadata'])->toBe(['new' => 'metadata']);
        });

        it('overwrites existing metadata keys with addMetadata', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-update')
                ->withSdk('javascript')
                ->withScore(0.75)
                ->withPreview('Update test')
                ->addMetadata('key', 'original')
                ->addMetadata('key', 'updated')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['metadata']['key'])->toBe('updated');
        });
    });

    describe('fromArray method', function (): void {
        it('populates builder from array with all fields', function (): void {
            $data = [
                'chunk_id' => 'chunk-array',
                'sdk' => 'php',
                'score' => 0.88,
                'preview' => 'Array preview',
                'metadata' => ['imported' => true],
                'uri' => 'openfga://imported',
            ];

            $result = SearchResultBuilder::create()
                ->fromArray($data)
                ->build();

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-array');
            expect($json['sdk'])->toBe('php');
            expect($json['score'])->toBe(0.88);
            expect($json['preview'])->toBe('Array preview');
            expect($json['metadata'])->toBe(['imported' => true]);
            expect($json['uri'])->toBe('openfga://imported');
        });

        it('handles partial array data', function (): void {
            $data = [
                'chunk_id' => 'partial',
                'sdk' => 'go',
            ];

            $result = SearchResultBuilder::create()
                ->fromArray($data)
                ->withScore(0.5) // Add missing required fields
                ->withPreview('Added preview')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('partial');
            expect($json['sdk'])->toBe('go');
        });

        it('converts numeric score to float', function (): void {
            $data = [
                'chunk_id' => 'numeric',
                'sdk' => 'python',
                'score' => '0.77', // String numeric
                'preview' => 'Test',
            ];

            $result = SearchResultBuilder::create()
                ->fromArray($data)
                ->build();

            $json = $result->jsonSerialize();
            expect($json['score'])->toBe(0.77);
        });

        it('ignores invalid data types in array', function (): void {
            $data = [
                'chunk_id' => ['invalid'], // Should be scalar
                'sdk' => new stdClass, // Should be scalar
                'score' => 'not-a-number',
                'preview' => 123, // Will be converted to string
                'metadata' => 'not-an-array',
                'uri' => false, // Will be converted to string
            ];

            $result = SearchResultBuilder::create()
                ->fromArray($data)
                ->withChunkId('valid-chunk')
                ->withSdk('php')
                ->withScore(0.5)
                ->withPreview('Valid preview')
                ->build();

            expect($result)->toBeInstanceOf(SearchResult::class);
        });
    });

    describe('validation', function (): void {
        it('throws exception when chunk ID is missing', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withSdk('php')
                    ->withScore(0.5)
                    ->withPreview('Preview')
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'Chunk ID is required');
        });

        it('throws exception when SDK is missing', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withScore(0.5)
                    ->withPreview('Preview')
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'SDK is required');
        });

        it('throws exception when score is missing', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withSdk('php')
                    ->withPreview('Preview')
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'Score is required');
        });

        it('throws exception when preview is missing', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withSdk('php')
                    ->withScore(0.5)
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'Preview is required');
        });

        it('throws exception when URI is required but not set', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withSdk('php')
                    ->withScore(0.5)
                    ->withPreview('Preview')
                    ->withUri('') // Empty URI disables auto-generation
                    ->build(),
            )->toThrow(InvalidArgumentException::class);
        });

        it('validates SDK format through SearchResult', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withSdk('INVALID-SDK')
                    ->withScore(0.5)
                    ->withPreview('Preview')
                    ->build(),
            )->toThrow(InvalidArgumentException::class, 'SDK identifier does not match required pattern: must contain only lowercase letters');
        });

        it('validates score range through SearchResult', function (): void {
            expect(
                fn () => SearchResultBuilder::create()
                    ->withChunkId('chunk-123')
                    ->withSdk('php')
                    ->withScore(1.5)
                    ->withPreview('Preview')
                    ->build(),
            )->toThrow(InvalidArgumentException::class);
        });
    });

    describe('edge cases', function (): void {
        it('handles very long preview text', function (): void {
            $longPreview = str_repeat('Lorem ipsum ', 1000);

            $result = SearchResultBuilder::create()
                ->withChunkId('long-preview')
                ->withSdk('php')
                ->withScore(0.5)
                ->withPreview($longPreview)
                ->build();

            $json = $result->jsonSerialize();
            expect($json['preview'])->toBe($longPreview);
        });

        it('handles special characters in chunk ID', function (): void {
            $result = SearchResultBuilder::create()
                ->withChunkId('chunk-with-special_chars.123')
                ->withSdk('php')
                ->withScore(0.5)
                ->withPreview('Test')
                ->build();

            $json = $result->jsonSerialize();
            expect($json['chunk_id'])->toBe('chunk-with-special_chars.123');
        });

        it('handles deeply nested metadata', function (): void {
            $metadata = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => 'deep value',
                        ],
                    ],
                ],
            ];

            $result = SearchResultBuilder::create()
                ->withChunkId('nested')
                ->withSdk('php')
                ->withScore(0.5)
                ->withPreview('Test')
                ->withMetadata($metadata)
                ->build();

            $json = $result->jsonSerialize();
            expect($json['metadata'])->toBe($metadata);
        });
    });
});
