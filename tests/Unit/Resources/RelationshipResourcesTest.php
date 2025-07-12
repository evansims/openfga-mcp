<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Resources\RelationshipResources;
use OpenFGA\Models\Collections\TuplesInterface;
use OpenFGA\Models\{TupleInterface, TupleKeyInterface};
use OpenFGA\Responses\{CheckResponseInterface, ExpandResponseInterface, ReadTuplesResponseInterface};
use OpenFGA\Results\SuccessInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->relationshipResources = new RelationshipResources($this->client);
});

afterEach(function (): void {
    Mockery::close();
});

describe('listUsers resource', function (): void {
    it('returns unique users list', function (): void {
        $storeId = 'test-store-id';

        // Create mock tuples with proper structure
        $mockTuples = [];

        // Tuple 1: user:alice
        $mockKey1 = Mockery::mock(TupleKeyInterface::class);
        $mockKey1->shouldReceive('getUser')->andReturn('user:alice');
        $mockTuple1 = Mockery::mock(TupleInterface::class);
        $mockTuple1->shouldReceive('getKey')->andReturn($mockKey1);
        $mockTuples[] = $mockTuple1;

        // Tuple 2: user:bob
        $mockKey2 = Mockery::mock(TupleKeyInterface::class);
        $mockKey2->shouldReceive('getUser')->andReturn('user:bob');
        $mockTuple2 = Mockery::mock(TupleInterface::class);
        $mockTuple2->shouldReceive('getKey')->andReturn($mockKey2);
        $mockTuples[] = $mockTuple2;

        // Tuple 3: user:alice (duplicate)
        $mockKey3 = Mockery::mock(TupleKeyInterface::class);
        $mockKey3->shouldReceive('getUser')->andReturn('user:alice');
        $mockTuple3 = Mockery::mock(TupleInterface::class);
        $mockTuple3->shouldReceive('getKey')->andReturn($mockKey3);
        $mockTuples[] = $mockTuple3;

        // Tuple 4: group:admins
        $mockKey4 = Mockery::mock(TupleKeyInterface::class);
        $mockKey4->shouldReceive('getUser')->andReturn('group:admins');
        $mockTuple4 = Mockery::mock(TupleInterface::class);
        $mockTuple4->shouldReceive('getKey')->andReturn($mockKey4);
        $mockTuples[] = $mockTuple4;

        // Mock tuples collection
        $mockTuplesCollection = Mockery::mock(TuplesInterface::class);
        $mockTuplesCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator($mockTuples));

        $mockResponse = Mockery::mock(ReadTuplesResponseInterface::class);
        $mockResponse->shouldReceive('getTuples')->andReturn($mockTuplesCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('readTuples')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['users'])->toHaveCount(3) // Only unique users
            ->and($result['count'])->toBe(3)
            ->and($result['users'][0])->toBe(['user' => 'user:alice', 'type' => 'user', 'id' => 'alice'])
            ->and($result['users'][1])->toBe(['user' => 'user:bob', 'type' => 'user', 'id' => 'bob'])
            ->and($result['users'][2])->toBe(['user' => 'group:admins', 'type' => 'group', 'id' => 'admins']);
    });

    it('handles empty tuples', function (): void {
        $storeId = 'test-store-id';

        // Mock empty tuples collection
        $mockTuplesCollection = Mockery::mock(TuplesInterface::class);
        $mockTuplesCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));

        $mockResponse = Mockery::mock(ReadTuplesResponseInterface::class);
        $mockResponse->shouldReceive('getTuples')->andReturn($mockTuplesCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('readTuples')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['users'])->toBeEmpty()
            ->and($result['count'])->toBe(0);
    });
});

describe('listObjects resource', function (): void {
    it('returns unique objects list', function (): void {
        $storeId = 'test-store-id';

        $mockTuples = [
            (object) ['user' => 'user:alice', 'relation' => 'reader', 'object' => 'document:1'],
            (object) ['user' => 'user:bob', 'relation' => 'writer', 'object' => 'document:2'],
            (object) ['user' => 'user:charlie', 'relation' => 'reader', 'object' => 'document:1'], // Duplicate object
            (object) ['user' => 'user:alice', 'relation' => 'member', 'object' => 'folder:root'],
        ];

        // Mock tuples collection
        $mockTuplesCollection = Mockery::mock(TuplesInterface::class);
        $mockTuplesCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator($mockTuples));

        $mockResponse = Mockery::mock(ReadTuplesResponseInterface::class);
        $mockResponse->shouldReceive('getTuples')->andReturn($mockTuplesCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('readTuples')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->listObjects($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['objects'])->toHaveCount(3) // Only unique objects
            ->and($result['count'])->toBe(3)
            ->and($result['objects'][0])->toBe(['object' => 'document:1', 'type' => 'document', 'id' => '1'])
            ->and($result['objects'][1])->toBe(['object' => 'document:2', 'type' => 'document', 'id' => '2'])
            ->and($result['objects'][2])->toBe(['object' => 'folder:root', 'type' => 'folder', 'id' => 'root']);
    });
});

