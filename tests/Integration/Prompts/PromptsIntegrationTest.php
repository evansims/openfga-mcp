<?php

declare(strict_types=1);

use OpenFGA\MCP\Prompts\{ModelDesignPrompts, RelationshipTroubleshootingPrompts, SecurityGuidancePrompts};

beforeEach(function (): void {
    $this->client = getTestClient();
    $this->modelDesignPrompts = new ModelDesignPrompts($this->client);
    $this->troubleshootingPrompts = new RelationshipTroubleshootingPrompts($this->client);
    $this->securityPrompts = new SecurityGuidancePrompts($this->client);
});

describe('Prompts Integration', function (): void {
    it('ModelDesignPrompts can generate domain-specific prompts', function (): void {
        $result = $this->modelDesignPrompts->designModelForDomain('healthcare', 'hierarchical', 'complex');

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toHaveKeys(['role', 'content'])
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toBeString()
            ->and($result[0]['content'])->toContain('healthcare')
            ->and($result[0]['content'])->toContain('OpenFGA');
    });

    it('ModelDesignPrompts can generate RBAC conversion prompts', function (): void {
        $roleDescription = 'Admin, Manager, User roles with read/write permissions';
        $result = $this->modelDesignPrompts->convertRbacToRebac($roleDescription);

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain($roleDescription)
            ->and($result[0]['content'])->toContain('RBAC')
            ->and($result[0]['content'])->toContain('ReBAC');
    });

    it('RelationshipTroubleshootingPrompts can debug permission issues', function (): void {
        $result = $this->troubleshootingPrompts->debugPermissionDenial(
            'user:testuser',
            'viewer',
            'document:test',
        );

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain('user:testuser')
            ->and($result[0]['content'])->toContain('viewer')
            ->and($result[0]['content'])->toContain('document:test')
            ->and($result[0]['content'])->toContain('DENIED');
    });

    it('RelationshipTroubleshootingPrompts can analyze inheritance', function (): void {
        $result = $this->troubleshootingPrompts->analyzePermissionInheritance(
            'user:manager',
            'folder:project',
        );

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain('inheritance')
            ->and($result[0]['content'])->toContain('user:manager')
            ->and($result[0]['content'])->toContain('folder:project');
    });

    it('SecurityGuidancePrompts can generate security reviews', function (): void {
        $testModel = 'model
  schema 1.1

type user

type document
  relations
    define reader: [user]
    define writer: [user]';

        $result = $this->securityPrompts->securityReviewModel($testModel, 'high', 'SOC2');

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain($testModel)
            ->and($result[0]['content'])->toContain('security review')
            ->and($result[0]['content'])->toContain('SOC2');
    });

    it('SecurityGuidancePrompts can generate least privilege guidance', function (): void {
        $userRoles = 'Developer, QA, DevOps roles';
        $result = $this->securityPrompts->implementLeastPrivilege('microservices', $userRoles);

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain('least privilege')
            ->and($result[0]['content'])->toContain('microservices')
            ->and($result[0]['content'])->toContain($userRoles);
    });

    it('all prompts respect restricted mode', function (): void {
        $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        $_ENV['OPENFGA_MCP_API_STORE'] = 'allowed-store';
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        // Test ModelDesignPrompts - these don't take store parameters so are not restricted
        $result1 = $this->modelDesignPrompts->designModelForDomain('test');
        expect($result1[0]['role'])->toBe('user')
            ->and($result1[0]['content'])->toContain('OpenFGA');

        // Test RelationshipTroubleshootingPrompts with different store
        $result2 = $this->troubleshootingPrompts->debugPermissionDenial(
            'user:test',
            'viewer',
            'doc:test',
            'different-store',
        );
        expect($result2[0]['role'])->toBe('system')
            ->and($result2[0]['content'])->toContain('restricted mode');

        // Test SecurityGuidancePrompts - these don't take store parameters so are not restricted
        $result3 = $this->securityPrompts->securityReviewModel('test model');
        expect($result3[0]['role'])->toBe('user')
            ->and($result3[0]['content'])->toContain('security review');

        // Clean up
        unset($_ENV['OPENFGA_MCP_API_RESTRICT']);
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        unset($_ENV['OPENFGA_MCP_API_STORE']);
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('prompts work correctly when restricted mode allows access', function (): void {
        $_ENV['OPENFGA_MCP_API_RESTRICT'] = 'true';
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        $_ENV['OPENFGA_MCP_API_STORE'] = 'allowed-store';
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        // Test with allowed store - should work normally
        $result = $this->troubleshootingPrompts->debugPermissionDenial(
            'user:test',
            'viewer',
            'doc:test',
            'allowed-store',
        );
        expect($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->not->toContain('restricted mode');

        // Clean up
        unset($_ENV['OPENFGA_MCP_API_RESTRICT']);
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        unset($_ENV['OPENFGA_MCP_API_STORE']);
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
