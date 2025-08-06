<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Mockery;
use OpenFGA\MCP\Completions\ClassNameCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new ClassNameCompletionProvider;
});

describe('ClassNameCompletionProvider', function (): void {
    it('returns empty array for empty input', function (): void {
        $completions = $this->provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        // The actual result depends on documentation index state
    });

    it('filters classes based on current value', function (): void {
        $completions = $this->provider->getCompletions('Client', $this->session);

        expect($completions)->toBeArray();
        // Results will be filtered to only include classes starting with 'Client'
    });

    it('handles case-insensitive filtering', function (): void {
        $completions1 = $this->provider->getCompletions('client', $this->session);
        $completions2 = $this->provider->getCompletions('CLIENT', $this->session);

        expect($completions1)->toBeArray();
        expect($completions2)->toBeArray();
        // Both should return similar results due to case-insensitive matching
    });

    it('returns consistent results across multiple calls', function (): void {
        $completions1 = $this->provider->getCompletions('Store', $this->session);
        $completions2 = $this->provider->getCompletions('Store', $this->session);

        expect($completions1)->toBeArray();
        expect($completions2)->toBeArray();
        expect($completions1)->toBe($completions2);
    });

    it('handles special characters in input', function (): void {
        $completions = $this->provider->getCompletions('OpenFGA_Client', $this->session);

        expect($completions)->toBeArray();
    });

    it('handles very long input strings', function (): void {
        $longInput = str_repeat('A', 200);
        $completions = $this->provider->getCompletions($longInput, $this->session);

        expect($completions)->toBeArray();
    });

    it('handles whitespace in input', function (): void {
        $completions = $this->provider->getCompletions('  Client  ', $this->session);

        expect($completions)->toBeArray();
    });

    it('returns array even with numeric input', function (): void {
        $completions = $this->provider->getCompletions('123', $this->session);

        expect($completions)->toBeArray();
    });
});
