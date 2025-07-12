<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\StoreResources;
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->storeResources = new StoreResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('listStores resource', function (): void {
    it('calls listStores on the client', function (): void {
        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStores();

        expect($result)->toBeArray()
            ->and($result['stores'])->toBeArray()
            ->and($result['count'])->toBe(0);
    });

    it('handles listStores errors', function (): void {
        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStores();

        expect($result)->toBeArray()
            ->and($result['stores'])->toBeArray();
    });
});

describe('getStore resource', function (): void {
    it('calls getStore on the client', function (): void {
        $storeId = 'test-store-id';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->getStore($storeId);

        expect($result)->toBeArray();
    });

    it('handles getStore errors', function (): void {
        $storeId = 'non-existent-store';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->getStore($storeId);

        expect($result)->toBeArray();
    });
});

describe('listStoreModels resource', function (): void {
    it('calls listAuthorizationModels on the client', function (): void {
        $storeId = 'test-store-id';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['models'])->toBeArray()
            ->and($result['count'])->toBe(0);
    });

    it('handles listAuthorizationModels errors', function (): void {
        $storeId = 'test-store-id';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeResources->listStoreModels($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId);
    });
});
