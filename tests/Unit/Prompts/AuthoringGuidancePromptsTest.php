<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Prompts\AuthoringGuidancePrompts;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->authoringPrompts = new AuthoringGuidancePrompts($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('guideModelAuthoring', function (): void {
    it('generates model authoring guidance prompt', function (): void {
        $result = $this->authoringPrompts->guideModelAuthoring('document management', 'relationships');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('document management')
            ->and($result[0]['content'])->toContain('relationships')
            ->and($result[0]['content'])->toContain('OpenFGA');
    });

    it('uses default parameters when not specified', function (): void {
        $result = $this->authoringPrompts->guideModelAuthoring();

        expect($result[0]['content'])->toContain('general')
            ->and($result[0]['content'])->toContain('comprehensive');
    });
});

describe('createModelStepByStep', function (): void {
    it('generates step-by-step model creation prompt', function (): void {
        $requirements = 'Multi-tenant SaaS with role-based access';
        $result = $this->authoringPrompts->createModelStepByStep($requirements, 'complex');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($requirements)
            ->and($result[0]['content'])->toContain('complex')
            ->and($result[0]['content'])->toContain('Step 1')
            ->and($result[0]['content'])->toContain('Step 2');
    });

    it('uses moderate complexity by default', function (): void {
        $result = $this->authoringPrompts->createModelStepByStep('Simple app');

        expect($result[0]['content'])->toContain('moderate');
    });
});

describe('designRelationshipPatterns', function (): void {
    it('generates relationship pattern design prompt', function (): void {
        $scenario = 'Hierarchical document management with inheritance';
        $result = $this->authoringPrompts->designRelationshipPatterns($scenario, 'indirect');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($scenario)
            ->and($result[0]['content'])->toContain('indirect')
            ->and($result[0]['content'])->toContain('Pattern Selection');
    });

    it('uses mixed pattern type by default', function (): void {
        $result = $this->authoringPrompts->designRelationshipPatterns('Basic scenario');

        expect($result[0]['content'])->toContain('mixed');
    });
});

describe('implementCustomRoles', function (): void {
    it('generates custom roles implementation prompt', function (): void {
        $roleRequirements = 'Dynamic roles with permission templates';
        $result = $this->authoringPrompts->implementCustomRoles($roleRequirements, 'resource_specific');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($roleRequirements)
            ->and($result[0]['content'])->toContain('resource_specific')
            ->and($result[0]['content'])->toContain('Role Model Design');
    });

    it('uses global role scope by default', function (): void {
        $result = $this->authoringPrompts->implementCustomRoles('Basic roles');

        expect($result[0]['content'])->toContain('global');
    });
});

describe('testModelComprehensive', function (): void {
    it('generates comprehensive test generation prompt', function (): void {
        $model = 'model\n  schema 1.1\ntype user\ntype document';
        $result = $this->authoringPrompts->testModelComprehensive($model, 'security');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($model)
            ->and($result[0]['content'])->toContain('security')
            ->and($result[0]['content'])->toContain('.fga.yaml');
    });

    it('uses comprehensive test focus by default', function (): void {
        $result = $this->authoringPrompts->testModelComprehensive('basic model');

        expect($result[0]['content'])->toContain('comprehensive');
    });
});
