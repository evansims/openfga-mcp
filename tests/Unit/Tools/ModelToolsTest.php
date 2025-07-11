<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Tools\ModelTools;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Models\Collections\{Conditions, TypeDefinitions};
use OpenFGA\Responses\{CreateAuthorizationModelResponseInterface, GetAuthorizationModelResponseInterface};
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->modelTools = new ModelTools($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_READONLY=');
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('createModel', function (): void {
    it('creates an authorization model successfully', function (): void {
        $dsl = 'model
  schema 1.1
type user
type document
  relations
    define reader: [user]';
        $storeId = 'store-123';
        $modelId = 'model-456';

        $mockModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockModel->shouldReceive('getTypeDefinitions')->andReturn(new TypeDefinitions);
        $mockModel->shouldReceive('getConditions')->andReturn(new Conditions);

        $mockDslPromise = Mockery::mock(SuccessInterface::class);
        $mockDslPromise->shouldReceive('failure')->andReturnSelf();
        $mockDslPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockModel) {
            $callback($mockModel);

            return true;
        }))->andReturnSelf();

        $mockCreateResponse = Mockery::mock(CreateAuthorizationModelResponseInterface::class);
        $mockCreateResponse->shouldReceive('getModel')->andReturn($modelId);

        $mockCreatePromise = Mockery::mock(SuccessInterface::class);
        $mockCreatePromise->shouldReceive('failure')->andReturnSelf();
        $mockCreatePromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockCreateResponse) {
            $callback($mockCreateResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockDslPromise);

        $this->client->shouldReceive('createAuthorizationModel')
            ->once()
            ->andReturn($mockCreatePromise);

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toContain('✅ Successfully created authorization model')
            ->and($result)->toContain($modelId);
    });

    it('handles DSL parsing failure', function (): void {
        $dsl = 'invalid dsl';
        $storeId = 'store-123';
        $errorMessage = 'Invalid DSL syntax';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toContain('❌ Failed to create authorization model')
            ->and($result)->toContain($errorMessage);
    });

    it('handles model creation failure after successful DSL parsing', function (): void {
        $dsl = 'model
  schema 1.1
type user';
        $storeId = 'store-123';
        $errorMessage = 'Network error';

        $mockModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockModel->shouldReceive('getTypeDefinitions')->andReturn(new TypeDefinitions);
        $mockModel->shouldReceive('getConditions')->andReturn(new Conditions);

        $mockDslPromise = Mockery::mock(SuccessInterface::class);
        $mockDslPromise->shouldReceive('failure')->andReturnSelf();
        $mockDslPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockModel) {
            $callback($mockModel);

            return true;
        }))->andReturnSelf();

        $mockCreatePromise = Mockery::mock(FailureInterface::class);
        $mockCreatePromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockCreatePromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockDslPromise);

        $this->client->shouldReceive('createAuthorizationModel')
            ->once()
            ->andReturn($mockCreatePromise);

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toContain('❌ Failed to create authorization model')
            ->and($result)->toContain($errorMessage);
    });

    it('handles null authorization model from DSL parsing', function (): void {
        $dsl = 'model
  schema 1.1
type user';
        $storeId = 'store-123';

        $mockDslPromise = Mockery::mock(SuccessInterface::class);
        $mockDslPromise->shouldReceive('failure')->andReturnSelf();
        $mockDslPromise->shouldReceive('success')->with(Mockery::on(function ($callback) {
            // Create a non-AuthorizationModelInterface object
            $callback(new stdClass);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockDslPromise);

        $this->client->shouldReceive('createAuthorizationModel')->never();

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toBe('❌ Failed to create authorization model!');
    });

    it('prevents model creation in read-only mode', function (): void {
        putenv('OPENFGA_MCP_API_READONLY=true');

        $this->client->shouldReceive('dsl')->never();
        $this->client->shouldReceive('createAuthorizationModel')->never();

        $result = $this->modelTools->createModel('dsl', 'store-123');

        expect($result)->toBe('❌ The MCP server is configured in read only mode. You cannot create authorization models in this mode.');
    });

    it('prevents model creation in non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('dsl')->never();
        $this->client->shouldReceive('createAuthorizationModel')->never();

        $result = $this->modelTools->createModel('dsl', 'different-store');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });
});

