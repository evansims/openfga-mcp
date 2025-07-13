<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\StoreIdCompletionProvider;
use OpenFGA\Operations\ListStoresOperation;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new StoreIdCompletionProvider($this->client);
});

describe('StoreIdCompletionProvider', function (): void {
    it('returns store IDs from OpenFGA API', function (): void {
        $mockOperation = Mockery::mock(ListStoresOperation::class);

        $this->client->shouldReceive('stores')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'stores' => [
                        ['id' => 'store1', 'name' => 'Store 1'],
                        ['id' => 'store2', 'name' => 'Store 2'],
                        ['id' => 'store3', 'name' => 'Store 3'],
                    ],
                ];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn(['store1', 'store2', 'store3']);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['store1', 'store2', 'store3']);
    });

    it('handles empty stores response gracefully', function (): void {
        $mockOperation = Mockery::mock(ListStoresOperation::class);

        $this->client->shouldReceive('stores')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = ['stores' => []];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn([]);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('handles API failure gracefully', function (): void {
        $mockOperation = Mockery::mock(ListStoresOperation::class);

        $this->client->shouldReceive('stores')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $callback();

                return $mockOperation;
            });

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn([]);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('handles exception gracefully', function (): void {
        $this->client->shouldReceive('stores')
            ->once()
            ->andThrow(new Exception('API Error'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);
    });

    it('filters store IDs with invalid data', function (): void {
        $mockOperation = Mockery::mock(ListStoresOperation::class);

        $this->client->shouldReceive('stores')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'stores' => [
                        ['id' => 'store1', 'name' => 'Store 1'],
                        ['id' => '', 'name' => 'Empty ID'],  // Should be filtered out
                        ['name' => 'No ID'],  // Should be filtered out
                        ['id' => 'store2', 'name' => 'Store 2'],
                    ],
                ];
                $callback($response);

                return $mockOperation;
            });

        $mockOperation->shouldReceive('onFailure')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('await')
            ->once()
            ->andReturn(['store1', 'store2']);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['store1', 'store2']);
    });
});
