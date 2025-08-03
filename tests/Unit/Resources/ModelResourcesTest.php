<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\ModelResources;
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->client = Mockery::mock(ClientInterface::class);
    $this->modelResources = new ModelResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
});

describe('getModel resource', function (): void {
    it('calls getAuthorizationModel on the client', function (): void {
        $storeId = 'test-store-id';
        $modelId = 'test-model-id';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getModel($storeId, $modelId);

        expect($result)->toBeArray();
    });

    it('handles getAuthorizationModel errors', function (): void {
        $storeId = 'test-store-id';
        $modelId = 'non-existent-model';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getModel($storeId, $modelId);

        expect($result)->toBeArray();
    });
});

describe('getLatestModel resource', function (): void {
    it('calls listAuthorizationModels on the client', function (): void {
        $storeId = 'test-store-id';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getLatestModel($storeId);

        // Without executing callbacks, result is empty
        expect($result)->toBeArray();
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

        $result = $this->modelResources->getLatestModel($storeId);

        // Without executing callbacks, result is empty
        expect($result)->toBeArray();
    });
});

describe('offline mode behavior', function (): void {
    it('prevents getModel in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('getAuthorizationModel')->never();

        $result = $this->modelResources->getModel('test-store-id', 'test-model-id');

        expect($result)->toBe(['error' => '❌ Getting model details requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents getLatestModel in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('listAuthorizationModels')->never();

        $result = $this->modelResources->getLatestModel('test-store-id');

        expect($result)->toBe(['error' => '❌ Getting latest model requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });
});
