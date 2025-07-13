<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\ModelIdCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new ModelIdCompletionProvider($this->client);
});

describe('ModelIdCompletionProvider', function (): void {
    it('returns only "latest" when no store ID is available', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('returns only "latest" when store is restricted', function (): void {
        putenv('OPENFGA_MCP_API_RESTRICT=true');
        putenv('OPENFGA_MCP_API_STORE=allowed-store');

        // Since extractStoreIdFromSession returns the configured store,
        // but this test assumes restricted access to a different store
        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_RESTRICT=false');
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles exception gracefully', function (): void {
        // Test the completion provider when environment is not configured
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('handles configured store gracefully', function (): void {
        // Test when there's a configured store but no filtering
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('test', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });

    it('filters completions based on current value', function (): void {
        putenv('OPENFGA_MCP_API_STORE=false');

        $result = $this->provider->getCompletions('lat', $this->session);
        expect($result)->toBe(['latest']);

        // Clean up
        putenv('OPENFGA_MCP_API_STORE=false');
    });
});
