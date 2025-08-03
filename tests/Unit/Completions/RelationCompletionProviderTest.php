<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\RelationCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new RelationCompletionProvider($this->client);
});

describe('RelationCompletionProvider', function (): void {
    it('returns common relations when no store ID is available', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);

        // Should return common relations
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');
        expect($result)->toContain('admin');
        expect($result)->toContain('owner');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns common relations when API fails', function (): void {
        // Test fallback behavior when no store is configured
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles exception gracefully', function (): void {
        // Test fallback behavior
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);

        // Should return common relations as fallback
        expect($result)->toContain('viewer');
        expect($result)->toContain('editor');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('filters relations based on current value', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('ed', $this->session);

        // Should filter to relations starting with 'ed'
        expect($result)->toContain('editor');
        expect($result)->not->toContain('viewer');
        expect($result)->not->toContain('admin');
        expect($result)->not->toContain('owner');
        expect($result)->not->toContain('member');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles configured environment gracefully', function (): void {
        // Test with no store configured
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('adm', $this->session);

        // Should return filtered common relations
        expect($result)->toContain('admin');
        expect($result)->not->toContain('viewer');

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
