<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Prompts\RelationshipTroubleshootingPrompts;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->troubleshootingPrompts = new RelationshipTroubleshootingPrompts($this->client);
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_RESTRICT=');
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
});

describe('debugPermissionDenial', function (): void {
    it('generates permission denial debugging prompt', function (): void {
        $result = $this->troubleshootingPrompts->debugPermissionDenial(
            'user:alice',
            'viewer',
            'document:budget',
            'store-123',
            'model-456',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('user:alice')
            ->and($result[0]['content'])->toContain('viewer')
            ->and($result[0]['content'])->toContain('document:budget')
            ->and($result[0]['content'])->toContain('store-123')
            ->and($result[0]['content'])->toContain('model-456')
            ->and($result[0]['content'])->toContain('DENIED');
    });

    it('works without store and model IDs', function (): void {
        $result = $this->troubleshootingPrompts->debugPermissionDenial('user:bob', 'editor', 'file:test');

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toContain('user:bob')
            ->and($result[0]['content'])->toContain('editor')
            ->and($result[0]['content'])->toContain('file:test');
    });

    it('respects restricted mode with store restriction', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        $result = $this->troubleshootingPrompts->debugPermissionDenial(
            'user:alice',
            'viewer',
            'document:test',
            'different-store',
        );

        expect($result[0]['role'])->toBe('system')
            ->and($result[0]['content'])->toContain('restricted mode');
    });
});

describe('analyzePermissionInheritance', function (): void {
    it('generates permission inheritance analysis prompt', function (): void {
        $result = $this->troubleshootingPrompts->analyzePermissionInheritance(
            'user:charlie',
            'folder:documents',
            'should have access',
            'store-789',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('user:charlie')
            ->and($result[0]['content'])->toContain('folder:documents')
            ->and($result[0]['content'])->toContain('should have access')
            ->and($result[0]['content'])->toContain('store-789')
            ->and($result[0]['content'])->toContain('inheritance');
    });

    it('uses default expected access when not specified', function (): void {
        $result = $this->troubleshootingPrompts->analyzePermissionInheritance('user:dave', 'resource:test');

        expect($result[0]['content'])->toContain('should have access');
    });
});

describe('troubleshootUnexpectedAccess', function (): void {
    it('generates unexpected access troubleshooting prompt', function (): void {
        $result = $this->troubleshootingPrompts->troubleshootUnexpectedAccess(
            'user:eve',
            'admin',
            'system:config',
            'store-secure',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('user:eve')
            ->and($result[0]['content'])->toContain('admin')
            ->and($result[0]['content'])->toContain('system:config')
            ->and($result[0]['content'])->toContain('store-secure')
            ->and($result[0]['content'])->toContain('should NOT have');
    });

    it('works without store ID', function (): void {
        $result = $this->troubleshootingPrompts->troubleshootUnexpectedAccess('user:frank', 'write', 'data:sensitive');

        expect($result[0]['content'])->toContain('user:frank')
            ->and($result[0]['content'])->toContain('write')
            ->and($result[0]['content'])->toContain('data:sensitive');
    });
});

describe('optimizeRelationshipQueries', function (): void {
    it('generates query optimization prompt', function (): void {
        $result = $this->troubleshootingPrompts->optimizeRelationshipQueries(
            'list_objects',
            'high latency',
            'complex',
        );

        expect($result)->toBeArray()
            ->and($result[0]['role'])->toBe('user')
            ->and($result[0]['content'])->toContain('list_objects')
            ->and($result[0]['content'])->toContain('high latency')
            ->and($result[0]['content'])->toContain('complex')
            ->and($result[0]['content'])->toContain('Optimize');
    });

    it('uses default values when not specified', function (): void {
        $result = $this->troubleshootingPrompts->optimizeRelationshipQueries('check');

        expect($result[0]['content'])->toContain('slow response times')
            ->and($result[0]['content'])->toContain('moderate');
    });
});
