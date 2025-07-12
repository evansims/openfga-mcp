<?php

declare(strict_types=1);

use OpenFGA\MCP\Resources\StoreResources;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->storeResources = new StoreResources($this->client);
});

describe('StoreResources Integration', function (): void {
    it('lists real stores including test stores', function (): void {
        // Create a test store
        $testStoreName = 'Test Store ' . uniqid();
        $storeId = createTestStore($testStoreName);

        try {
            // List stores
            $result = $this->storeResources->listStores();

            expect($result)->toBeArray()
                ->and($result)->toHaveKey('stores')
                ->and($result)->toHaveKey('count')
                ->and($result['count'])->toBeGreaterThan(0);

            // Find our test store
            $foundStore = null;

            foreach ($result['stores'] as $store) {
                if ($store['id'] === $storeId) {
                    $foundStore = $store;

                    break;
                }
            }

            expect($foundStore)->not->toBeNull()
                ->and($foundStore['name'])->toBe($testStoreName)
                ->and($foundStore['created_at'])->not->toBeNull();
        } finally {
            // Clean up
            deleteTestStore($storeId);
        }
    });

    it('gets specific store details', function (): void {
        // Create a test store
        $testStoreName = 'Test Store Details ' . uniqid();
        $storeId = createTestStore($testStoreName);

        try {
            // Get store details
            $result = $this->storeResources->getStore($storeId);

            expect($result)->toBeArray()
                ->and($result)->toHaveKey('id')
                ->and($result)->toHaveKey('name')
                ->and($result['id'])->toBe($storeId)
                ->and($result['name'])->toBe($testStoreName)
                ->and($result['created_at'])->not->toBeNull();
        } finally {
            // Clean up
            deleteTestStore($storeId);
        }
    });

    it('handles non-existent store gracefully', function (): void {
        $result = $this->storeResources->getStore('non-existent-store-id');

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('error')
            ->and($result['error'])->toContain('❌ Failed to fetch store!');
    });

    it('lists models in a store', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // List models
        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['models'])->toHaveCount(1)
            ->and($result['models'][0]['id'])->toBe($modelId)
            ->and($result['models'][0]['schema_version'])->toBe('1.1')
            ->and($result['models'][0]['type_definitions'])->toBe(2);
    });

    it('handles store with no models', function (): void {
        // Create store without model
        $storeId = setupTestStore();

        // List models
        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['models'])->toBeEmpty()
            ->and($result['count'])->toBe(0);
    });
});
