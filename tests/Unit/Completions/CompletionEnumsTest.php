<?php

declare(strict_types=1);

use OpenFGA\MCP\Completions\{
    AccessPattern,
    AuditFrequency,
    ComplexityLevel,
    ComplianceFramework,
    QueryType,
    RiskLevel,
    SecurityLevel,
    SystemCriticality,
    SystemType
};

describe('Completion Enums', function (): void {
    it('AccessPattern enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, AccessPattern::cases());

        expect($values)->toContain('hierarchical');
        expect($values)->toContain('flat');
        expect($values)->toContain('hybrid');
        expect($values)->toContain('matrix');
        expect($values)->toContain('role-based');
        expect($values)->toContain('attribute-based');
    });

    it('AuditFrequency enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, AuditFrequency::cases());

        expect($values)->toContain('daily');
        expect($values)->toContain('weekly');
        expect($values)->toContain('monthly');
        expect($values)->toContain('quarterly');
        expect($values)->toContain('biannual');
        expect($values)->toContain('annual');
    });

    it('ComplianceFramework enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, ComplianceFramework::cases());

        expect($values)->toContain('SOC2');
        expect($values)->toContain('HIPAA');
        expect($values)->toContain('PCI-DSS');
        expect($values)->toContain('GDPR');
        expect($values)->toContain('SOX');
        expect($values)->toContain('ISO27001');
        expect($values)->toContain('FedRAMP');
        expect($values)->toContain('FISMA');
        expect($values)->toContain('none');
    });

    it('ComplexityLevel enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, ComplexityLevel::cases());

        expect($values)->toContain('simple');
        expect($values)->toContain('moderate');
        expect($values)->toContain('complex');
        expect($values)->toContain('enterprise');
        expect($values)->toContain('highly nested');
    });

    it('QueryType enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, QueryType::cases());

        expect($values)->toContain('check');
        expect($values)->toContain('list_objects');
        expect($values)->toContain('list_users');
        expect($values)->toContain('expand');
        expect($values)->toContain('read_tuples');
        expect($values)->toContain('write_tuples');
    });

    it('RiskLevel enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, RiskLevel::cases());

        expect($values)->toContain('low');
        expect($values)->toContain('medium');
        expect($values)->toContain('high');
        expect($values)->toContain('critical');
    });

    it('SecurityLevel enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, SecurityLevel::cases());

        expect($values)->toContain('standard');
        expect($values)->toContain('high');
        expect($values)->toContain('critical');
        expect($values)->toContain('government');
        expect($values)->toContain('enterprise');
    });

    it('SystemCriticality enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, SystemCriticality::cases());

        expect($values)->toContain('low');
        expect($values)->toContain('medium');
        expect($values)->toContain('high');
        expect($values)->toContain('critical');
    });

    it('SystemType enum has expected values', function (): void {
        $values = array_map(fn ($case) => $case->value, SystemType::cases());

        expect($values)->toContain('web app');
        expect($values)->toContain('API');
        expect($values)->toContain('enterprise');
        expect($values)->toContain('microservices');
        expect($values)->toContain('mobile app');
        expect($values)->toContain('desktop app');
        expect($values)->toContain('SaaS platform');
    });

    it('enums can be used with CompletionProvider attribute', function (): void {
        // Test that enums can be instantiated and used
        expect(ComplianceFramework::SOC2->value)->toBe('SOC2');
        expect(SecurityLevel::HIGH->value)->toBe('high');
        expect(QueryType::CHECK->value)->toBe('check');

        // Test enum cases can be enumerated
        expect(count(RiskLevel::cases()))->toBe(4);
        expect(count(SystemType::cases()))->toBe(7);
    });
});
