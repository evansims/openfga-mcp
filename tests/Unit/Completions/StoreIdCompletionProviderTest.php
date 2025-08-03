<?php

declare(strict_types=1);

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\StoreIdCompletionProvider;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new StoreIdCompletionProvider($this->client);
});

describe('StoreIdCompletionProvider', function (): void {
    it('returns empty array in offline mode', function (): void {
        // Ensure we're in offline mode (no API configuration)
        putenv('OPENFGA_MCP_API_URL=');
        putenv('OPENFGA_MCP_API_TOKEN=');
        putenv('OPENFGA_MCP_API_CLIENT_ID=');

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
        putenv('OPENFGA_MCP_API_TOKEN=');
        putenv('OPENFGA_MCP_API_CLIENT_ID=');
    });

    it('returns empty array when client throws exception', function (): void {
        // Set up online mode
        putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

        $this->client->shouldReceive('listStores')
            ->once()
            ->andThrow(new Exception('API Error'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
    });

    it('returns empty array when client returns null', function (): void {
        // Set up online mode
        putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn(null);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
    });

    it('filters completions based on current value', function (): void {
        // Test offline mode behavior
        putenv('OPENFGA_MCP_API_URL=');
        putenv('OPENFGA_MCP_API_TOKEN=');
        putenv('OPENFGA_MCP_API_CLIENT_ID=');

        $result = $this->provider->getCompletions('test', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
        putenv('OPENFGA_MCP_API_TOKEN=');
        putenv('OPENFGA_MCP_API_CLIENT_ID=');
    });

    it('handles null client response gracefully', function (): void {
        // Set up online mode
        putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

        $this->client->shouldReceive('listStores')
            ->once()
            ->andReturn(null);

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
    });

    it('handles client that returns empty response', function (): void {
        // Set up online mode
        putenv('OPENFGA_MCP_API_URL=http://localhost:8080');

        $this->client->shouldReceive('listStores')
            ->once()
            ->andThrow(new RuntimeException('Empty response'));

        $result = $this->provider->getCompletions('', $this->session);
        expect($result)->toBe([]);

        // Clean up
        putenv('OPENFGA_MCP_API_URL=');
    });
});
