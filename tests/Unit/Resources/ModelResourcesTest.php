<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\ModelResources;
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->modelResources = new ModelResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
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
