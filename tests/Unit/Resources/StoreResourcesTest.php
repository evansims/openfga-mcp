<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\StoreResources;
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->client = Mockery::mock(ClientInterface::class);
    $this->storeResources = new StoreResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
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

describe('offline mode behavior', function (): void {
    it('prevents listStores in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('listStores')->never();

        $result = $this->storeResources->listStores();

        expect($result)->toBe(['error' => '❌ Listing stores requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents getStore in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('getStore')->never();

        $result = $this->storeResources->getStore('test-store-id');

        expect($result)->toBe(['error' => '❌ Fetching store details requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents listStoreModels in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('listAuthorizationModels')->never();

        $result = $this->storeResources->listStoreModels('test-store-id');

        expect($result)->toBe(['error' => '❌ Listing store models requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });
});