describe('listRelationships resource', function (): void {
    it('returns all relationships', function (): void {
        $storeId = 'test-store-id';

        $mockTuples = [
            (object) ['user' => 'user:alice', 'relation' => 'reader', 'object' => 'doc:1', 'timestamp' => '2024-01-01T00:00:00Z'],
            (object) ['user' => 'user:bob', 'relation' => 'writer', 'object' => 'doc:2'],
        ];

        // Mock tuples collection
        $mockTuplesCollection = Mockery::mock(TuplesInterface::class);
        $mockTuplesCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator($mockTuples));

        $mockResponse = Mockery::mock(ReadTuplesResponseInterface::class);
        $mockResponse->shouldReceive('getTuples')->andReturn($mockTuplesCollection);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('readTuples')
            ->with($storeId)
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->listRelationships($storeId);

        expect($result)->toBeArray()
            ->and($result['relationships'])->toHaveCount(2)
            ->and($result['count'])->toBe(2)
            ->and($result['relationships'][0]['timestamp'])->toBe('2024-01-01T00:00:00Z')
            ->and($result['relationships'][1])->not->toHaveKey('timestamp');
    });
});

describe('checkPermission resource template', function (): void {
    it('returns permission check result', function (): void {
        $storeId = 'test-store-id';
        $user = 'user:alice';
        $relation = 'reader';
        $object = 'document:budget';

        $mockResponse = Mockery::mock(CheckResponseInterface::class);
        $mockResponse->shouldReceive('allowed')->andReturn(true);
        $mockResponse->shouldReceive('resolution')->andReturn('direct');

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('check')
            ->with($storeId, 'latest', Mockery::type('OpenFGA\Models\TupleKey'))
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->checkPermission($storeId, $user, $relation, $object);

        expect($result)->toBeArray()
            ->and($result['allowed'])->toBe(true)
            ->and($result['user'])->toBe($user)
            ->and($result['relation'])->toBe($relation)
            ->and($result['object'])->toBe($object)
            ->and($result['resolution'])->toBe('direct');
    });

    it('handles permission denied', function (): void {
        $storeId = 'test-store-id';
        $user = 'user:alice';
        $relation = 'writer';
        $object = 'document:budget';

        $mockResponse = Mockery::mock(CheckResponseInterface::class);
        $mockResponse->shouldReceive('allowed')->andReturn(false);
        $mockResponse->shouldReceive('resolution')->andReturn(null);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('check')
            ->with($storeId, 'latest', Mockery::type('OpenFGA\Models\TupleKey'))
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->checkPermission($storeId, $user, $relation, $object);

        expect($result)->toBeArray()
            ->and($result['allowed'])->toBe(false);
    });
});

describe('expandRelationships resource template', function (): void {
    it('extracts users from simple tree', function (): void {
        $storeId = 'test-store-id';
        $object = 'document:budget';
        $relation = 'reader';

        $mockTree = (object) [
            'leaf' => (object) [
                'users' => ['user:alice', 'user:bob'],
            ],
        ];

        $mockResponse = Mockery::mock(ExpandResponseInterface::class);
        $mockResponse->shouldReceive('tree')->andReturn($mockTree);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('expand')
            ->with($storeId, Mockery::type('OpenFGA\Models\TupleKey'), 'latest')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->expandRelationships($storeId, $object, $relation);

        expect($result)->toBeArray()
            ->and($result['object'])->toBe($object)
            ->and($result['relation'])->toBe($relation)
            ->and($result['users'])->toContain('user:alice')
            ->and($result['users'])->toContain('user:bob')
            ->and($result['count'])->toBe(2);
    });

    it('extracts users from complex union tree', function (): void {
        $storeId = 'test-store-id';
        $object = 'document:budget';
        $relation = 'reader';

        $mockTree = (object) [
            'union' => (object) [
                'nodes' => [
                    (object) [
                        'leaf' => (object) [
                            'users' => ['user:alice'],
                        ],
                    ],
                    (object) [
                        'leaf' => (object) [
                            'users' => ['user:bob', 'user:alice'], // Duplicate should be removed
                        ],
                    ],
                ],
            ],
        ];

        $mockResponse = Mockery::mock(ExpandResponseInterface::class);
        $mockResponse->shouldReceive('tree')->andReturn($mockTree);

        $mockPromise = Mockery::mock(SuccessInterface::class);
        $mockPromise->shouldReceive('failure')->andReturnSelf();
        $mockPromise->shouldReceive('success')->withArgs(function ($callback) use ($mockResponse): bool {
            $callback($mockResponse);

            return true;
        })->andReturnSelf();

        $this->client->shouldReceive('expand')
            ->with($storeId, Mockery::type('OpenFGA\Models\TupleKey'), 'latest')
            ->once()
            ->andReturn($mockPromise);

        $result = $this->relationshipResources->expandRelationships($storeId, $object, $relation);

        expect($result)->toBeArray()
            ->and($result['users'])->toHaveCount(2) // Duplicates removed
            ->and($result['users'])->toContain('user:alice')
            ->and($result['users'])->toContain('user:bob');
    });
});
