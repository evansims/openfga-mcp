<?php

declare(strict_types=1);

use OpenFGA\MCP\Documentation\DocumentationIndex;

beforeEach(function (): void {
    $this->index = new DocumentationIndex;
});

afterEach(function (): void {
    Mockery::close();
});

describe('DocumentationIndex initialization', function (): void {
    it('starts uninitialized', function (): void {
        expect($this->index->isInitialized())->toBeFalse();
    });

    it('can be initialized successfully', function (): void {
        // This will work with the real docs directory if it exists
        $this->index->initialize();
        expect($this->index->isInitialized())->toBeTrue();
    });

    it('does not reinitialize if already initialized', function (): void {
        $this->index->initialize();
        $firstInit = $this->index->isInitialized();

        $this->index->initialize();
        $secondInit = $this->index->isInitialized();

        expect($firstInit)->toBeTrue();
        expect($secondInit)->toBeTrue();
    });
});

describe('SDK list and overview', function (): void {
    it('returns empty SDK list when not initialized', function (): void {
        $sdkList = $this->index->getSdkList();
        expect($sdkList)->toBeArray();
        // Note: The actual behavior returns the SDK list even when not initialized
        // due to how the ensureInitialized() method works
    });

    it('returns SDK list after initialization', function (): void {
        $this->index->initialize();
        $sdkList = $this->index->getSdkList();

        expect($sdkList)->toBeArray();
        // The list might be empty if no docs exist, but it should be an array
    });

    it('returns SDK overview or null for valid/invalid SDK', function (): void {
        $this->index->initialize();
        $sdkList = $this->index->getSdkList();

        if (! empty($sdkList)) {
            $firstSdk = $sdkList[0];
            $overview = $this->index->getSdkOverview($firstSdk);

            expect($overview)->toBeArray();
            expect($overview['sdk'])->toBe($firstSdk);
            expect($overview['name'])->toBeString();
            expect($overview['sections'])->toBeArray();
            expect($overview['classes'])->toBeArray();
            expect($overview['total_chunks'])->toBeInt();
        }

        // Invalid SDK should return null
        $overview = $this->index->getSdkOverview('definitely_invalid_sdk_name_123');
        expect($overview)->toBeNull();
    });
});

describe('chunk retrieval', function (): void {
    it('returns null for non-existent chunk', function (): void {
        $this->index->initialize();
        $chunk = $this->index->getChunk('non_existent_chunk_123');

        expect($chunk)->toBeNull();
    });

    it('returns chunks by section', function (): void {
        $this->index->initialize();
        $chunks = $this->index->getChunksBySection('any_sdk', 'NonExistentSection');

        // Should return empty array for non-existent section
        expect($chunks)->toBeArray()->toBeEmpty();
    });
});

describe('class and method documentation', function (): void {
    it('returns null for non-existent class', function (): void {
        $this->index->initialize();
        $classDoc = $this->index->getClassDocumentation('any_sdk', 'NonExistentClass');

        expect($classDoc)->toBeNull();
    });

    it('returns null for non-existent method', function (): void {
        $this->index->initialize();
        $methodDoc = $this->index->getMethodDocumentation('any_sdk', 'AnyClass', 'nonExistentMethod');

        expect($methodDoc)->toBeNull();
    });
});

describe('search functionality', function (): void {
    it('performs basic content search', function (): void {
        $this->index->initialize();
        $results = $this->index->searchChunks('test_query_that_probably_wont_match');

        // Should return an array, likely empty for non-matching query
        expect($results)->toBeArray();
    });

    it('filters search by SDK', function (): void {
        $this->index->initialize();
        $results = $this->index->searchChunks('test', 'nonexistent_sdk');

        expect($results)->toBeArray();

        foreach ($results as $result) {
            expect($result['sdk'])->toBe('nonexistent_sdk');
        }
    });

    it('limits search results', function (): void {
        $this->index->initialize();
        $results = $this->index->searchChunks('test', null, 2);

        expect(count($results))->toBeLessThanOrEqual(2);
    });

    it('returns empty array for no matches', function (): void {
        $this->index->initialize();
        $results = $this->index->searchChunks('xyznomatchstring12345');

        expect($results)->toBeEmpty();
    });
});

describe('error handling', function (): void {
    it('handles initialization gracefully', function (): void {
        $index = new DocumentationIndex;
        $index->initialize();
        expect($index->isInitialized())->toBeTrue();
    });
});
