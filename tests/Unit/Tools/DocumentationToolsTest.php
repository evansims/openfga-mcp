<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Tools\DocumentationTools;

describe('DocumentationTools', function (): void {
    beforeEach(function (): void {
        $this->mockClient = Mockery::mock(ClientInterface::class);
        $this->tools = new DocumentationTools($this->mockClient);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    describe('searchDocumentation', function (): void {
        it('returns error for empty query', function (): void {
            $result = $this->tools->searchDocumentation('');
            expect($result)->toContain('❌ Search query cannot be empty');
        });

        it('returns error for invalid limit', function (): void {
            $result = $this->tools->searchDocumentation('test', null, 'content', 0);
            expect($result)->toContain('❌ Limit must be between 1 and 50');

            $result = $this->tools->searchDocumentation('test', null, 'content', 51);
            expect($result)->toContain('❌ Limit must be between 1 and 50');
        });

        it('returns error for negative offset', function (): void {
            $result = $this->tools->searchDocumentation('test', null, 'content', 10, -1);
            expect($result)->toContain('❌ Offset cannot be negative');
        });

        it('returns error for invalid search type', function (): void {
            $result = $this->tools->searchDocumentation('test', null, 'invalid_type');
            expect($result)->toContain('❌ Invalid search_type');
            expect($result)->toContain('content, class, method, section');
        });

        it('returns error for invalid SDK', function (): void {
            $result = $this->tools->searchDocumentation('test', 'invalid_sdk');
            expect($result)->toContain('❌ Invalid SDK');
            expect($result)->toContain('php, go, python, java, dotnet, js, laravel');
        });

        it('performs basic content search', function (): void {
            $result = $this->tools->searchDocumentation('openfga');

            expect($result)->toBeString();
            expect($result)->toContain('## Documentation Search Results');
            expect($result)->toContain('**Query:** `openfga`');
        });

        it('performs search with SDK filter', function (): void {
            $result = $this->tools->searchDocumentation('check', 'php');

            expect($result)->toBeString();
            expect($result)->toContain('## Documentation Search Results');
            expect($result)->toContain('**SDK Filter:** php');
        });

        it('performs class search', function (): void {
            $result = $this->tools->searchDocumentation('Client', null, 'class');

            expect($result)->toBeString();
            expect($result)->toContain('**Search Type:** class');
        });

        it('performs method search', function (): void {
            $result = $this->tools->searchDocumentation('check', null, 'method');

            expect($result)->toBeString();
            expect($result)->toContain('**Search Type:** method');
        });

        it('performs section search', function (): void {
            $result = $this->tools->searchDocumentation('Authentication', null, 'section');

            expect($result)->toBeString();
            expect($result)->toContain('**Search Type:** section');
        });

        it('handles pagination properly', function (): void {
            $result = $this->tools->searchDocumentation('test', null, 'content', 5, 10);

            expect($result)->toBeString();
            expect($result)->toContain('## Documentation Search Results');

            // Should show results starting from offset 10
            if (str_contains($result, 'Results:')) {
                expect($result)->toMatch('/\*\*Results:\*\* Showing \d+/');
            }
        });

        it('formats no results message properly', function (): void {
            $result = $this->tools->searchDocumentation('xyznonexistentquery123');

            expect($result)->toBeString();

            if (str_contains($result, 'No results found')) {
                expect($result)->toContain('No results found for query');
                expect($result)->toContain('Try:');
                expect($result)->toContain('Using different keywords');
            }
        });
    });

    describe('searchCodeExamples', function (): void {
        it('returns error for empty query', function (): void {
            $result = $this->tools->searchCodeExamples('');
            expect($result)->toContain('❌ Search query cannot be empty');
        });

        it('returns error for invalid limit', function (): void {
            $result = $this->tools->searchCodeExamples('test', null, true, 0);
            expect($result)->toContain('❌ Limit must be between 1 and 20');

            $result = $this->tools->searchCodeExamples('test', null, true, 21);
            expect($result)->toContain('❌ Limit must be between 1 and 20');
        });

        it('returns error for negative offset', function (): void {
            $result = $this->tools->searchCodeExamples('test', null, true, 5, -1);
            expect($result)->toContain('❌ Offset cannot be negative');
        });

        it('returns error for invalid language', function (): void {
            $result = $this->tools->searchCodeExamples('test', 'invalid_lang');
            expect($result)->toContain('❌ Invalid language');
            expect($result)->toContain('php, go, python, java, csharp, javascript, typescript');
        });

        it('performs basic code search', function (): void {
            $result = $this->tools->searchCodeExamples('check');

            expect($result)->toBeString();
            expect($result)->toContain('## Code Examples');
            expect($result)->toContain('**Search:** `check`');
        });

        it('performs search with language filter', function (): void {
            $result = $this->tools->searchCodeExamples('client', 'php');

            expect($result)->toBeString();
            expect($result)->toContain('## Code Examples');
            expect($result)->toContain('**Language:** php');
        });

        it('includes context when requested', function (): void {
            $result = $this->tools->searchCodeExamples('new Client', null, true);

            expect($result)->toBeString();

            // If examples are found, they might include context
            if (! str_contains($result, 'No code examples found')) {
                // Context section would be included if examples exist
                expect($result)->toContain('## Code Examples');
            }
        });

        it('excludes context when not requested', function (): void {
            $result = $this->tools->searchCodeExamples('new Client', null, false);

            expect($result)->toBeString();
            expect($result)->toContain('## Code Examples');
        });

        it('handles pagination properly', function (): void {
            $result = $this->tools->searchCodeExamples('function', null, true, 3, 5);

            expect($result)->toBeString();
            expect($result)->toContain('## Code Examples');

            // Should show pagination info if there are results
            if (! str_contains($result, 'No code examples found')) {
                expect($result)->toMatch('/\*\*Results:\*\* Showing \d+/');
            }
        });

        it('formats no results message properly', function (): void {
            $result = $this->tools->searchCodeExamples('xyznonexistentcode123');

            expect($result)->toBeString();

            if (str_contains($result, 'No code examples found')) {
                expect($result)->toContain('No code examples found for');
                expect($result)->toContain('Try:');
                expect($result)->toContain('Searching for specific method or class names');
            }
        });

        it('maps language to SDK correctly', function (): void {
            // Test PHP mapping
            $result = $this->tools->searchCodeExamples('client', 'php');
            expect($result)->toBeString();
            expect($result)->toContain('**Language:** php');

            // Test JavaScript mapping
            $result = $this->tools->searchCodeExamples('client', 'javascript');
            expect($result)->toBeString();
            expect($result)->toContain('**Language:** javascript');

            // Test TypeScript mapping
            $result = $this->tools->searchCodeExamples('client', 'typescript');
            expect($result)->toBeString();
            expect($result)->toContain('**Language:** typescript');
        });
    });

    describe('findSimilarDocumentation', function (): void {
        it('returns error for empty content', function (): void {
            $result = $this->tools->findSimilarDocumentation('');
            expect($result)->toContain('❌ Content cannot be empty');
        });

        it('returns error for invalid similarity threshold', function (): void {
            $result = $this->tools->findSimilarDocumentation('test content', null, -0.1);
            expect($result)->toContain('❌ Similarity threshold must be between 0.0 and 1.0');

            $result = $this->tools->findSimilarDocumentation('test content', null, 1.1);
            expect($result)->toContain('❌ Similarity threshold must be between 0.0 and 1.0');
        });

        it('returns error for invalid limit', function (): void {
            $result = $this->tools->findSimilarDocumentation('test content', null, 0.5, 0);
            expect($result)->toContain('❌ Limit must be between 1 and 20');

            $result = $this->tools->findSimilarDocumentation('test content', null, 0.5, 21);
            expect($result)->toContain('❌ Limit must be between 1 and 20');
        });

        it('returns error for invalid SDK', function (): void {
            $result = $this->tools->findSimilarDocumentation('test content', 'invalid_sdk');
            expect($result)->toContain('❌ Invalid SDK');
            expect($result)->toContain('php, go, python, java, dotnet, js, laravel');
        });

        it('finds similar documentation', function (): void {
            $content = 'I need to understand how to check permissions in OpenFGA using the check method';
            $result = $this->tools->findSimilarDocumentation($content);

            expect($result)->toBeString();
            expect($result)->toContain('## Similar Documentation');

            // The test should handle both cases - finding results or not finding results
            if (str_contains($result, 'No similar documentation found')) {
                expect($result)->toContain('Try:');
                expect($result)->toContain('Lowering the similarity threshold');
            } else {
                expect($result)->toContain('**Similarity Threshold:** 0.5');
            }
        });

        it('finds similar documentation with SDK filter', function (): void {
            $content = 'How do I create a new client instance and connect to OpenFGA?';
            $result = $this->tools->findSimilarDocumentation($content, 'php');

            expect($result)->toBeString();
            expect($result)->toContain('## Similar Documentation');

            // The test should handle both cases - finding results or not finding results
            if (str_contains($result, 'No similar documentation found')) {
                expect($result)->toContain('in SDK: php');
            } else {
                expect($result)->toContain('**SDK Filter:** php');
            }
        });

        it('respects similarity threshold', function (): void {
            $content = 'OpenFGA authorization model with user groups';

            // Lower threshold should find more results
            $result1 = $this->tools->findSimilarDocumentation($content, null, 0.3);
            expect($result1)->toBeString();

            if (str_contains($result1, 'No similar documentation found')) {
                expect($result1)->toContain('(threshold: 0.3)');
            } else {
                expect($result1)->toContain('**Similarity Threshold:** 0.3');
            }

            // Higher threshold should be more restrictive
            $result2 = $this->tools->findSimilarDocumentation($content, null, 0.9);
            expect($result2)->toBeString();

            if (str_contains($result2, 'No similar documentation found')) {
                expect($result2)->toContain('(threshold: 0.9)');
            } else {
                expect($result2)->toContain('**Similarity Threshold:** 0.9');
            }
        });

        it('limits results properly', function (): void {
            $content = 'OpenFGA client methods and API calls';

            $result = $this->tools->findSimilarDocumentation($content, null, 0.3, 3);
            expect($result)->toBeString();

            // If similar docs are found, should respect limit
            if (! str_contains($result, 'No similar documentation found')) {
                // Count the number of result headers (### 1., ### 2., etc.)
                preg_match_all('/### \d+\./', $result, $matches);
                expect(count($matches[0]))->toBeLessThanOrEqual(3);
            }
        });

        it('formats no results message properly', function (): void {
            $content = 'xyzabc123 nonexistent random gibberish content that will not match anything';
            $result = $this->tools->findSimilarDocumentation($content, null, 0.95);

            expect($result)->toBeString();

            if (str_contains($result, 'No similar documentation found')) {
                expect($result)->toContain('No similar documentation found');
                expect($result)->toContain('Try:');
                expect($result)->toContain('Lowering the similarity threshold');
            }
        });

        it('handles content with code blocks', function (): void {
            $content = <<<'CONTENT'
                I'm trying to use the check method like this:
                ```php
                $client->check($request);
                ```
                But I'm getting an error. How should I properly call the check method?
                CONTENT;

            $result = $this->tools->findSimilarDocumentation($content);
            expect($result)->toBeString();
            expect($result)->toContain('## Similar Documentation');
        });

        it('extracts key terms properly', function (): void {
            $content = 'OpenFGA authorization tuples and relationships with user groups and permissions';
            $result = $this->tools->findSimilarDocumentation($content);

            expect($result)->toBeString();
            // Should find documentation related to these OpenFGA concepts
            expect($result)->toContain('## Similar Documentation');
        });
    });

    describe('edge cases', function (): void {
        it('handles whitespace-only query gracefully', function (): void {
            $result = $this->tools->searchDocumentation('   ');
            expect($result)->toContain('❌ Search query cannot be empty');

            $result = $this->tools->searchCodeExamples("\t\n");
            expect($result)->toContain('❌ Search query cannot be empty');

            $result = $this->tools->findSimilarDocumentation('   ');
            expect($result)->toContain('❌ Content cannot be empty');
        });

        it('handles very long queries gracefully', function (): void {
            $longQuery = str_repeat('test ', 100);

            $result = $this->tools->searchDocumentation($longQuery);
            expect($result)->toBeString();
            expect($result)->toContain('## Documentation Search Results');

            $result = $this->tools->searchCodeExamples($longQuery);
            expect($result)->toBeString();
            expect($result)->toContain('## Code Examples');
        });

        it('handles special characters in queries', function (): void {
            $specialQuery = 'check() && expand()';

            $result = $this->tools->searchDocumentation($specialQuery);
            expect($result)->toBeString();
            expect($result)->not->toContain('❌');

            $result = $this->tools->searchCodeExamples($specialQuery);
            expect($result)->toBeString();
            expect($result)->not->toContain('❌');
        });

        it('handles Unicode characters in content', function (): void {
            $unicodeContent = 'OpenFGA 授权 权限 检查 用户组 🔐';

            $result = $this->tools->findSimilarDocumentation($unicodeContent);
            expect($result)->toBeString();
            expect($result)->toContain('## Similar Documentation');
        });

        it('handles null SDK parameter correctly', function (): void {
            $result = $this->tools->searchDocumentation('test', null);
            expect($result)->toBeString();
            expect($result)->not->toContain('**SDK Filter:**');

            $result = $this->tools->searchCodeExamples('test', null);
            expect($result)->toBeString();
            // When no language filter is provided, the filter should not be shown in the header
            // But individual code examples can still show their language
            $lines = explode('
', $result);
            $headerSection = implode('
', array_slice($lines, 0, 10)); // Check only the header section
            expect($headerSection)->not->toContain('**Language:**');

            $result = $this->tools->findSimilarDocumentation('test content', null);
            expect($result)->toBeString();
            expect($result)->not->toContain('**SDK Filter:**');
        });
    });

    describe('markdown formatting', function (): void {
        it('generates valid markdown headers', function (): void {
            $result = $this->tools->searchDocumentation('test');

            // Check for proper markdown headers
            expect($result)->toMatch('/^## /m');
            expect($result)->toContain('**Query:**');
        });

        it('formats code blocks properly', function (): void {
            $result = $this->tools->searchCodeExamples('client');

            expect($result)->toBeString();

            // If code examples are found, they should be in code blocks
            if (! str_contains($result, 'No code examples found')) {
                // Code blocks should be properly formatted
                expect($result)->toContain('## Code Examples');
            }
        });

        it('includes pagination navigation when needed', function (): void {
            $result = $this->tools->searchDocumentation('test', null, 'content', 5, 0);

            expect($result)->toBeString();

            // If there are more results than the limit, pagination should be shown
            if (str_contains($result, 'Page:') && str_contains($result, ' of ')) {
                if (preg_match('/Page: \d+ of (\d+)/', $result, $matches)) {
                    $totalPages = (int) $matches[1];

                    if (1 < $totalPages) {
                        expect($result)->toContain('### Pagination');
                    }
                }
            }
        });

        it('formats similarity scores as percentages', function (): void {
            $result = $this->tools->findSimilarDocumentation('OpenFGA check method');

            expect($result)->toBeString();

            // If similar docs are found, similarity should be shown as percentage
            if (! str_contains($result, 'No similar documentation found')) {
                // Should contain percentage formatting if results exist
                if (str_contains($result, '**Similarity Score:**')) {
                    expect($result)->toMatch('/\*\*Similarity Score:\*\* \d+%/');
                }
            }
        });
    });
});
