<?php

declare(strict_types=1);

namespace Tests\Integration\Tools;

use Mockery;
use OpenFGA\ClientInterface;
use OpenFGA\MCP\Documentation\DocumentationIndexSingleton;
use OpenFGA\MCP\Tools\DocumentationTools;
use PHPUnit\Framework\TestCase;

use function count;

final class DocumentationToolsIntegrationTest extends TestCase
{
    private DocumentationTools $tools;

    protected function setUp(): void
    {
        parent::setUp();

        $mockClient = Mockery::mock(ClientInterface::class);
        $this->tools = new DocumentationTools($mockClient);

        // Ensure documentation index is initialized for integration tests
        $index = DocumentationIndexSingleton::getInstance();

        if (! $index->isInitialized()) {
            $index->initialize();
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCodeExampleFormattingIsValid(): void
    {
        $result = $this->tools->searchCodeExamples('client', null, true, 2);

        if (! str_contains($result, 'No code examples found')) {
            // Should have example headers
            $this->assertMatchesRegularExpression('/### Example \d+/', $result);

            // Should have code blocks with language hints
            $this->assertMatchesRegularExpression('/```\w*\n/', $result);

            // Should close code blocks properly
            $codeBlockStarts = substr_count($result, '```');
            $this->assertEquals(0, $codeBlockStarts % 2, 'Code blocks should be properly opened and closed');
        }
    }

    public function testDifferentSearchTypesReturnDifferentResults(): void
    {
        $query = 'Client';

        $contentResults = $this->tools->searchDocumentation($query, null, 'content', 5);
        $classResults = $this->tools->searchDocumentation($query, null, 'class', 5);
        $methodResults = $this->tools->searchDocumentation($query, null, 'method', 5);
        $sectionResults = $this->tools->searchDocumentation($query, null, 'section', 5);

        // All should return valid responses
        $this->assertIsString($contentResults);
        $this->assertIsString($classResults);
        $this->assertIsString($methodResults);
        $this->assertIsString($sectionResults);

        // Each should indicate its search type
        $this->assertStringContainsString('**Search Type:** content', $contentResults);
        $this->assertStringContainsString('**Search Type:** class', $classResults);
        $this->assertStringContainsString('**Search Type:** method', $methodResults);
        $this->assertStringContainsString('**Search Type:** section', $sectionResults);
    }

    public function testFindSimilarDocumentationWithRealContent(): void
    {
        $content = 'I need to understand how to perform authorization checks in OpenFGA. ' .
                   'Specifically, I want to check if a user has permission to access a resource.';

        $result = $this->tools->findSimilarDocumentation($content, null, 0.3, 5);

        $this->assertIsString($result);
        $this->assertStringContainsString('## Similar Documentation', $result);
        $this->assertStringContainsString('**Similarity Threshold:** 0.3', $result);

        // Should find related documentation
        if (! str_contains($result, 'No similar documentation found')) {
            $this->assertStringContainsString('**Similarity Score:**', $result);
            $this->assertMatchesRegularExpression('/### \d+\./', $result);
        }
    }

    public function testHandlesSpecialCharactersInQueries(): void
    {
        $queries = [
            'check()',
            'user:anne',
            'document#viewer',
            'can_view->document',
            '$request->check()',
        ];

        foreach ($queries as $query) {
            $result = $this->tools->searchDocumentation($query);

            $this->assertIsString($result);
            $this->assertStringNotContainsString('❌', $result, "Query '{$query}' should not cause an error");
            $this->assertStringContainsString('## Documentation Search Results', $result);
        }
    }

    public function testLanguageMappingWorks(): void
    {
        $languages = [
            'php' => 'PHP',
            'go' => 'Go',
            'python' => 'Python',
            'java' => 'Java',
            'csharp' => 'C#',
            'javascript' => 'JavaScript',
            'typescript' => 'TypeScript',
        ];

        foreach ($languages as $langCode => $langName) {
            $result = $this->tools->searchCodeExamples('client', $langCode, false, 1);

            $this->assertIsString($result);
            $this->assertStringContainsString("**Language:** {$langCode}", $result);
        }
    }

    public function testMarkdownFormattingIsValid(): void
    {
        $result = $this->tools->searchDocumentation('OpenFGA', null, 'content', 3);

        // Check for valid markdown structure
        $this->assertMatchesRegularExpression('/^## /m', $result);
        $this->assertStringContainsString('**Query:**', $result);

        if (! str_contains($result, 'No results found')) {
            // Should have properly formatted results
            $this->assertMatchesRegularExpression('/### \d+\./', $result);

            // Should have metadata with proper markdown
            if (str_contains($result, '**SDK:**')) {
                $this->assertMatchesRegularExpression('/\*\*SDK:\*\* `[^`]+`/', $result);
            }
        }
    }

    public function testMemoryUsageIsReasonable(): void
    {
        $memoryBefore = memory_get_usage(true);

        // Perform memory-intensive operations
        for ($i = 0; 10 > $i; $i++) {
            $this->tools->searchDocumentation('test' . $i, null, 'content', 20);
        }

        $memoryAfter = memory_get_usage(true);
        $memoryUsedMB = ($memoryAfter - $memoryBefore) / 1024 / 1024;

        // Should use less than 50MB for 10 searches
        $this->assertLessThan(50, $memoryUsedMB, 'Documentation tools should be memory efficient');
    }

    public function testMultipleSdkSearches(): void
    {
        $sdks = ['php', 'go', 'python', 'java', 'dotnet', 'js'];

        foreach ($sdks as $sdk) {
            $result = $this->tools->searchDocumentation('client', $sdk, 'content', 2);

            $this->assertIsString($result);
            $this->assertStringContainsString("**SDK Filter:** {$sdk}", $result);

            // If results are found for this SDK
            if (! str_contains($result, 'No results found')) {
                // Results should be from the specified SDK
                $this->assertStringContainsString($sdk, strtolower($result));
            }
        }
    }

    public function testPaginationWorks(): void
    {
        // First page
        $page1 = $this->tools->searchDocumentation('the', null, 'content', 5, 0);
        $this->assertIsString($page1);

        // Second page
        $page2 = $this->tools->searchDocumentation('the', null, 'content', 5, 5);
        $this->assertIsString($page2);

        // Results should be different between pages
        if (! str_contains($page1, 'No results found') && ! str_contains($page2, 'No results found')) {
            // Extract result numbers from both pages
            preg_match('/Results: Showing (\d+)-(\d+)/', $page1, $matches1);
            preg_match('/Results: Showing (\d+)-(\d+)/', $page2, $matches2);

            if (! empty($matches1) && ! empty($matches2)) {
                $this->assertNotEquals($matches1[1], $matches2[1]);
            }
        }
    }

    public function testSearchCodeExamplesFindsRealExamples(): void
    {
        $result = $this->tools->searchCodeExamples('new Client', 'php', true, 3);

        $this->assertIsString($result);
        $this->assertStringContainsString('## Code Examples', $result);
        $this->assertStringContainsString('**Search:** `new Client`', $result);
        $this->assertStringContainsString('**Language:** php', $result);

        // If examples are found, they should be formatted as code blocks
        if (! str_contains($result, 'No code examples found')) {
            $this->assertMatchesRegularExpression('/```\w*\n/', $result);
            $this->assertStringContainsString('### Example', $result);
        }
    }

    public function testSearchCodeExamplesWithContext(): void
    {
        $result = $this->tools->searchCodeExamples('check', null, true, 2);

        $this->assertIsString($result);

        // If examples are found with context
        if (! str_contains($result, 'No code examples found') && str_contains($result, '**Context:**')) {
            $this->assertStringContainsString('**Context:**', $result);
            $this->assertStringContainsString('*(see code below)*', $result);
        }
    }

    public function testSearchDocumentationFindsRealContent(): void
    {
        $result = $this->tools->searchDocumentation('check', null, 'content', 5);

        $this->assertIsString($result);
        $this->assertStringContainsString('## Documentation Search Results', $result);
        $this->assertStringContainsString('**Query:** `check`', $result);

        // Should find actual documentation about the check method
        if (! str_contains($result, 'No results found')) {
            $this->assertStringContainsString('Results:', $result);
            // Should have numbered results
            $this->assertMatchesRegularExpression('/### \d+\./', $result);
        }
    }

    public function testSearchDocumentationWithSdkFilter(): void
    {
        $result = $this->tools->searchDocumentation('Client', 'php', 'class', 10);

        $this->assertIsString($result);
        $this->assertStringContainsString('**SDK Filter:** php', $result);
        $this->assertStringContainsString('**Search Type:** class', $result);

        // If PHP SDK docs exist, should find Client class
        if (! str_contains($result, 'No results found')) {
            // Results should be from PHP SDK only
            $this->assertStringContainsString('php', strtolower($result));
        }
    }

    public function testSearchPerformanceIsReasonable(): void
    {
        $startTime = microtime(true);

        // Perform multiple searches
        $this->tools->searchDocumentation('authorization', null, 'content', 10);
        $this->tools->searchCodeExamples('client', 'php', true, 5);
        $this->tools->findSimilarDocumentation('OpenFGA tuples and relationships', null, 0.5, 5);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // All three searches should complete in reasonable time (< 5 seconds)
        $this->assertLessThan(5.0, $duration, 'Documentation searches should be fast');
    }

    public function testSimilarityThresholdAffectsResults(): void
    {
        $content = 'OpenFGA authorization and permission checking';

        // Low threshold should find more results
        $lowThreshold = $this->tools->findSimilarDocumentation($content, null, 0.1, 10);
        preg_match_all('/### \d+\./', $lowThreshold, $lowMatches);
        $lowCount = count($lowMatches[0] ?? []);

        // High threshold should find fewer results
        $highThreshold = $this->tools->findSimilarDocumentation($content, null, 0.8, 10);
        preg_match_all('/### \d+\./', $highThreshold, $highMatches);
        $highCount = count($highMatches[0] ?? []);

        // Low threshold should find same or more results than high threshold
        $this->assertGreaterThanOrEqual($highCount, $lowCount);
    }
}
