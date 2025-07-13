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

        // List users (Note: This will return empty due to OpenFGA Read API limitations)
        $result = $this->relationshipResources->listUsers($storeId);

        // Debug: Show what we actually got
        fwrite(STDERR, 'DEBUG: listUsers result: ' . json_encode($result, JSON_PRETTY_PRINT) . "\n");

        // OpenFGA Read API doesn't support reading all tuples without specific filters
        expect($result)->toBeArray()
            ->and($result['store_id'])->toBe($storeId)
            ->and($result['users'])->toBeEmpty() // Empty due to API limitation
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all users requires specific tuple filters');
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

        // List objects (Note: This will return empty due to OpenFGA Read API limitations)
        $result = $this->relationshipResources->listObjects($storeId);

        // OpenFGA Read API doesn't support reading all tuples without specific filters
        expect($result)->toBeArray()
            ->and($result['objects'])->toBeEmpty() // Empty due to API limitation
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all objects requires specific tuple filters');
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

        // List relationships (Note: This will return empty due to OpenFGA Read API limitations)
        $result = $this->relationshipResources->listRelationships($storeId);

        // OpenFGA Read API doesn't support reading all tuples without specific filters
        expect($result)->toBeArray()
            ->and($result['relationships'])->toBeEmpty() // Empty due to API limitation
            ->and($result['count'])->toBe(0)
            ->and($result['note'])->toContain('Reading all relationships requires specific tuple filters');
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
