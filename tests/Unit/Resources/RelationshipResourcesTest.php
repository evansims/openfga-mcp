<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\RelationshipResources;
use OpenFGA\Models\TupleKey;
use OpenFGA\Results\{Failure, FailureInterface, SuccessInterface};

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->client = Mockery::mock(ClientInterface::class);
    $this->relationshipResources = new RelationshipResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
});

describe('listUsers resource', function (): void {
    it('handles readTuples failure', function (): void {
        $storeId = 'test-store-id';
        $error = new Exception('Failed to read tuples');

        $this->client->shouldReceive('readTuples')
            ->once()
            ->andReturn(new Failure($error));

        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('Failed to read tuples');
    });
});

describe('listObjects resource', function (): void {
    it('handles readTuples failure', function (): void {
        $storeId = 'test-store-id';
        $error = new Exception('Failed to read tuples');

        $this->client->shouldReceive('readTuples')
            ->once()
            ->andReturn(new Failure($error));

        $result = $this->relationshipResources->listObjects($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('Failed to read tuples');
    });
});

describe('listRelationships resource', function (): void {
    it('handles readTuples failure', function (): void {
        $storeId = 'test-store-id';
        $error = new Exception('Failed to read tuples');

        $this->client->shouldReceive('readTuples')
            ->once()
            ->andReturn(new Failure($error));

        $result = $this->relationshipResources->listRelationships($storeId);

        expect($result)->toBeArray()
            ->and($result['error'])->toContain('Failed to read tuples');
    });
});

describe('checkPermission resource template', function (): void {
    it('calls check on the client', function (): void {
        $storeId = 'test-store-id';
        $user = 'user:alice';
        $relation = 'writer';
        $object = 'document:budget';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('check')
            ->with($storeId, 'latest', Mockery::type('OpenFGA\Models\TupleKey'))
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->checkPermission($storeId, $user, $relation, $object);

        expect($result)->toBeArray();
    });

    it('handles check errors', function (): void {
        $storeId = 'test-store-id';
        $user = 'user:alice';
        $relation = 'writer';
        $object = 'document:budget';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('check')
            ->with($storeId, 'latest', Mockery::type('OpenFGA\Models\TupleKey'))
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->checkPermission($storeId, $user, $relation, $object);

        expect($result)->toBeArray();
    });
});

describe('expandRelationships resource template', function (): void {
    it('calls expand on the client', function (): void {
        $storeId = 'test-store-id';
        $object = 'document:budget';
        $relation = 'reader';

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('expand')
            ->withArgs(fn ($store, $tuple) => $store === $storeId && $tuple instanceof TupleKey)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->expandRelationships($storeId, $object, $relation);

        expect($result)->toBeArray();
    });

    it('handles expand errors', function (): void {
        $storeId = 'test-store-id';
        $object = 'document:budget';
        $relation = 'reader';

        $mockPromise = Mockery::mock(FailureInterface::class);
        $mockPromise->shouldReceive('failure')->once()->andReturnSelf();
        $mockPromise->shouldReceive('success')->once()->andReturnSelf();

        $this->client->shouldReceive('expand')
            ->withArgs(fn ($store, $tuple) => $store === $storeId && $tuple instanceof TupleKey)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->expandRelationships($storeId, $object, $relation);

        expect($result)->toBeArray();
    });
});

describe('offline mode behavior', function (): void {
    it('prevents checkPermission in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('check')->never();

        $result = $this->relationshipResources->checkPermission('test-store-id', 'user:123', 'reader', 'document:456');

        expect($result)->toBe(['error' => '❌ Checking permission requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents expandRelationships in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('expand')->never();

        $result = $this->relationshipResources->expandRelationships('test-store-id', 'document:456', 'reader');

        expect($result)->toBe(['error' => '❌ Expanding relationships requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents listObjects in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('readTuples')->never();

        $result = $this->relationshipResources->listObjects('test-store-id');

        expect($result)->toBe(['error' => '❌ Listing objects requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents listRelationships in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('readTuples')->never();

        $result = $this->relationshipResources->listRelationships('test-store-id');

        expect($result)->toBe(['error' => '❌ Listing relationships requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });

    it('prevents listUsers in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear the URL to simulate offline mode

        $this->client->shouldReceive('readTuples')->never();

        $result = $this->relationshipResources->listUsers('test-store-id');

        expect($result)->toBe(['error' => '❌ Listing users requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.']);
    });
});
