<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Prompts\SecurityGuidancePrompts;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->securityPrompts = new SecurityGuidancePrompts($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('securityReviewModel', function (): void {
    it('generates security review prompt', function (): void {
        $model = 'model\n  schema 1.1\ntype user\ntype document\n  relations\n    define reader: [user]';
        $result = $this->securityPrompts->securityReviewModel($model, 'high', 'HIPAA');

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain($model)
            ->and($result[0]['content'])->toContain('high')
            ->and($result[0]['content'])->toContain('HIPAA')
            ->and($result[0]['content'])->toContain('security review');
    });

    it('uses default security level and compliance', function (): void {
        $result = $this->securityPrompts->securityReviewModel('basic model');

        expect($result[0]['content'])->toContain('standard')
            ->and($result[0]['content'])->toContain('SOC2');
    });

    it('respects restricted mode when configured', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');

        // Since securityReviewModel doesn't take store/model parameters,
        // it won't trigger restricted mode. This test verifies normal operation.
        $result = $this->securityPrompts->securityReviewModel('test model');

        expect($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('test model');
    });
});

describe('implementLeastPrivilege', function (): void {
    it('generates least privilege implementation prompt', function (): void {
        $userRoles = 'Admin, Manager, Employee with specific responsibilities';
        $result = $this->securityPrompts->implementLeastPrivilege(
            'web app',
            $userRoles,
            'personal health information',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('web app')
            ->and($result[0]['content'])->toContain($userRoles)
            ->and($result[0]['content'])->toContain('personal health information')
            ->and($result[0]['content'])->toContain('least privilege');
    });

    it('uses default sensitive data description', function (): void {
        $result = $this->securityPrompts->implementLeastPrivilege('API', 'Basic roles');

        expect($result[0]['content'])->toContain('confidential business data');
    });
});

describe('secureDelegationPatterns', function (): void {
    it('generates secure delegation pattern prompt', function (): void {
        $result = $this->securityPrompts->secureDelegationPatterns(
            'temporary',
            'vacation coverage',
            'high',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('temporary')
            ->and($result[0]['content'])->toContain('vacation coverage')
            ->and($result[0]['content'])->toContain('high')
            ->and($result[0]['content'])->toContain('delegation');
    });

    it('uses default risk level', function (): void {
        $result = $this->securityPrompts->secureDelegationPatterns('permanent', 'role transition');

        expect($result[0]['content'])->toContain('medium');
    });
});

describe('auditFriendlyPatterns', function (): void {
    it('generates audit-friendly patterns prompt', function (): void {
        $result = $this->securityPrompts->auditFriendlyPatterns(
            'SOX',
            'monthly',
            'critical',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('SOX')
            ->and($result[0]['content'])->toContain('monthly')
            ->and($result[0]['content'])->toContain('critical')
            ->and($result[0]['content'])->toContain('audit-friendly');
    });

    it('uses default frequency and criticality', function (): void {
        $result = $this->securityPrompts->auditFriendlyPatterns('PCI-DSS');

        expect($result[0]['content'])->toContain('quarterly')
            ->and($result[0]['content'])->toContain('high');
    });
});
