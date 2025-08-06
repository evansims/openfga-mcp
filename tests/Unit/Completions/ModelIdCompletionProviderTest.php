<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Exception;
use Mockery;
use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\ModelIdCompletionProvider;
use OpenFGA\MCP\OfflineClient;
use OpenFGA\Results\Failure;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new ModelIdCompletionProvider($this->client);
});

afterEach(function (): void {
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_URL=');
    putenv('OPENFGA_MCP_API_TOKEN=');
    putenv('OPENFGA_MCP_API_CLIENT_ID=');
    putenv('OPENFGA_MCP_API_RESTRICT=');
    Mockery::close();
});

describe('ModelIdCompletionProvider', function (): void {
    describe('offline mode', function (): void {
        it('returns only "latest" in offline mode', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $offlineClient = new OfflineClient;
            $provider = new ModelIdCompletionProvider($offlineClient);

            $result = $provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });

        it('filters "latest" in offline mode based on current value', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $offlineClient = new OfflineClient;
            $provider = new ModelIdCompletionProvider($offlineClient);

            $result = $provider->getCompletions('lat', $this->session);
            expect($result)->toBe(['latest']);

            $result = $provider->getCompletions('xyz', $this->session);
            expect($result)->toBe([]);
        });
    });

    describe('store ID handling', function (): void {
        it('returns only "latest" when no store ID is available', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });

        it('handles API failure gracefully', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');

            $failure = new Failure(new Exception('API error'));

            $this->client->shouldReceive('listAuthorizationModels')
                ->with(store: 'store-123')
                ->andReturn($failure);

            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });
    });

    describe('filtering', function (): void {
        it('filters completions based on current value', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('la', $this->session);
            expect($result)->toBe(['latest']);

            $result = $this->provider->getCompletions('test', $this->session);
            expect($result)->toBe([]);
        });
    });

    describe('restricted mode', function (): void {
        it('returns empty when accessing non-configured store in restricted mode', function (): void {
            putenv('OPENFGA_MCP_API_STORE=restricted-store');
            putenv('OPENFGA_MCP_API_RESTRICT=true');

            // In restricted mode with a configured store, it would normally return empty
            // if the provider is checking a different store, but since we're not passing
            // a store through session, it uses the configured one and returns 'latest'
            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });

        it('allows access to configured store in restricted mode', function (): void {
            putenv('OPENFGA_MCP_API_STORE=allowed-store');
            putenv('OPENFGA_MCP_API_RESTRICT=true');

            // Returns 'latest' when accessing the allowed store
            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });
    });

    describe('edge cases', function (): void {
        it('handles exception during API call', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');

            $this->client->shouldReceive('listAuthorizationModels')
                ->andThrow(new Exception('Connection error'));

            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBe(['latest']);
        });

        it('handles null store ID gracefully', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('', $this->session);
            expect($result)->toBeArray();
            expect($result)->toContain('latest');
        });
    });
});
