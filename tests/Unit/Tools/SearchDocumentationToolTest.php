<?php

declare(strict_types=1);

use OpenFGA\MCP\Tools\SearchDocumentationTool;

beforeEach(function (): void {
    // Set up online mode for unit tests
    putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

    $this->tool = new SearchDocumentationTool;
});

afterEach(function (): void {
    Mockery::close();
    putenv('OPENFGA_MCP_API_URL=');
});

describe('searchDocumentation tool', function (): void {
    it('requires query parameter', function (): void {
        $result = $this->tool->searchDocumentation('');

        expect($result)->toBeArray();
        expect($result[0])->toBe('❌ Search query is required');
        expect($result)->toHaveKey('usage');
        expect($result)->toHaveKey('examples');
        expect($result['usage']['query'])->toContain('required');
    });

    it('performs basic content search', function (): void {
        $result = $this->tool->searchDocumentation('test_search_query');

        expect($result)->toBeArray();
        expect($result[0])->toBeString(); // Should have status message
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('test_search_query');
        expect($result)->toHaveKey('search_type');
        expect($result['search_type'])->toBe('content');
    });

    it('filters search by SDK', function (): void {
        $result = $this->tool->searchDocumentation('client', 'php');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('sdk_filter');
        expect($result['sdk_filter'])->toBe('php');
    });

    it('limits search results', function (): void {
        $result = $this->tool->searchDocumentation('test', null, 3);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('test');

        if (isset($result['results']) && [] !== $result['results']) {
            expect(count($result['results']))->toBeLessThanOrEqual(3);
        }
    });

    it('caps limit at maximum', function (): void {
        $result = $this->tool->searchDocumentation('test', null, 100); // Above maximum

        expect($result)->toBeArray();
        expect($result)->toHaveKey('query');

        if (isset($result['results']) && [] !== $result['results']) {
            expect(count($result['results']))->toBeLessThanOrEqual(50);
        }
    });

    it('performs class search', function (): void {
        $result = $this->tool->searchDocumentation('Client', null, 10, 'class');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('search_type');
        expect($result['search_type'])->toBe('class');
    });

    it('performs method search', function (): void {
        $result = $this->tool->searchDocumentation('check', null, 10, 'method');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('search_type');
        expect($result['search_type'])->toBe('method');
    });

    it('performs section search', function (): void {
        $result = $this->tool->searchDocumentation('introduction', null, 10, 'section');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('search_type');
        expect($result['search_type'])->toBe('section');
    });
});

describe('searchCodeExamples tool', function (): void {
    it('requires query parameter', function (): void {
        $result = $this->tool->searchCodeExamples('');

        expect($result[0])->toBe('❌ Search query is required');
        expect($result)->toHaveKey('usage');
        expect($result)->toHaveKey('examples');
    });

    it('searches for code examples', function (): void {
        $result = $this->tool->searchCodeExamples('createStore', 'php');

        expect($result)->toBeArray();
        expect($result[0])->toBeString();
        expect($result)->toHaveKey('query');
        expect($result['query'])->toBe('createStore');
        expect($result)->toHaveKey('language_filter');
        expect($result['language_filter'])->toBe('php');
        expect($result)->toHaveKey('total_examples');
        expect($result)->toHaveKey('examples');
        expect($result['examples'])->toBeArray();
    });

    it('filters by programming language', function (): void {
        $result = $this->tool->searchCodeExamples('client', 'javascript');

        expect($result['language_filter'])->toBe('javascript');
    });
});

describe('findSimilarDocumentation tool', function (): void {
    it('requires reference text parameter', function (): void {
        $result = $this->tool->findSimilarDocumentation('');

        expect($result[0])->toBe('❌ Reference text is required');
        expect($result)->toHaveKey('usage');
        expect($result)->toHaveKey('examples');
    });

    it('finds similar documentation', function (): void {
        $result = $this->tool->findSimilarDocumentation(
            'This shows how to create a new store with proper configuration',
        );

        expect($result)->toBeArray();
        expect($result[0])->toBeString();
        expect($result)->toHaveKey('reference_text');
        expect($result['reference_text'])->toContain('This shows how to create');

        if (isset($result['total_results'])) {
            expect($result)->toHaveKey('results');
            expect($result['results'])->toBeArray();
        }
    });

    it('filters by minimum similarity score', function (): void {
        $result = $this->tool->findSimilarDocumentation(
            'Some reference text',
            null,
            5,
            0.8, // High threshold
        );

        expect($result)->toBeArray();
        expect($result)->toHaveKey('reference_text');

        if (isset($result['results']) && [] !== $result['results']) {
            foreach ($result['results'] as $item) {
                expect($item['similarity_score'])->toBeGreaterThanOrEqual(0.8);
            }
        }
    });
});

describe('offline mode behavior', function (): void {
    it('works normally in offline mode', function (): void {
        putenv('OPENFGA_MCP_API_URL='); // Clear to simulate offline mode

        $result = $this->tool->searchDocumentation('test');

        // Search tools should work in offline mode (they access local documentation)
        expect($result)->toBeArray();
        expect($result[0])->toBeString();
    });
});

describe('error handling', function (): void {
    it('handles various input parameters gracefully', function (): void {
        $testCases = [
            ['query' => 'test', 'sdk' => 'php'],
            ['query' => 'test', 'limit' => 5],
            ['query' => 'test', 'search_type' => 'class'],
        ];

        foreach ($testCases as $params) {
            $query = $params['query'] ?? '';
            $sdk = $params['sdk'] ?? null;
            $limit = $params['limit'] ?? 10;
            $searchType = $params['search_type'] ?? 'content';

            $result = $this->tool->searchDocumentation($query, $sdk, $limit, $searchType);

            expect($result)->toBeArray();
            expect($result[0])->toBeString();
        }
    });
});