describe('getModel', function (): void {
    it('gets authorization model successfully', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';

        $mockAuthModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockAuthModel->shouldReceive('getId')->andReturn($modelId);

        $mockResponse = Mockery::mock(GetAuthorizationModelResponseInterface::class);
        $mockResponse->shouldReceive('getModel')->andReturn($mockAuthModel);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModel($storeId, $modelId);

        expect($result)->toContain('✅ Found authorization model')
            ->and($result)->toContain($modelId);
    });

    it('handles model not found', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';

        $mockResponse = Mockery::mock(GetAuthorizationModelResponseInterface::class);
        $mockResponse->shouldReceive('getModel')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModel($storeId, $modelId);

        expect($result)->toBe('❌ Authorization model not found!');
    });

    it('handles get model failure', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModel($storeId, $modelId);

        expect($result)->toContain('❌ Failed to get authorization model')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents getting model from non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('getAuthorizationModel')->never();

        $result = $this->modelTools->getModel('different-store', 'model-123');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });

    it('prevents getting non-restricted model', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_MODEL=allowed-model');

        $this->client->shouldReceive('getAuthorizationModel')->never();

        $result = $this->modelTools->getModel('store-123', 'different-model');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query authorization models other than allowed-model in this mode.');
    });
});

describe('getModelDsl', function (): void {
    it('gets model DSL successfully', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';
        $dsl = 'model
  schema 1.1
type user';

        $mockAuthModel = Mockery::mock(AuthorizationModelInterface::class);
        $mockAuthModel->shouldReceive('dsl')->andReturn($dsl);

        $mockResponse = Mockery::mock(GetAuthorizationModelResponseInterface::class);
        $mockResponse->shouldReceive('getModel')->andReturn($mockAuthModel);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModelDsl($storeId, $modelId);

        expect($result)->toBe($dsl);
    });

    it('handles model DSL not found', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';

        $mockResponse = Mockery::mock(GetAuthorizationModelResponseInterface::class);
        $mockResponse->shouldReceive('getModel')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) use ($mockResponse) {
            $callback($mockResponse);

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModelDsl($storeId, $modelId);

        expect($result)->toBe('❌ Authorization model not found!');
    });

    it('handles get model DSL failure', function (): void {
        $storeId = 'store-123';
        $modelId = 'model-456';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('getAuthorizationModel')
            ->with($storeId, $modelId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->getModelDsl($storeId, $modelId);

        expect($result)->toContain('❌ Failed to get authorization model')
            ->and($result)->toContain($errorMessage);
    });
});

describe('listModels', function (): void {
    it('handles list models failure', function (): void {
        $storeId = 'store-123';
        $errorMessage = 'Network error';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('listAuthorizationModels')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->listModels($storeId);

        expect($result)->toContain('❌ Failed to list authorization models')
            ->and($result)->toContain($errorMessage);
    });

    it('prevents listing models from non-restricted store', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $this->client->shouldReceive('listAuthorizationModels')->never();

        $result = $this->modelTools->listModels('different-store');

        expect($result)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than allowed-store in this mode.');
    });
});

describe('verifyModel', function (): void {
    it('verifies valid DSL successfully', function (): void {
        $dsl = 'model
  schema 1.1
type user';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->with(Mockery::on(function ($callback) {
            $callback();

            return true;
        }))->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->verifyModel($dsl);

        expect($result)->toBe('✅ Successfully verified! This DSL appears to represent a valid authorization model.');
    });

    it('handles invalid DSL', function (): void {
        $dsl = 'invalid dsl';
        $errorMessage = 'Invalid syntax';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->with(Mockery::on(function ($callback) use ($errorMessage) {
            $callback(new Exception($errorMessage));

            return true;
        }))->andReturnSelf();
        $mockPromise->shouldReceive('success')->andReturnSelf();

        $this->client->shouldReceive('dsl')
            ->with($dsl)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->modelTools->verifyModel($dsl);

        expect($result)->toContain('❌ Failed to verify authorization model')
            ->and($result)->toContain($errorMessage);
    });
});
