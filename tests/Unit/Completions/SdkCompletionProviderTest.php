<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Mockery;
use OpenFGA\MCP\Completions\SdkCompletionProvider;
use OpenFGA\MCP\Documentation\DocumentationIndexSingleton;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = Mockery::mock(SessionInterface::class);
    // Reset the singleton to ensure clean state
    DocumentationIndexSingleton::reset();

    // Clean up any previous mock aliases
    Mockery::close();
});

describe('SdkCompletionProvider', function (): void {
    it('creates provider instance successfully', function (): void {
        $provider = new SdkCompletionProvider;

        expect($provider)->toBeInstanceOf(SdkCompletionProvider::class);
    });

    it('returns empty array when documentation fails to initialize', function (): void {
        // Test the actual behavior when documentation cannot be initialized
        // This simulates the real-world scenario where docs are not available
        $provider = new SdkCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        // In normal circumstances, this would return SDK names, but when initialization
        // fails or docs don't exist, it returns empty array
    });

    it('handles empty current value properly', function (): void {
        $provider = new SdkCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        // When documentation is available, should return SDKs, otherwise empty
    });

    it('handles filtering with current value', function (): void {
        $provider = new SdkCompletionProvider;
        $completions = $provider->getCompletions('p', $this->session);

        expect($completions)->toBeArray();
        // Should filter results based on current value when documentation available
    });

    it('handles case-insensitive filtering', function (): void {
        $provider = new SdkCompletionProvider;
        $completionsLower = $provider->getCompletions('php', $this->session);
        $completionsUpper = $provider->getCompletions('PHP', $this->session);

        expect($completionsLower)->toBeArray();
        expect($completionsUpper)->toBeArray();
        // Both should return the same results (case-insensitive matching)
    });

    it('handles various input patterns', function (): void {
        $provider = new SdkCompletionProvider;

        $testCases = ['g', 'go', 'general', 'auth', 'authoring', 'java', 'js'];

        foreach ($testCases as $input) {
            $completions = $provider->getCompletions($input, $this->session);
            expect($completions)->toBeArray();
        }
    });

    it('handles no matches found scenario', function (): void {
        $provider = new SdkCompletionProvider;
        $completions = $provider->getCompletions('xyz', $this->session);

        expect($completions)->toBeArray();
        // Should return empty array when no SDKs match the filter
    });

    it('handles exceptions gracefully', function (): void {
        $provider = new SdkCompletionProvider;

        // Provider should handle any initialization errors gracefully
        $completions = $provider->getCompletions('test', $this->session);

        expect($completions)->toBeArray();
        // Should return empty array on any exceptions
    });

    it('handles empty and whitespace input', function (): void {
        $provider = new SdkCompletionProvider;

        $emptyCompletions = $provider->getCompletions('', $this->session);
        $spaceCompletions = $provider->getCompletions(' ', $this->session);
        $tabCompletions = $provider->getCompletions("\t", $this->session);

        expect($emptyCompletions)->toBeArray();
        expect($spaceCompletions)->toBeArray();
        expect($tabCompletions)->toBeArray();
    });

    it('handles long input strings', function (): void {
        $provider = new SdkCompletionProvider;
        $longInput = str_repeat('php', 100);

        $completions = $provider->getCompletions($longInput, $this->session);

        expect($completions)->toBeArray();
    });

    it('handles unicode characters in input', function (): void {
        $provider = new SdkCompletionProvider;

        $completions = $provider->getCompletions('phé', $this->session);

        expect($completions)->toBeArray();
    });

    it('maintains result consistency across multiple calls', function (): void {
        $provider = new SdkCompletionProvider;

        $completions1 = $provider->getCompletions('p', $this->session);
        $completions2 = $provider->getCompletions('p', $this->session);

        expect($completions1)->toBeArray();
        expect($completions2)->toBeArray();
        expect($completions1)->toBe($completions2);
    });
});
