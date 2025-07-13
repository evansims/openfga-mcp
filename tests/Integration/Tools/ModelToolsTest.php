<?php

declare(strict_types=1);

use OpenFGA\MCP\Tools\ModelTools;

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->modelTools = new ModelTools($this->client);
});

describe('ModelTools Integration', function (): void {
    it('can create an authorization model', function (): void {
        $storeId = setupTestStore();
        $dsl = 'model
  schema 1.1

type user

type project
  relations
    define owner: [user]
    define member: [user] or owner
    define viewer: [user] or member';

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toContain('✅ Successfully created authorization model')
            ->and($result)->toContain('Model ID:');

        // Extract model ID for verification
        preg_match('/Model ID: ([a-zA-Z0-9-]+)/', $result, $matches);
        $modelId = $matches[1] ?? null;

        expect($modelId)->not->toBeNull();
    });

    it('can get an authorization model', function (): void {
        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel();

        $result = $this->modelTools->getModel($storeId, $modelId);

        expect($result)->toContain('✅ Found authorization model')
            ->and($result)->toContain($modelId);
    });

    it('can get model DSL', function (): void {
        $customDsl = 'model
  schema 1.1

type organization

type team
  relations
    define parent: [organization]
    define member: [user]

type user';

        ['store' => $storeId, 'model' => $modelId] = setupTestStoreWithModel($customDsl);

        $result = $this->modelTools->getModelDsl($storeId, $modelId);

        expect($result)->toBeString()
            ->and($result)->toContain('type organization')
            ->and($result)->toContain('type team')
            ->and($result)->toContain('define parent: [organization]')
            ->and($result)->toContain('define member: [user]');
    });

    it('can list authorization models', function (): void {
        $storeId = setupTestStore();

        // Create multiple models
        $modelId1 = createTestModel($storeId);
        $modelId2 = createTestModel($storeId);

        $result = $this->modelTools->listModels($storeId);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(2)
            ->and(array_column($result, 'id'))->toContain($modelId1)
            ->and(array_column($result, 'id'))->toContain($modelId2);
    });

    it('can verify valid DSL', function (): void {
        $validDsl = 'model
  schema 1.1

type user

type folder
  relations
    define parent: [folder]
    define owner: [user]
    define editor: [user] or owner
    define viewer: [user] or editor or owner from parent';

        $result = $this->modelTools->verifyModel($validDsl);

        expect($result)->toBe('✅ Successfully verified! This DSL appears to represent a valid authorization model.');
    });

    it('detects invalid DSL', function (): void {
        // Use actually invalid DSL syntax rather than undefined types
        $invalidDsl = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user
    define writer: [user]';  // Missing closing bracket

        $result = $this->modelTools->verifyModel($invalidDsl);

        expect($result)->toContain('❌ Failed to verify authorization model');
    });

    it('handles non-existent model gracefully', function (): void {
        $storeId = setupTestStore();
        $fakeModelId = '00000000-0000-0000-0000-000000000000';

        $result = $this->modelTools->getModel($storeId, $fakeModelId);

        expect($result)->toContain('Failed to get authorization model');
    });

    it('respects read-only mode', function (): void {
        $storeId = setupTestStore();
        putenv('OPENFGA_MCP_API_READONLY=true');

        $dsl = 'model
  schema 1.1
type user';

        $result = $this->modelTools->createModel($dsl, $storeId);

        expect($result)->toBe('❌ The MCP server is configured in read only mode. You cannot create authorization models in this mode.');

        putenv('OPENFGA_MCP_API_READONLY');
        unset($_ENV['OPENFGA_MCP_API_READONLY']);
    });

    it('respects restricted mode for model access', function (): void {
        ['store' => $storeId, 'model' => $allowedModelId] = setupTestStoreWithModel();
        $restrictedModelId = createTestModel($storeId);

        $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
        $_ENV['OPENFGA_MCP_API_STORE'] = $storeId;
        $_ENV['OPENFGA_MCP_API_MODEL'] = $allowedModelId;

        // Should allow access to the allowed model
        $allowedResult = $this->modelTools->getModel($storeId, $allowedModelId);
        expect($allowedResult)->toContain('✅ Found authorization model');

        // Should block access to other models
        $restrictedResult = $this->modelTools->getModel($storeId, $restrictedModelId);
        expect($restrictedResult)->toBe('❌ The MCP server is configured in restricted mode. You cannot query authorization models other than ' . $allowedModelId . ' in this mode.');

        putenv('OPENFGA_MCP_API_RESTRICT');
        putenv('OPENFGA_MCP_API_STORE');
        putenv('OPENFGA_MCP_API_MODEL');
        unset($_ENV['OPENFGA_MCP_API_RESTRICT'], $_ENV['OPENFGA_MCP_API_STORE'], $_ENV['OPENFGA_MCP_API_MODEL']);
    });

    it('creates complex models with inheritance', function (): void {
        $storeId = setupTestStore();
        $dslWithInheritance = 'model
  schema 1.1

type user

type group
  relations
    define member: [user, group#member]

type document
  relations
    define owner: [user, group#member]
    define editor: [user, group#member] or owner
    define viewer: [user, group#member] or editor';

        $result = $this->modelTools->createModel($dslWithInheritance, $storeId);

        expect($result)->toContain('✅ Successfully created authorization model');

        // Verify we can retrieve the model
        preg_match('/Model ID: ([a-zA-Z0-9-]+)/', $result, $matches);
        $modelId = $matches[1] ?? null;

        $dslResult = $this->modelTools->getModelDsl($storeId, $modelId);
        expect($dslResult)->toContain('group#member');
    });
});
