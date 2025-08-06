<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Mockery;
use OpenFGA\MCP\Completions\ChunkIdCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new ChunkIdCompletionProvider;
});

describe('ChunkIdCompletionProvider', function (): void {
    it('returns empty array when no chunks available', function (): void {
        $completions = $this->provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('returns empty array with current value filtering', function (): void {
        $completions = $this->provider->getCompletions('test', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('handles various input values gracefully', function (): void {
        // Test with empty string
        $completions = $this->provider->getCompletions('', $this->session);
        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();

        // Test with short string
        $completions = $this->provider->getCompletions('a', $this->session);
        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();

        // Test with long string
        $completions = $this->provider->getCompletions(str_repeat('a', 100), $this->session);
        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('returns consistent results across multiple calls', function (): void {
        $completions1 = $this->provider->getCompletions('test', $this->session);
        $completions2 = $this->provider->getCompletions('test', $this->session);

        expect($completions1)->toBe($completions2);
    });

    it('handles special characters in input', function (): void {
        $completions = $this->provider->getCompletions('test-chunk_123', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });
});
