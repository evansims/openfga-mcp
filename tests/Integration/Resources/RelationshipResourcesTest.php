<?php

declare(strict_types=1);

use OpenFGA\MCP\Resources\RelationshipResources;
use OpenFGA\MCP\Tools\RelationshipTools;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->relationshipResources = new RelationshipResources($this->client);
    $this->relationshipTools = new RelationshipTools($this->client); // For setting up test data
});

describe('RelationshipResources Integration', function (): void {
    it('lists users from relationships', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Add some relationships
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:1');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'writer', 'document:1');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'writer', 'document:2');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:charlie', 'reader', 'document:3');

        // List users
        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['users'])->toHaveCount(3) // alice, bob, charlie (unique)
            ->and($result['count'])->toBe(3);

        $userIds = array_column($result['users'], 'id');
        expect($userIds)->toContain('alice')
            ->and($userIds)->toContain('bob')
            ->and($userIds)->toContain('charlie');
    });

    it('lists objects from relationships', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Add relationships
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:report');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'reader', 'document:budget');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:charlie', 'reader', 'document:report');

        // List objects
        $result = $this->relationshipResources->listObjects($storeId);

        expect($result)->toBeArray()
            ->and($result['objects'])->toHaveCount(2) // report, budget (unique)
            ->and($result['count'])->toBe(2);

        $objectIds = array_column($result['objects'], 'id');
        expect($objectIds)->toContain('report')
            ->and($objectIds)->toContain('budget');
    });

    it('lists all relationships', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Add relationships
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:1');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'reader', 'document:2');

        // List relationships
        $result = $this->relationshipResources->listRelationships($storeId);

        expect($result)->toBeArray()
            ->and($result['relationships'])->toHaveCount(2)
            ->and($result['count'])->toBe(2);

        // Check relationship structure
        foreach ($result['relationships'] as $rel) {
            expect($rel)->toHaveKey('user')
                ->and($rel)->toHaveKey('relation')
                ->and($rel)->toHaveKey('object')
                ->and($rel['relation'])->toBe('reader');
        }
    });

    it('checks permissions using resource template', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Add relationships
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:budget');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'writer', 'document:budget');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'reader', 'document:budget');

        // Check alice can write
        $result = $this->relationshipResources->checkPermission($storeId, 'user:alice', 'writer', 'document:budget');

        expect($result)->toBeArray()
            ->and($result['allowed'])->toBe(true)
            ->and($result['user'])->toBe('user:alice')
            ->and($result['relation'])->toBe('writer')
            ->and($result['object'])->toBe('document:budget');

        // Check bob cannot write
        $result = $this->relationshipResources->checkPermission($storeId, 'user:bob', 'writer', 'document:budget');

        expect($result)->toBeArray()
            ->and($result['allowed'])->toBe(false);
    });

    it('expands relationships using resource template', function (): void {
        // Create store with model
        $dsl = 'model
  schema 1.1

type user

type group
  relations
    define member: [user]

type document
  relations
    define reader: [user, group#member]';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Add relationships
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:report');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'member', 'group:engineering');
        $this->relationshipTools->grantPermission($storeId, $modelId, 'group:engineering#member', 'reader', 'document:report');

        // Expand readers of document
        $result = $this->relationshipResources->expandRelationships($storeId, 'document:report', 'reader');

        expect($result)->toBeArray()
            ->and($result['object'])->toBe('document:report')
            ->and($result['relation'])->toBe('reader')
            ->and($result['users'])->toContain('user:alice');

        // Note: The expansion may not include bob directly depending on how OpenFGA resolves the graph
        // but this tests the basic functionality
    });

    it('handles empty store gracefully', function (): void {
        $storeId = setupTestStore();

        // List users in empty store
        $result = $this->relationshipResources->listUsers($storeId);

        expect($result)->toBeArray()
            ->and($result['users'])->toBeEmpty()
            ->and($result['count'])->toBe(0);

        // List objects in empty store
        $result = $this->relationshipResources->listObjects($storeId);

        expect($result)->toBeArray()
            ->and($result['objects'])->toBeEmpty()
            ->and($result['count'])->toBe(0);
    });
});
