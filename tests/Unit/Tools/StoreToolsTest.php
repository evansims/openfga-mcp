<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Tools\StoreTools;
use OpenFGA\Responses\{CreateStoreResponseInterface, GetStoreResponseInterface};
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');
    // Enable write operations for unit tests
    putenv('OPENFGA_MCP_API_WRITEABLE=true');

    $this->client = Mockery::mock(ClientInterface::class);
    $this->storeTools = new StoreTools($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
    putenv('OPENFGA_MCP_API_WRITEABLE=');
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
});

describe('createStore', function (): void {
    it('creates a store successfully', function (): void {
        $storeName = 'test-store';
        $storeId = 'store-123';

        $mockResponse = Mockery::mock(CreateStoreResponseInterface::class);
        $mockResponse->shouldReceive('getId')->andReturn($storeId);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('createStore')
            ->with($storeName)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->createStore($storeName);

        expect($result)->toContain('✅ Successfully created store')
            ->and($result)->toContain($storeName)
            ->and($result)->toContain($storeId);
    });

    it('handles store creation failure', function (): void {
        $storeName = 'test-store';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('createStore')
            ->with($storeName)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->createStore($storeName);

        expect($result)->toContain('❌ Failed to create store')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents store creation in read-only mode', function (): void {
        putenv('OPENFGA_MCP_API_WRITEABLE=false');

        $this->client->shouldReceive('createStore')->never();

        $result = $this->storeTools->createStore('test-store');

        expect($result)->toBe('❌ Write operations are disabled for safety. To enable create stores, set OPENFGA_MCP_API_WRITEABLE=true.');
    });

    it('prevents store creation in restricted mode', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');

        $this->client->shouldReceive('createStore')->never();

        $result = $this->storeTools->createStore('test-store');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot create stores in this mode.');
    });

    it('prevents store creation in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('createStore')->never();

        $result = $this->storeTools->createStore('test-store');

        expect($result)->toBe('❌ Creating stores requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.');
    });
});

describe('deleteStore', function (): void {
    it('deletes a store successfully', function (): void {
        $storeId = 'store-123';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) {
            $callback();

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('deleteStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->deleteStore($storeId);

        expect($result)->toBe('✅ Successfully deleted store!');
    });

    it('handles store deletion failure', function (): void {
        $storeId = 'store-123';
        $errorMessage = 'Store not found';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('deleteStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->deleteStore($storeId);

        expect($result)->toContain('❌ Failed to delete store')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents store deletion in read-only mode', function (): void {
        putenv('OPENFGA_MCP_API_WRITEABLE=false');

        $this->client->shouldReceive('deleteStore')->never();

        $result = $this->storeTools->deleteStore('store-123');

        expect($result)->toBe('❌ Write operations are disabled for safety. To enable delete stores, set OPENFGA_MCP_API_WRITEABLE=true.');
    });

    it('prevents store deletion in restricted mode', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');

        $this->client->shouldReceive('deleteStore')->never();

        $result = $this->storeTools->deleteStore('store-123');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot delete stores in this mode.');
    });
});

describe('getStore', function (): void {
    it('gets store details successfully', function (): void {
        $storeId = 'store-123';
        $storeName = 'test-store';
        $createdAt = new DateTimeImmutable('2024-01-01');
        $updatedAt = new DateTimeImmutable('2024-01-02');

        $mockResponse = Mockery::mock(GetStoreResponseInterface::class);
        $mockResponse->shouldReceive('getId')->andReturn($storeId);
        $mockResponse->shouldReceive('getName')->andReturn($storeName);
        $mockResponse->shouldReceive('getCreatedAt')->andReturn($createdAt);
        $mockResponse->shouldReceive('getUpdatedAt')->andReturn($updatedAt);
        $mockResponse->shouldReceive('getDeletedAt')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->getStore($storeId);

        expect($result)->toBeArray()
            ->and($result['id'])->toBe($storeId)
            ->and($result['name'])->toBe($storeName)
            ->and($result['created_at'])->toBe($createdAt)
            ->and($result['updated_at'])->toBe($updatedAt)
            ->and($result['deleted_at'])->toBeNull();
    });

    it('handles get store failure', function (): void {
        $storeId = 'store-123';
        $errorMessage = 'Store not found';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->getStore($storeId);

        expect($result)->toContain('❌ Failed to get store')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents getting non-restricted store in restricted mode', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('getStore')->never();

        $result = $this->storeTools->getStore('different-store');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });

    it('allows getting restricted store in restricted mode', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $mockResponse = Mockery::mock(GetStoreResponseInterface::class);
        $mockResponse->shouldReceive('getId')->andReturn('allowed-store');
        $mockResponse->shouldReceive('getName')->andReturn('test');
        $mockResponse->shouldReceive('getCreatedAt')->andReturn(new DateTimeImmutable);
        $mockResponse->shouldReceive('getUpdatedAt')->andReturn(new DateTimeImmutable);
        $mockResponse->shouldReceive('getDeletedAt')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getStore')
            ->with('allowed-store')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->getStore('allowed-store');

        expect($result)->toBeArray();
    });
});

describe('listStores', function (): void {
    it('handles list stores failure', function (): void {
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->storeTools->listStores();

        expect($result)->toContain('❌ Failed to list stores')
            ->and($result)->toContain($errorMessage);
    });
});
