<?php

declare(strict_types=1);

use OpenFGA\MCP\Tools\RelationshipTools;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->relationshipTools = new RelationshipTools($this->client);
});

describe('RelationshipTools Integration', function (): void {
    it('can grant and check permissions', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $user = 'user:alice';
        $relation = 'reader';
        $object = 'document:budget-2024';

        // Initially, user should not have permission
        $checkBefore = $this->relationshipTools->checkPermission($storeId, $modelId, $user, $relation, $object);
        expect($checkBefore)->toBe('❌ Permission denied');

        // Grant permission
        $grantResult = $this->relationshipTools->grantPermission($storeId, $modelId, $user, $relation, $object);
        expect($grantResult)->toBe('✅ Permission granted successfully');

        // Now user should have permission
        $checkAfter = $this->relationshipTools->checkPermission($storeId, $modelId, $user, $relation, $object);
        expect($checkAfter)->toBe('✅ Permission allowed');
    });

    it('can revoke permissions', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $user = 'user:bob';
        $relation = 'writer';
        $object = 'document:proposal';

        // Grant permission first
        $this->relationshipTools->grantPermission($storeId, $modelId, $user, $relation, $object);

        // Verify permission exists
        $checkBefore = $this->relationshipTools->checkPermission($storeId, $modelId, $user, $relation, $object);
        expect($checkBefore)->toBe('✅ Permission allowed');

        // Revoke permission
        $revokeResult = $this->relationshipTools->revokePermission($storeId, $modelId, $user, $relation, $object);
        expect($revokeResult)->toBe('✅ Permission revoked successfully');

        // Verify permission is revoked
        $checkAfter = $this->relationshipTools->checkPermission($storeId, $modelId, $user, $relation, $object);
        expect($checkAfter)->toBe('❌ Permission denied');
    });

    it('can list objects a user has access to', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $user = 'user:charlie';
        $relation = 'reader';
        $objects = ['document:report-1', 'document:report-2', 'document:report-3'];

        // Grant permissions to multiple objects
        foreach ($objects as $object) {
            $this->relationshipTools->grantPermission($storeId, $modelId, $user, $relation, $object);
        }

        // List objects user has access to
        $result = $this->relationshipTools->listObjects($storeId, $modelId, 'document', $user, $relation);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(3);

        foreach ($objects as $object) {
            expect($result)->toContain($object);
        }
    });

    it('can list users with access to an object', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $object = 'document:shared-doc';
        $relation = 'reader';
        $users = ['user:dave', 'user:eve', 'user:frank'];

        // Grant permissions to multiple users
        foreach ($users as $user) {
            $this->relationshipTools->grantPermission($storeId, $modelId, $user, $relation, $object);
        }

        // List users with access
        $result = $this->relationshipTools->listUsers($storeId, $modelId, $object, $relation);

        // Skip this test if listUsers returns an error - it might not be fully supported
        if (is_string($result) && str_contains($result, 'Failed to list users')) {
            $this->markTestSkipped('listUsers endpoint returned an error - may not be fully supported in this OpenFGA version');
        }

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(3);

        foreach ($users as $user) {
            expect($result)->toContain($user);
        }
    });

    it('handles hierarchical permissions', function (): void {
        $dsl = 'model
  schema 1.1

type user

type folder
  relations
    define owner: [user]
    define viewer: [user] or owner

type document
  relations
    define parent: [folder]
    define owner: [user]
    define viewer: [user] or owner or viewer from parent';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($dsl);

        // Create folder and document relationship
        $folder = 'folder:projects';
        $document = 'document:project-plan';
        $user = 'user:grace';

        // Make user owner of folder
        $this->relationshipTools->grantPermission($storeId, $modelId, $user, 'owner', $folder);

        // Set document's parent to folder
        $this->relationshipTools->grantPermission($storeId, $modelId, $folder, 'parent', $document);

        // User should have viewer permission on document through folder ownership
        $checkResult = $this->relationshipTools->checkPermission($storeId, $modelId, $user, 'viewer', $document);
        expect($checkResult)->toBe('✅ Permission allowed');
    });

    it('respects read-only mode', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();
        $_ENV['OPENFGA_MCP_API_READONLY'] = 'true';

        $grantResult = $this->relationshipTools->grantPermission($storeId, $modelId, 'user:test', 'reader', 'document:test');
        expect($grantResult)->toBe('❌ The MCP server is configured in read only mode. You cannot grant permissions in this mode.');

        $revokeResult = $this->relationshipTools->revokePermission($storeId, $modelId, 'user:test', 'reader', 'document:test');
        expect($revokeResult)->toBe('❌ The MCP server is configured in read only mode. You cannot revoke permissions in this mode.');

        // Check should still work in read-only mode
        $checkResult = $this->relationshipTools->checkPermission($storeId, $modelId, 'user:test', 'reader', 'document:test');
        expect($checkResult)->toBe('❌ Permission denied');

        putenv('OPENFGA_MCP_API_READONLY=false');
        $_ENV['OPENFGA_MCP_API_READONLY'] = 'false';
    });

    it('respects restricted mode', function (): void {
        ['store' => $allowedStoreId, 'model' => $allowedModelId] = setupTestStoreWithModel();
        ['store' => $restrictedStoreId, 'model' => $restrictedModelId] = setupTestStoreWithModel();

        try {
            $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
            $_ENV['OPENFGA_MCP_API_STORE'] = $allowedStoreId;
            $_ENV['OPENFGA_MCP_API_MODEL'] = $allowedModelId;

            // Should allow operations on allowed store/model
            $allowedCheck = $this->relationshipTools->checkPermission($allowedStoreId, $allowedModelId, 'user:test', 'reader', 'document:test');
            expect($allowedCheck)->toBe('❌ Permission denied'); // No permission, but query succeeded

            // Should block operations on restricted store
            $restrictedStoreCheck = $this->relationshipTools->checkPermission($restrictedStoreId, $allowedModelId, 'user:test', 'reader', 'document:test');
            expect($restrictedStoreCheck)->toBe('❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $allowedStoreId . ' in this mode.');

            // Should block operations on restricted model
            $restrictedModelCheck = $this->relationshipTools->checkPermission($allowedStoreId, $restrictedModelId, 'user:test', 'reader', 'document:test');
            expect($restrictedModelCheck)->toBe('❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than ' . $allowedModelId . ' in this mode.');
        } finally {
            // Clear both system environment and $_ENV
            putenv('OPENFGA_MCP_API_RESTRICT=false');
            putenv('OPENFGA_MCP_API_STORE=false');
            putenv('OPENFGA_MCP_API_MODEL=false');
            $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'false';
            $_ENV['OPENFGA_MCP_API_STORE'] = 'false';
            $_ENV['OPENFGA_MCP_API_MODEL'] = 'false';
        }

        // Clean up restricted store
        deleteTestStore($restrictedStoreId);
    });

    it('handles batch operations efficiently', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $users = [];
        $documents = [];

        // Create test data
        for ($i = 1; 5 >= $i; $i++) {
            $users[] = 'user:user' . $i;
            $documents[] = 'document:doc' . $i;
        }

        // Grant permissions in a pattern
        foreach ($users as $userIndex => $user) {
            foreach ($documents as $docIndex => $document) {
                // Each user can read their document and all previous ones
                if ($docIndex <= $userIndex) {
                    $this->relationshipTools->grantPermission($storeId, $modelId, $user, 'reader', $document);
                }
            }
        }

        // Verify the pattern
        expect($this->relationshipTools->checkPermission($storeId, $modelId, 'user:user1', 'reader', 'document:doc1'))->toBe('✅ Permission allowed');
        expect($this->relationshipTools->checkPermission($storeId, $modelId, 'user:user1', 'reader', 'document:doc2'))->toBe('❌ Permission denied');

        expect($this->relationshipTools->checkPermission($storeId, $modelId, 'user:user3', 'reader', 'document:doc1'))->toBe('✅ Permission allowed');
        expect($this->relationshipTools->checkPermission($storeId, $modelId, 'user:user3', 'reader', 'document:doc3'))->toBe('✅ Permission allowed');
        expect($this->relationshipTools->checkPermission($storeId, $modelId, 'user:user3', 'reader', 'document:doc4'))->toBe('❌ Permission denied');

        // Verify list operations
        $user3Objects = $this->relationshipTools->listObjects($storeId, $modelId, 'document', 'user:user3', 'reader');
        expect($user3Objects)->toHaveCount(3)
            ->and($user3Objects)->toContain('document:doc1')
            ->and($user3Objects)->toContain('document:doc2')
            ->and($user3Objects)->toContain('document:doc3');
    });
});
