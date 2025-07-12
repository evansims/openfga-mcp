<?php

declare(strict_types=1);

use OpenFGA\MCP\Resources\ModelResources;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->modelResources = new ModelResources($this->client);
});

describe('ModelResources Integration', function (): void {
    it('gets model details', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type group
  relations
    define member: [user]

type document
  relations
    define reader: [user, group#member]
    define writer: [user]
    define owner: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Get model details
        $result = $this->modelResources->getModel($storeId, $modelId);

        expect($result)->toBeArray()
            ->and($result['id'])->toBe($modelId)
            ->and($result['schema_version'])->toBe('1.1')
            ->and($result['type_count'])->toBe(3)
            ->and($result['type_definitions'])->toHaveCount(3);

        // Check type definitions
        $types = array_column($result['type_definitions'], 'type');
        expect($types)->toContain('user')
            ->and($types)->toContain('group')
            ->and($types)->toContain('document');

        // Check document relations
        $documentType = null;

        foreach ($result['type_definitions'] as $typeDef) {
            if ('document' === $typeDef['type']) {
                $documentType = $typeDef;

                break;
            }
        }

        expect($documentType)->not->toBeNull()
            ->and($documentType['relations'])->toContain('reader')
            ->and($documentType['relations'])->toContain('writer')
            ->and($documentType['relations'])->toContain('owner');
    });

    it('gets latest model in store', function (): void {
        // Create store with initial model
        $dsl1 = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]';

        ['store' => $storeId] = setupTestStoreWithModel($dsl1);

        // Add another model (this will be the latest)
        $dsl2 = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]';

        $latestModelId = createTestModel($storeId, $dsl2);

        // Get latest model
        $result = $this->modelResources->getLatestModel($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['id'])->toBe($latestModelId)
            ->and($result['is_latest'])->toBe(true)
            ->and($result['type_count'])->toBe(2);

        // Check that writer relation exists (only in latest model)
        $documentType = null;

        foreach ($result['type_definitions'] as $typeDef) {
            if ('document' === $typeDef['type']) {
                $documentType = $typeDef;

                break;
            }
        }

        expect($documentType['relations'])->toContain('writer');
    });

    it('handles store with no models', function (): void {
        // Create store without model
        $storeId = setupTestStore();

        // Try to get latest model
        $result = $this->modelResources->getLatestModel($storeId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('error')
            ->and($result['error'])->toContain('❌ No models found in the store');
    });

    it('handles non-existent model', function (): void {
        $storeId = setupTestStore();

        $result = $this->modelResources->getModel($storeId, 'non-existent-model-id');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('error')
            ->and($result['error'])->toContain('❌ Failed to fetch model!');
    });
});
