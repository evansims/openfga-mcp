<?php

declare(strict_types=1);

use OpenFGA\MCP\Resources\DocumentationResources;

beforeEach(function (): void {
    // Set up online mode for unit tests (documentation works in both modes)
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->resources = new DocumentationResources;
});

afterEach(function (): void {
    putenv('OPENFGA_MCP_API_URL=');
});

describe('DocumentationResources class structure', function (): void {
    it('is final and readonly', function (): void {
        $reflection = new ReflectionClass(DocumentationResources::class);
        expect($reflection->isFinal())->toBeTrue();
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('extends AbstractResources', function (): void {
        $reflection = new ReflectionClass(DocumentationResources::class);
        expect($reflection->getParentClass()->getName())->toBe('OpenFGA\\MCP\\Resources\\AbstractResources');
    });

    it('has private index property', function (): void {
        $reflection = new ReflectionClass(DocumentationResources::class);
        expect($reflection->hasProperty('index'))->toBeTrue();

        $property = $reflection->getProperty('index');
        expect($property->isPrivate())->toBeTrue();
        expect($property->getType()->getName())->toBe('OpenFGA\\MCP\\Documentation\\DocumentationIndex');
    });
});

describe('listDocumentation resource', function (): void {
    it('returns documentation list structure', function (): void {
        $result = $this->resources->listDocumentation();

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result['status'])->toBeString();

        // Can have error or success status
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('sdk_documentation');
            expect($result)->toHaveKey('guides_documentation');
            expect($result)->toHaveKey('total_sdks');
            expect($result)->toHaveKey('endpoints');
            expect($result['sdk_documentation'])->toBeArray();
            expect($result['guides_documentation'])->toBeArray();
            expect($result['total_sdks'])->toBeInt();
            expect($result['endpoints'])->toBeArray();
        } else {
            // Error case
            expect($result)->toHaveKey('error');
            expect($result)->toHaveKey('note');
        }
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

    it('handles general documentation type', function (): void {
        $result = $this->resources->getSdkDocumentation('general');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('general');

        // If found, should be marked as general type
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('type');
            expect($result['type'])->toBe('general');
        }
    });

    it('handles authoring documentation type', function (): void {
        $result = $this->resources->getSdkDocumentation('authoring');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('sdk');
        expect($result['sdk'])->toBe('authoring');

        // If found, should be marked as general type
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('type');
            expect($result['type'])->toBe('general');
        }
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

    it('handles non-existent class', function (): void {
        $result = $this->resources->getClassDocumentation('nonexistent_sdk', 'NonExistentClass');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        // Should be either not found or error
        if (str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_class');
            expect($result['requested_class'])->toBe('NonExistentClass');
        } else {
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

    it('handles non-existent method', function (): void {
        $result = $this->resources->getMethodDocumentation('nonexistent_sdk', 'SomeClass', 'nonExistentMethod');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        // Should be either not found or error
        if (str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_method');
            expect($result['requested_method'])->toBe('nonExistentMethod');
        } else {
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

    it('handles non-existent section', function (): void {
        $result = $this->resources->getDocumentationSection('nonexistent_sdk', 'NonExistentSection');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        // Should be either not found or error
        if (str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_section');
            expect($result['requested_section'])->toBe('NonExistentSection');
        } else {
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

    it('handles non-existent chunk', function (): void {
        $result = $this->resources->getDocumentationChunk('nonexistent_sdk', 'NonExistentChunk');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        // Should be either not found or error
        if (str_contains($result['status'], 'not found')) {
            expect($result)->toHaveKey('requested_chunk');
            expect($result['requested_chunk'])->toBe('NonExistentChunk');
        } else {
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

    it('handles empty search query', function (): void {
        $result = $this->resources->searchDocumentation('');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('');
    });

    it('handles special characters in search', function (): void {
        $result = $this->resources->searchDocumentation('check() && expand()');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('check() && expand()');
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

        // Can still return documentation in offline mode or an error
        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('sdk_documentation');
        } else {
            expect($result)->toHaveKey('error');
        }
    });

    it('search works in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear to simulate offline mode

        $result = $this->resources->searchDocumentation('test');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('query');
    });
});

describe('response structure validation', function (): void {
    it('all methods return arrays with status key', function (): void {
        $methods = [
            ['listDocumentation', []],
            ['getSdkDocumentation', ['php']],
            ['getClassDocumentation', ['php', 'Test']],
            ['getMethodDocumentation', ['php', 'Test', 'method']],
            ['getDocumentationSection', ['php', 'section']],
            ['getDocumentationChunk', ['php', 'chunk-1']],
            ['searchDocumentation', ['query']],
        ];

        foreach ($methods as [$method, $params]) {
            $result = $this->resources->{$method}(...$params);

            expect($result)->toBeArray("{$method} should return array");
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeString("{$method} status should be string");
        }
    });
});

describe('navigation handling', function (): void {
    it('handles navigation in chunk results', function (): void {
        $result = $this->resources->getDocumentationChunk('php', 'chunk-123');

        expect($result)->toBeArray();

        if (str_contains($result['status'], '✅')) {
            expect($result)->toHaveKey('navigation');
            expect($result['navigation'])->toBeArray();

            // Navigation can have previous and/or next keys, or be empty
            if (! empty($result['navigation'])) {
                foreach ($result['navigation'] as $key => $value) {
                    expect($key)->toBeIn(['previous', 'next']);
                    expect($value)->toBeString();
                }
            }
        }
    });
});

describe('metadata validation', function (): void {
    it('class documentation has proper metadata structure', function (): void {
        $result = $this->resources->getClassDocumentation('php', 'TestClass');

        // Always perform at least one assertion
        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        if (str_contains($result['status'], '✅')) {
            // Documentation was found, check metadata structure
            expect($result)->toHaveKey('metadata');
            expect($result['metadata'])->toHaveKey('class');
            expect($result['metadata'])->toHaveKey('sdk');
            expect($result['metadata'])->toHaveKey('methods');
            expect($result['metadata']['methods'])->toBeArray();
            expect($result['metadata'])->toHaveKey('method_count');
            expect($result['metadata']['method_count'])->toBeInt();
        } else {
            // Documentation not found, verify error response structure
            expect($result['status'])->toContain('❌');

            // Check for specific keys based on the error response type
            // Class documentation not found has different keys than method documentation not found
            if (isset($result['requested_class'])) {
                expect($result)->toHaveKey('requested_class');
                expect($result)->toHaveKey('available_classes');
            } elseif (isset($result['requested_method'])) {
                expect($result)->toHaveKey('requested_method');
                expect($result)->toHaveKey('available_methods');
            }
            expect($result)->toHaveKey('sdk');
        }
    });

    it('method documentation has proper metadata structure', function (): void {
        $result = $this->resources->getMethodDocumentation('php', 'TestClass', 'testMethod');

        // Always perform at least one assertion
        expect($result)->toBeArray();
        expect($result)->toHaveKey('status');

        if (str_contains($result['status'], '✅')) {
            // Documentation was found, check metadata structure
            expect($result)->toHaveKey('metadata');
            expect($result['metadata'])->toHaveKey('method');
            expect($result['metadata'])->toHaveKey('class');
            expect($result['metadata'])->toHaveKey('sdk');
            expect($result['metadata'])->toHaveKey('parameters');
            expect($result['metadata']['parameters'])->toBeArray();
        } else {
            // Documentation not found, verify error response structure
            expect($result['status'])->toContain('❌');

            // Check for specific keys based on the error response type
            // Class documentation not found has different keys than method documentation not found
            if (isset($result['requested_class'])) {
                expect($result)->toHaveKey('requested_class');
                expect($result)->toHaveKey('available_classes');
            } elseif (isset($result['requested_method'])) {
                expect($result)->toHaveKey('requested_method');
                expect($result)->toHaveKey('available_methods');
            }
            expect($result)->toHaveKey('sdk');
        }
    });
});
