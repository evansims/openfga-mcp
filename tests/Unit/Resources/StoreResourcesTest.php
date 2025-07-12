<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\StoreResources;
use OpenFGA\Models\{AuthorizationModelInterface, StoreInterface};
use OpenFGA\Models\Collections\{AuthorizationModelsInterface, StoresInterface};
use OpenFGA\Responses\{GetStoreResponseInterface, ListAuthorizationModelsResponseInterface, ListStoresResponseInterface};
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->storeResources = new StoreResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('listStores resource', function (): void {
    it('returns stores list successfully', function (): void {
        // Mock individual stores
        $mockStore1 = Mockery::mock(StoreInterface::class);
        $mockStore1->shouldReceive('getId')->andReturn('store-1');
        $mockStore1->shouldReceive('getName')->andReturn('Test Store 1');
        $mockStore1->shouldReceive('getCreatedAt')->andReturn(new DateTimeImmutable('2024-01-01T00:00:00Z'));
        $mockStore1->shouldReceive('getUpdatedAt')->andReturn(new DateTimeImmutable('2024-01-02T00:00:00Z'));
        $mockStore1->shouldReceive('getDeletedAt')->andReturn(null);

        $mockStore2 = Mockery::mock(StoreInterface::class);
        $mockStore2->shouldReceive('getId')->andReturn('store-2');
        $mockStore2->shouldReceive('getName')->andReturn('Test Store 2');
        $mockStore2->shouldReceive('getCreatedAt')->andReturn(new DateTimeImmutable('2024-01-03T00:00:00Z'));
        $mockStore2->shouldReceive('getUpdatedAt')->andReturn(new DateTimeImmutable('2024-01-04T00:00:00Z'));
        $mockStore2->shouldReceive('getDeletedAt')->andReturn(null);

        // Mock stores collection
        $mockStoresCollection = Mockery::mock(StoresInterface::class);
        $mockStoresCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            $mockStore1,
            $mockStore2,
        ]));

        $mockResponse = Mockery::mock(ListStoresResponseInterface::class);
        $mockResponse->shouldReceive('getStores')->andReturn($mockStoresCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('listStores')->andReturn($mockPromise);

        $result = $this->storeResources->listStores();

        expect($result)->toBeArray()
            ->and($result['stores'])->toHaveCount(2)
            ->and($result['count'])->toBe(2)
            ->and($result['stores'][0]['id'])->toBe('store-1')
            ->and($result['stores'][0]['name'])->toBe('Test Store 1');
    });

    it('handles errors gracefully', function (): void {
        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->withArgs(function ($callback): bool {
            $callback(new Exception('API Error'));

            return true;
        })->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('listStores')->andReturn($mockPromise);

        $result = $this->storeResources->listStores();

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('❌ Failed to fetch stores!')
            ->and($result['error'])->toContain('API Error');
    });
});

describe('getStore resource', function (): void {
    it('returns store details successfully', function (): void {
        $storeId = 'test-store-id';

        $mockResponse = Mockery::mock(GetStoreResponseInterface::class);
        $mockResponse->shouldReceive('getId')->andReturn($storeId);
        $mockResponse->shouldReceive('getName')->andReturn('Test Store');
        $mockResponse->shouldReceive('getCreatedAt')->andReturn(new DateTimeImmutable('2024-01-01'));
        $mockResponse->shouldReceive('getUpdatedAt')->andReturn(new DateTimeImmutable('2024-01-02'));
        $mockResponse->shouldReceive('getDeletedAt')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with(store: $storeId)
            ->andReturn($mockPromise);

        $result = $this->storeResources->getStore($storeId);

        expect($result)->toBeArray()
            ->and($result['id'])->toBe($storeId)
            ->and($result['name'])->toBe('Test Store')
            ->and($result['created_at'])->toBe('2024-01-01T00:00:00+00:00');
    });

    it('handles store not found', function (): void {
        $storeId = 'non-existent-store';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->withArgs(function ($callback): bool {
            $callback(new Exception('Store not found'));

            return true;
        })->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with(store: $storeId)
            ->andReturn($mockPromise);

        $result = $this->storeResources->getStore($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('❌ Failed to fetch store!')
            ->and($result['error'])->toContain('Store not found');
    });
});

describe('listStoreModels resource', function (): void {
    it('returns models list successfully', function (): void {
        $storeId = 'test-store-id';

        // Mock individual models
        $mockModel1 = Mockery::mock(AuthorizationModelInterface::class);
        $mockModel1->shouldReceive('getId')->andReturn('model-1');
        $mockModel1->shouldReceive('getTypeDefinitions')->andReturn(Mockery::mock())->once();

        $mockModel2 = Mockery::mock(AuthorizationModelInterface::class);
        $mockModel2->shouldReceive('getId')->andReturn('model-2');
        $mockModel2->shouldReceive('getTypeDefinitions')->andReturn(Mockery::mock())->once();

        // Mock models collection
        $mockModelsCollection = Mockery::mock(AuthorizationModelsInterface::class);
        $mockModelsCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            $mockModel1,
            $mockModel2,
        ]));

        $mockResponse = Mockery::mock(ListAuthorizationModelsResponseInterface::class);
        $mockResponse->shouldReceive('getModels')->andReturn($mockModelsCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with(store: $storeId)
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['models'])->toHaveCount(2)
            ->and($result['count'])->toBe(2)
            ->and($result['models'][0]['id'])->toBe('model-1')
            ->and($result['models'][0]['type_definitions'])->toBe(0);
    });

    it('handles empty models list', function (): void {
        $storeId = 'test-store-id';

        // Mock empty models collection
        $mockModelsCollection = Mockery::mock(AuthorizationModelsInterface::class);
        $mockModelsCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));

        $mockResponse = Mockery::mock(ListAuthorizationModelsResponseInterface::class);
        $mockResponse->shouldReceive('getModels')->andReturn($mockModelsCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with(store: $storeId)
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['models'])->toBeEmpty()
            ->and($result['count'])->toBe(0);
    });
});
