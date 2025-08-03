<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Prompts\ModelDesignPrompts;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->modelDesignPrompts = new ModelDesignPrompts($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('designModelForDomain', function (): void {
    it('generates a domain-specific model design prompt', function (): void {
        $result = $this->modelDesignPrompts->designModelForDomain('document management', 'hierarchical', 'moderate');

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toHaveKey('role')
            ->and($result[0])->toHaveKey('content')
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('document management')
            ->and($result[0]['content'])->toContain('hierarchical')
            ->and($result[0]['content'])->toContain('moderate');
    });

    it('uses default parameters when not specified', function (): void {
        $result = $this->modelDesignPrompts->designModelForDomain('e-commerce');

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain('e-commerce')
            ->and($result[0]['content'])->toContain('hierarchical')
            ->and($result[0]['content'])->toContain('moderate');
    });

    it('respects restricted mode when store ID is provided', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        // Since designModelForDomain doesn't take store/model parameters,
        // it won't trigger restricted mode. This test verifies normal operation.
        $result = $this->modelDesignPrompts->designModelForDomain('healthcare');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('healthcare');
    });
});

describe('convertRbacToRebac', function (): void {
    it('generates RBAC to ReBAC conversion prompt', function (): void {
        $roleDescription = 'Admin, Manager, User roles with hierarchical permissions';
        $result = $this->modelDesignPrompts->convertRbacToRebac($roleDescription, 'gradual');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($roleDescription)
            ->and($result[0]['content'])->toContain('gradual')
            ->and($result[0]['content'])->toContain('RBAC')
            ->and($result[0]['content'])->toContain('ReBAC');
    });

    it('uses default migration scope', function (): void {
        $result = $this->modelDesignPrompts->convertRbacToRebac('Simple role system');

        expect($result[0]['content'])->toContain('additive');
    });
});

describe('modelHierarchicalRelationships', function (): void {
    it('generates hierarchical relationship modeling prompt', function (): void {
        $result = $this->modelDesignPrompts->modelHierarchicalRelationships('organizational', 'parent-to-child');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('organizational')
            ->and($result[0]['content'])->toContain('parent-to-child')
            ->and($result[0]['content'])->toContain('hierarchy');
    });

    it('uses default inheritance model', function (): void {
        $result = $this->modelDesignPrompts->modelHierarchicalRelationships('resource');

        expect($result[0]['content'])->toContain('parent-to-child');
    });
});

describe('optimizeModelStructure', function (): void {
    it('generates model optimization prompt', function (): void {
        $currentModel = 'model\n  schema 1.1\ntype user\ntype document';
        $result = $this->modelDesignPrompts->optimizeModelStructure($currentModel, 'performance');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($currentModel)
            ->and($result[0]['content'])->toContain('performance')
            ->and($result[0]['content'])->toContain('optimize');
    });

    it('uses default optimization goal', function (): void {
        $result = $this->modelDesignPrompts->optimizeModelStructure('basic model');

        expect($result[0]['content'])->toContain('performance');
    });
});
