<?php

declare(strict_types=1);

use OpenFGA\MCP\Resources\DocumentationResources;

beforeEach(function (): void {
    // Set up online mode for unit tests (documentation works in both modes)
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->resources = new DocumentationResources;
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
});

describe('listDocumentation resource', function (): void {
    it('returns documentation list structure', function (): void {
        $result = $this->resources->listDocumentation();

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString(); // Should have a status message
        expect($result)->toHaveKey('sdk_documentation');
        expect($result)->toHaveKey('guides_documentation');
        expect($result)->toHaveKey('total_sdks');
        expect($result)->toHaveKey('endpoints');
        expect($result['sdk_documentation'])->toBeArray();
        expect($result['guides_documentation'])->toBeArray();
        expect($result['total_sdks'])->toBeInt();
        expect($result['endpoints'])->toBeArray();
    });
});

describe('getSdkDocumentation resource template', function (): void {
    it('handles SDK documentation requests', function (): void {
        $result = $this->resources->getSdkDocumentation('php');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('php');

        // Should either return overview data or "not found" message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('name');
            expect($result)->toHaveKey('sections');
            expect($result)->toHaveKey('total_chunks');
            expect($result)->toHaveKey('type');
        } else {
            expect($result)->toHaveKey('available_sdks');
        }
    });

    it('handles non-existent SDK', function (): void {
        $result = $this->resources->getSdkDocumentation('definitely_nonexistent_sdk_123');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toContain('❌');
        expect($result['requested_sdk'])->toBe('definitely_nonexistent_sdk_123');
        expect($result)->toHaveKey('available_sdks');
        expect($result['available_sdks'])->toBeArray();
    });
});

describe('getClassDocumentation resource template', function (): void {
    it('handles class documentation requests', function (): void {
        $result = $this->resources->getClassDocumentation('php', 'SomeClass');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('php');

        // Should either return content or not found message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('content');
            expect($result)->toHaveKey('metadata');
            expect($result['metadata'])->toHaveKey('class');
            expect($result['metadata'])->toHaveKey('sdk');
            expect($result['metadata'])->toHaveKey('namespace');
            expect($result['metadata'])->toHaveKey('methods');
            expect($result['metadata'])->toHaveKey('method_count');
        } elseif (str_contains($result['status'], '❌') && str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_class');
            expect($result)->toHaveKey('available_classes');
        } else {
            // Error case
            expect($result)->toHaveKey('class');
            expect($result)->toHaveKey('error');
        }
    });
});

describe('getMethodDocumentation resource template', function (): void {
    it('handles method documentation requests', function (): void {
        $result = $this->resources->getMethodDocumentation('php', 'SomeClass', 'someMethod');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('php');

        // Should either return content or not found message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('content');
            expect($result)->toHaveKey('metadata');
            expect($result['metadata'])->toHaveKey('method');
            expect($result['metadata'])->toHaveKey('class');
            expect($result['metadata'])->toHaveKey('sdk');
            expect($result['metadata'])->toHaveKey('signature');
            expect($result['metadata'])->toHaveKey('parameters');
            expect($result['metadata'])->toHaveKey('returns');
        } elseif (str_contains($result['status'], '❌') && str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_method');
            expect($result)->toHaveKey('class');
            expect($result)->toHaveKey('available_methods');
        } else {
            // Error case
            expect($result)->toHaveKey('method');
            expect($result)->toHaveKey('class');
            expect($result)->toHaveKey('error');
        }
    });
});

describe('getDocumentationSection resource template', function (): void {
    it('handles section requests', function (): void {
        $result = $this->resources->getDocumentationSection('php', 'SomeSection');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('php');

        // Should either return content or not found message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('content');
            expect($result)->toHaveKey('metadata');
            expect($result['metadata'])->toHaveKey('section');
            expect($result['metadata'])->toHaveKey('sdk');
            expect($result['metadata'])->toHaveKey('chunk_count');
            expect($result['metadata'])->toHaveKey('total_size');
        } elseif (str_contains($result['status'], '❌') && str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_section');
            expect($result)->toHaveKey('available_sections');
        } else {
            // Error case
            expect($result)->toHaveKey('section');
            expect($result)->toHaveKey('error');
        }
    });
});

describe('getDocumentationChunk resource template', function (): void {
    it('handles chunk requests', function (): void {
        $result = $this->resources->getDocumentationChunk('php', 'some_chunk_id');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('php');

        // Should either return content or not found message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('content');
            expect($result)->toHaveKey('metadata');
            expect($result)->toHaveKey('navigation');
            expect($result['metadata'])->toHaveKey('chunk_id');
            expect($result['metadata'])->toHaveKey('sdk');
        } elseif (str_contains($result['status'], '❌') && str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_chunk');
            expect($result)->toHaveKey('note');
        } else {
            // Error case
            expect($result)->toHaveKey('chunk_id');
            expect($result)->toHaveKey('error');
        }
    });
});

describe('searchDocumentation resource template', function (): void {
    it('handles search requests', function (): void {
        $result = $this->resources->searchDocumentation('test_query');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('test_query');

        // Should either return results or no results message
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('total_results');
            expect($result)->toHaveKey('results');
            expect($result['results'])->toBeArray();
        } elseif (str_contains($result['status'], 'No results')) {
            expect($result)->toHaveKey('suggestion');
            expect($result)->toHaveKey('available_sdks');
        } else {
            // Error case
            expect($result)->toHaveKey('error');
        }
    });
});

describe('offline mode behavior', function (): void {
    it('works in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear to simulate offline mode

        $result = $this->resources->listDocumentation();

        // Documentation resources should work in offline mode
        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();
        expect($result)->toHaveKey('sdk_documentation');
    });
});
