<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\ModelResources;
use OpenFGA\Models\{AuthorizationModelInterface, TypeDefinitionInterface, TypeDefinitionRelationsInterface};
use OpenFGA\Models\Collections\{AuthorizationModelsInterface, TypeDefinitionsInterface};
use OpenFGA\Requests\{ClientRequestInterface};
use OpenFGA\Responses\{GetAuthorizationModelResponseInterface, ListAuthorizationModelsResponseInterface};
use OpenFGA\Results\SuccessInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->modelResources = new ModelResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('getModel resource', function (): void {
    it('returns model details successfully', function (): void {
        $storeId = 'test-store-id';
        $modelId = 'test-model-id';

        // Mock relations for user type
        $mockUserRelations = Mockery::mock(TypeDefinitionRelationsInterface::class);
        $mockUserRelations->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            'member' => new stdClass,
        ]));

        // Mock relations for document type
        $mockDocumentRelations = Mockery::mock(TypeDefinitionRelationsInterface::class);
        $mockDocumentRelations->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            'viewer' => new stdClass,
            'editor' => new stdClass,
        ]));

        // Mock type definitions
        $mockUserTypeDef = Mockery::mock(TypeDefinitionInterface::class);
        $mockUserTypeDef->shouldReceive('getType')->andReturn('user');
        $mockUserTypeDef->shouldReceive('getRelations')->andReturn($mockUserRelations);

        $mockDocumentTypeDef = Mockery::mock(TypeDefinitionInterface::class);
        $mockDocumentTypeDef->shouldReceive('getType')->andReturn('document');
        $mockDocumentTypeDef->shouldReceive('getRelations')->andReturn($mockDocumentRelations);

        // Mock type definitions collection
        $mockTypeDefinitions = Mockery::mock(TypeDefinitionsInterface::class);
        $mockTypeDefinitions->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            $mockUserTypeDef,
            $mockDocumentTypeDef,
        ]));

        // Mock authorization model
        $mockAuthModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockAuthModel->shouldReceive('getId')->andReturn($modelId);
        $mockAuthModel->shouldReceive('getTypeDefinitions')->andReturn($mockTypeDefinitions);

        $mockResponse = Mockery::mock(GetAuthorizationModelResponseInterface::class);
        $mockResponse->shouldReceive('getModel')->andReturn($mockAuthModel);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getModel($storeId, $modelId);

        expect($result)->toBeArray()
            ->and($result['id'])->toBe($modelId)
            ->and($result['schema_version'])->toBe('1.1')
            ->and($result['type_count'])->toBe(2)
            ->and($result['type_definitions'])->toHaveCount(2)
            ->and($result['type_definitions'][0]['type'])->toBe('user')
            ->and($result['type_definitions'][0]['relations'])->toContain('member')
            ->and($result['type_definitions'][1]['type'])->toBe('document')
            ->and($result['type_definitions'][1]['relations'])->toContain('viewer')
            ->and($result['type_definitions'][1]['relations'])->toContain('editor');
    });

    it('handles model not found error', function (): void {
        $storeId = 'test-store-id';
        $modelId = 'non-existent-model';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->withArgs(function ($callback): bool {
            $callback(new Exception('Model not found'));

            return true;
        })->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getModel($storeId, $modelId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('❌ Failed to fetch model!')
            ->and($result['error'])->toContain('Model not found');
    });
});

describe('getLatestModel resource', function (): void {
    it('returns latest model successfully', function (): void {
        $storeId = 'test-store-id';

        // Mock relations for user type
        $mockUserRelations = Mockery::mock(TypeDefinitionRelationsInterface::class);
        $mockUserRelations->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            'admin' => new stdClass,
        ]));

        // Mock type definition
        $mockUserTypeDef = Mockery::mock(TypeDefinitionInterface::class);
        $mockUserTypeDef->shouldReceive('getType')->andReturn('user');
        $mockUserTypeDef->shouldReceive('getRelations')->andReturn($mockUserRelations);

        // Mock type definitions collection
        $mockTypeDefinitions = Mockery::mock(TypeDefinitionsInterface::class);
        $mockTypeDefinitions->shouldReceive('getIterator')->andReturn(new ArrayIterator([
            $mockUserTypeDef,
        ]));

        // Mock the latest model
        $mockLatestModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockLatestModel->shouldReceive('getId')->andReturn('latest-model-id');
        $mockLatestModel->shouldReceive('getTypeDefinitions')->andReturn($mockTypeDefinitions);

        // Mock the models collection
        $mockModelsCollection = Mockery::mock(AuthorizationModelsInterface::class);
        $mockModelsCollection->shouldReceive('count')->andReturn(2);
        $mockModelsCollection->shouldReceive('offsetGet')->with(0)->andReturn($mockLatestModel);

        $mockResponse = Mockery::mock(ListAuthorizationModelsResponseInterface::class);
        $mockResponse->shouldReceive('getModels')->andReturn($mockModelsCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelResources->getLatestModel($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['id'])->toBe('latest-model-id')
            ->and($result['is_latest'])->toBe(true)
            ->and($result['type_count'])->toBe(1)
            ->and($result['type_definitions'][0]['type'])->toBe('user');
    });

    it('handles no models found', function (): void {
        $storeId = 'test-store-id';

        $mockResponse = Mockery::mock(ListAuthorizationModelsResponseInterface::class);
        $mockResponse->shouldReceive('authorizationModels')->andReturn([]);

        $mockModelRequest = Mockery::mock(ClientRequestInterface::class);
        $mockModelRequest->shouldReceive('failure')->andReturnSelf();
        $mockModelRequest->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $mockModelsInterface = Mockery::mock();
        $mockModelsInterface->shouldReceive('list')->andReturn($mockModelRequest);

        $mockModelsStoreInterface = Mockery::mock();
        $mockModelsStoreInterface->shouldReceive('store')->with($storeId)->andReturn($mockModelsInterface);

        $this->client->shouldReceive('models')->andReturn($mockModelsStoreInterface);

        $result = $this->modelResources->getLatestModel($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('❌ No models found in the store');
    });

    it('handles API errors', function (): void {
        $storeId = 'test-store-id';

        $mockModelRequest = Mockery::mock(ClientRequestInterface::class);
        $mockModelRequest->shouldReceive('failure')->withArgs(function ($callback): bool {
            $callback(new Exception('API Error'));

            return true;
        })->andReturnSelf();
        $mockModelRequest->shouldReceive('success')->andReturnSelf();

        $mockModelsInterface = Mockery::mock();
        $mockModelsInterface->shouldReceive('list')->andReturn($mockModelRequest);

        $mockModelsStoreInterface = Mockery::mock();
        $mockModelsStoreInterface->shouldReceive('store')->with($storeId)->andReturn($mockModelsInterface);

        $this->client->shouldReceive('models')->andReturn($mockModelsStoreInterface);

        $result = $this->modelResources->getLatestModel($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('❌ Failed to fetch models!')
            ->and($result['error'])->toContain('API Error');
    });
});
