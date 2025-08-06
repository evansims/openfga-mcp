<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Mockery;
use OpenFGA\MCP\Completions\MethodNameCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = Mockery::mock(SessionInterface::class);
});

describe('MethodNameCompletionProvider', function (): void {
    it('always returns empty array due to lack of context', function (): void {
        // MethodNameCompletionProvider currently always returns empty array
        // because it cannot extract SDK and className from session context
        $provider = new MethodNameCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('returns empty array with any current value', function (): void {
        $provider = new MethodNameCompletionProvider;
        $completions = $provider->getCompletions('getSome', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('returns empty array with empty current value', function (): void {
        $provider = new MethodNameCompletionProvider;
        $completions = $provider->getCompletions('', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('handles various string inputs consistently', function (): void {
        $provider = new MethodNameCompletionProvider;

        // Test different string inputs - all should return empty arrays
        expect($provider->getCompletions('method', $this->session))->toBeEmpty();
        expect($provider->getCompletions('get', $this->session))->toBeEmpty();
        expect($provider->getCompletions('create', $this->session))->toBeEmpty();
        expect($provider->getCompletions('update', $this->session))->toBeEmpty();
    });

    it('maintains consistent behavior with special characters', function (): void {
        $provider = new MethodNameCompletionProvider;

        // Test special characters - all should return empty arrays
        expect($provider->getCompletions('_method', $this->session))->toBeEmpty();
        expect($provider->getCompletions('method123', $this->session))->toBeEmpty();
        expect($provider->getCompletions('method_name', $this->session))->toBeEmpty();
        expect($provider->getCompletions('method-name', $this->session))->toBeEmpty();
    });

    it('handles long input strings', function (): void {
        $provider = new MethodNameCompletionProvider;
        $longInput = str_repeat('method', 100);

        $completions = $provider->getCompletions($longInput, $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('handles unicode characters in input', function (): void {
        $provider = new MethodNameCompletionProvider;

        $completions = $provider->getCompletions('méthod', $this->session);

        expect($completions)->toBeArray();
        expect($completions)->toBeEmpty();
    });

    it('creates provider instance successfully', function (): void {
        $provider = new MethodNameCompletionProvider;

        expect($provider)->toBeInstanceOf(MethodNameCompletionProvider::class);
    });
});
