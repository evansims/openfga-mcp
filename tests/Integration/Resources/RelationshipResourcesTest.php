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

        // Add some relationships and verify they succeed
        $grant1 = $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'reader', 'document:1');
        $grant2 = $this->relationshipTools->grantPermission($storeId, $modelId, 'user:bob', 'writer', 'document:1');
        $grant3 = $this->relationshipTools->grantPermission($storeId, $modelId, 'user:alice', 'writer', 'document:2');
        $grant4 = $this->relationshipTools->grantPermission($storeId, $modelId, 'user:charlie', 'reader', 'document:3');

        // Debug: Check that grants succeeded
        expect($grant1)->toContain('✅ Permission granted successfully');
        expect($grant2)->toContain('✅ Permission granted successfully');
        expect($grant3)->toContain('✅ Permission granted successfully');
        expect($grant4)->toContain('✅ Permission granted successfully');

        // Wait a moment for data consistency
        sleep(1); // 1 second delay to ensure writes are persisted

        // List users
        $result = $this->relationshipResources->listUsers($storeId);

        // Debug: Show what we actually got
        fwrite(STDERR, 'DEBUG: listUsers result: ' . json_encode($result, JSON_PRETTY_PRINT) . "\n");

        // Should return all unique users
        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['users'])->toBeArray()
            ->and($result['users'])->toContain('user:alice', 'user:bob', 'user:charlie')
            ->and($result['count'])->toBe(3);
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

        // Wait for data consistency
        sleep(1);

        // List objects
        $result = $this->relationshipResources->listObjects($storeId);

        // Should return all unique objects
        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['objects'])->toBeArray()
            ->and($result['objects'])->toContain('document:report', 'document:budget')
            ->and($result['count'])->toBe(2);
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

        // Wait for data consistency
        sleep(1);

        // List relationships
        $result = $this->relationshipResources->listRelationships($storeId);

        // Should return all relationships
        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['relationships'])->toBeArray()
            ->and($result['relationships'])->toHaveCount(2)
            ->and($result['relationships'][0]['user'])->toBe('user:alice')
            ->and($result['relationships'][0]['relation'])->toBe('reader')
            ->and($result['relationships'][0]['object'])->toBe('document:1')
            ->and($result['relationships'][1]['user'])->toBe('user:bob')
            ->and($result['relationships'][1]['relation'])->toBe('reader')
            ->and($result['relationships'][1]['object'])->toBe('document:2')
            ->and($result['count'])->toBe(2);
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

        // Wait for data consistency
        sleep(1);

        // Check alice can write
        $result = $this->relationshipResources->checkPermission($storeId, 'user:alice', 'writer', 'document:budget', $modelId);

        expect($result)->toBeArray()
            ->and($result['allowed'])->toBe(true)
            ->and($result['user'])->toBe('user:alice')
            ->and($result['relation'])->toBe('writer')
            ->and($result['object'])->toBe('document:budget');

        // Check bob cannot write
        $result = $this->relationshipResources->checkPermission($storeId, 'user:bob', 'writer', 'document:budget', $modelId);

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
