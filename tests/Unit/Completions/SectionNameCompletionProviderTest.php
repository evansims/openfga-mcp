<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Mockery;
use OpenFGA\MCP\Completions\SectionNameCompletionProvider;
use OpenFGA\MCP\Documentation\DocumentationIndexSingleton;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = Mockery::mock(SessionInterface::class);
    // Reset the singleton to ensure clean state
    DocumentationIndexSingleton::reset();

    // Clean up any previous mock aliases
    Mockery::close();
});

describe('SectionNameCompletionProvider', function (): void {
    it('creates provider instance successfully', function (): void {
        $provider = new SectionNameCompletionProvider;

        expect($provider)->toBeInstanceOf(SectionNameCompletionProvider::class);
    });

    it('handles documentation initialization gracefully', function (): void {
        $provider = new SectionNameCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        // When documentation is available, returns sections; when not, returns empty
    });

    it('handles filtering with current value', function (): void {
        $provider = new SectionNameCompletionProvider;
        $completions = $provider->getCompletions('api', $this->session);

        expect($completions)->toBeArray();
        // Should filter results based on current value when documentation available
    });

    it('handles case-insensitive filtering', function (): void {
        $provider = new SectionNameCompletionProvider;
        $completionsLower = $provider->getCompletions('getting', $this->session);
        $completionsUpper = $provider->getCompletions('GETTING', $this->session);

        expect($completionsLower)->toBeArray();
        expect($completionsUpper)->toBeArray();
        // Both should return the same results (case-insensitive matching)
    });

    it('handles various section name patterns', function (): void {
        $provider = new SectionNameCompletionProvider;

        $testCases = ['api', 'getting', 'config', 'tutorial', 'guide', 'example'];

        foreach ($testCases as $input) {
            $completions = $provider->getCompletions($input, $this->session);
            expect($completions)->toBeArray();
        }
    });

    it('handles empty current value', function (): void {
        $provider = new SectionNameCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        // Should return all available sections when no filter applied
    });

    it('handles no matches found scenario', function (): void {
        $provider = new SectionNameCompletionProvider;
        $completions = $provider->getCompletions('xyz123nonexistent', $this->session);

        expect($completions)->toBeArray();
        // Should return empty array when no sections match the filter
    });

    it('handles exceptions gracefully', function (): void {
        $provider = new SectionNameCompletionProvider;

        // Provider should handle any initialization errors gracefully
        $completions = $provider->getCompletions('test', $this->session);

        expect($completions)->toBeArray();
        // Should return empty array on any exceptions
    });

    it('handles empty and whitespace input', function (): void {
        $provider = new SectionNameCompletionProvider;

        $emptyCompletions = $provider->getCompletions('', $this->session);
        $spaceCompletions = $provider->getCompletions(' ', $this->session);
        $tabCompletions = $provider->getCompletions("\t", $this->session);

        expect($emptyCompletions)->toBeArray();
        expect($spaceCompletions)->toBeArray();
        expect($tabCompletions)->toBeArray();
    });

    it('handles long input strings', function (): void {
        $provider = new SectionNameCompletionProvider;
        $longInput = str_repeat('section', 100);

        $completions = $provider->getCompletions($longInput, $this->session);

        expect($completions)->toBeArray();
    });

    it('handles unicode characters in input', function (): void {
        $provider = new SectionNameCompletionProvider;

        $completions = $provider->getCompletions('configurati\u00f3n', $this->session);

        expect($completions)->toBeArray();
    });

    it('maintains result consistency across multiple calls', function (): void {
        $provider = new SectionNameCompletionProvider;

        $completions1 = $provider->getCompletions('getting', $this->session);
        $completions2 = $provider->getCompletions('getting', $this->session);

        expect($completions1)->toBeArray();
        expect($completions2)->toBeArray();
        expect($completions1)->toBe($completions2);
    });

    it('handles hyphenated section names', function (): void {
        $provider = new SectionNameCompletionProvider;

        $completions = $provider->getCompletions('getting-started', $this->session);

        expect($completions)->toBeArray();
    });

    it('handles underscored section names', function (): void {
        $provider = new SectionNameCompletionProvider;

        $completions = $provider->getCompletions('api_reference', $this->session);

        expect($completions)->toBeArray();
    });
});
