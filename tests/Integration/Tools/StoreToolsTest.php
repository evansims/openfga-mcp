<?php

declare(strict_types=1);

use OpenFGA\MCP\Tools\StoreTools;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->storeTools = new StoreTools($this->client);
});

describe('StoreTools Integration', function (): void {
    it('can create a store', function (): void {
        $storeName = 'integration-test-store-' . uniqid();

        $result = $this->storeTools->createStore($storeName);

        expect($result)->toContain('✅ Successfully created store')
            ->and($result)->toContain($storeName);

        // Extract store ID from result for cleanup
        preg_match('/Store ID: ([a-zA-Z0-9-]+)/', $result, $matches);
        $storeId = $matches[1] ?? null;

        expect($storeId)->not->toBeNull();

        // Clean up
        deleteTestStore($storeId);
    });

    it('can list stores', function (): void {
        // Create test stores
        $storeId1 = createTestStore('list-test-1');
        $storeId2 = createTestStore('list-test-2');

        $result = $this->storeTools->listStores();

        expect($result)->toBeArray()
            ->and(array_column($result, 'id'))->toContain($storeId1)
            ->and(array_column($result, 'id'))->toContain($storeId2);

        // Clean up
        deleteTestStore($storeId1);
        deleteTestStore($storeId2);
    });

    it('can get store details', function (): void {
        $storeName = 'get-test-store';
        $storeId = createTestStore($storeName);

        $result = $this->storeTools->getStore($storeId);

        expect($result)->toBeArray()
            ->and($result['id'])->toBe($storeId)
            ->and($result['name'])->toBe($storeName)
            ->and($result['created_at'])->toBeInstanceOf(DateTimeImmutable::class)
            ->and($result['updated_at'])->toBeInstanceOf(DateTimeImmutable::class)
            ->and($result['deleted_at'])->toBeNull();

        // Clean up
        deleteTestStore($storeId);
    });

    it('can delete a store', function (): void {
        $storeId = createTestStore('delete-test-store');

        // Verify store exists
        $beforeDelete = $this->storeTools->getStore($storeId);
        expect($beforeDelete)->toBeArray();

        // Delete the store
        $result = $this->storeTools->deleteStore($storeId);
        expect($result)->toBe('✅ Successfully deleted store!');

        // Verify store is deleted
        $afterDelete = $this->storeTools->getStore($storeId);
        expect($afterDelete)->toBeString()
            ->and($afterDelete)->toContain('Failed to get store');
    });

    it('handles non-existent store gracefully', function (): void {
        $fakeStoreId = '00000000-0000-0000-0000-000000000000';

        $result = $this->storeTools->getStore($fakeStoreId);

        expect($result)->toBeString()
            ->and($result)->toContain('Failed to get store');
    });

    it('respects read-only mode', function (): void {
        $_ENV['OPENFGA_MCP_API_WRITEABLE'] = 'false';

        $result = $this->storeTools->createStore('should-not-create');

        expect($result)->toBe('❌ Write operations are disabled for safety. To enable create stores, set OPENFGA_MCP_API_WRITEABLE=true.');

        putenv('OPENFGA_MCP_API_WRITEABLE=true');
        $_ENV['OPENFGA_MCP_API_WRITEABLE'] = 'true';
    });

    it('respects restricted mode for store access', function (): void {
        $allowedStoreId = createTestStore('allowed-store');
        $restrictedStoreId = createTestStore('restricted-store');

        $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
        $_ENV['OPENFGA_MCP_API_STORE'] = $allowedStoreId;

        // Should allow access to the allowed store
        $allowedResult = $this->storeTools->getStore($allowedStoreId);
        expect($allowedResult)->toBeArray()
            ->and($allowedResult['id'])->toBe($allowedStoreId);

        // Should block access to other stores
        $restrictedResult = $this->storeTools->getStore($restrictedStoreId);
        expect($restrictedResult)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $allowedStoreId . ' in this mode.');

        putenv('OPENFGA_MCP_API_RESTRICT=false');
        putenv('OPENFGA_MCP_API_STORE=false');
        $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'false';
        $_ENV['OPENFGA_MCP_API_STORE'] = 'false';

        // Clean up
        deleteTestStore($allowedStoreId);
        deleteTestStore($restrictedStoreId);
    });
});
