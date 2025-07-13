<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\ModelIdCompletionProvider;
use OpenFGA\Operations\ListAuthorizationModelsOperation;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new ModelIdCompletionProvider($this->client);
});

describe('ModelIdCompletionProvider', function (): void {
    it('returns model IDs including "latest" when store ID is available', function (): void {
        // Mock configured store
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(ListAuthorizationModelsOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_models' => [
                        ['id' => 'model1'],
                        ['id' => 'model2'],
                        ['id' => 'model3'],
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
            ->andReturn(['latest', 'model1', 'model2', 'model3']);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest', 'model1', 'model2', 'model3']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns only "latest" when no store ID is available', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns only "latest" when store is restricted', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        // Since extractStoreIdFromSession returns the configured store,
        // but this test assumes restricted access to a different store
        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles API failure gracefully', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(ListAuthorizationModelsOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
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
            ->andReturn(['latest']);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles exception gracefully', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andThrow(new Exception('API Error'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('filters completions based on current value', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(ListAuthorizationModelsOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_models' => [
                        ['id' => 'model1'],
                        ['id' => 'model2'],
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
            ->andReturn(['model1', 'model2']); // filtered result without 'latest'

        $result = $this->provider->getCompletions('mod', $this->session);
        expect($result)->toBe(['model1', 'model2']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('filters out empty or invalid model IDs', function (): void {
        putenv('OPENFGA_MCP_API_STORE=test-store');

        $mockOperation = Mockery::mock(ListAuthorizationModelsOperation::class);

        $this->client->shouldReceive('models')
            ->once()
            ->with('test-store')
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('list')
            ->once()
            ->andReturn($mockOperation);

        $mockOperation->shouldReceive('onSuccess')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function ($callback) use ($mockOperation) {
                $response = [
                    'authorization_models' => [
                        ['id' => 'model1'],
                        ['id' => ''],  // Should be filtered out
                        [],  // Should be filtered out (no id key)
                        ['id' => 'model2'],
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
            ->andReturn(['latest', 'model1', 'model2']);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest', 'model1', 'model2']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
