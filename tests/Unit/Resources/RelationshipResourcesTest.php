<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\RelationshipResources;
use OpenFGA\Models\TupleKey;
use OpenFGA\Results\{FailureInterface, SuccessInterface};

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->relationshipResources = new RelationshipResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('listUsers resource', function (): void {
    it('returns empty users with explanation note', function (): void {
        $storeId = 'test-store-id';

        // No client calls expected - method returns static response

        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['users'])->toBeArray()
            ->and($result['users'])->toBeEmpty()
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all users requires specific tuple filters');
    });
});

describe('listObjects resource', function (): void {
    it('returns empty objects with explanation note', function (): void {
        $storeId = 'test-store-id';

        // No client calls expected - method returns static response

        $result = $this->relationshipResources->listObjects($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['objects'])->toBeArray()
            ->and($result['objects'])->toBeEmpty()
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all objects requires specific tuple filters');
    });
});

describe('listRelationships resource', function (): void {
    it('returns empty relationships with explanation note', function (): void {
        $storeId = 'test-store-id';

        // No client calls expected - method returns static response

        $result = $this->relationshipResources->listRelationships($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['relationships'])->toBeArray()
            ->and($result['relationships'])->toBeEmpty()
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all relationships requires specific tuple filters');
    });
});

describe('checkPermission resource template', function (): void {
    it('calls check on the client', function (): void {
        $storeId = 'test-store-id';
        $user = 'user:alice';
        $relation = 'reader';
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

        // Without executing callbacks, result is empty
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
