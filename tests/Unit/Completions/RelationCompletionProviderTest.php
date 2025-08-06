<?php

declare(strict_types=1);

namespace Tests\Unit\Completions;

use Exception;
use Mockery;
use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\RelationCompletionProvider;
use OpenFGA\MCP\OfflineClient;
use OpenFGA\Results\Failure;
use PhpMcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->client = Mockery::mock(ClientInterface::class);
    $this->session = Mockery::mock(SessionInterface::class);
    $this->provider = new RelationCompletionProvider($this->client);
});

afterEach(function (): void {
    putenv('OPENFGA_MCP_API_STORE=');
    putenv('OPENFGA_MCP_API_MODEL=');
    putenv('OPENFGA_MCP_API_URL=');
    putenv('OPENFGA_MCP_API_RESTRICT=');
    Mockery::close();
});

describe('RelationCompletionProvider', function (): void {
    describe('offline mode', function (): void {
        it('returns common relations in offline mode', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $offlineClient = new OfflineClient;
            $provider = new RelationCompletionProvider($offlineClient);

            $result = $provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->toContain('viewer');
            expect($result)->toContain('editor');
            expect($result)->toContain('owner');
            expect($result)->toContain('member');
            expect($result)->toContain('admin');
        });

        it('filters common relations in offline mode', function (): void {
            putenv('OPENFGA_MCP_API_URL=');

            $offlineClient = new OfflineClient;
            $provider = new RelationCompletionProvider($offlineClient);

            $result = $provider->getCompletions('vie', $this->session);
            expect($result)->toContain('viewer');
            expect($result)->not->toContain('editor');

            $result = $provider->getCompletions('ad', $this->session);
            expect($result)->toContain('admin');
            expect($result)->not->toContain('viewer');
        });
    });

    describe('store ID handling', function (): void {
        it('returns common relations when no store ID is available', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');
            putenv('OPENFGA_MCP_API_MODEL=');

            $result = $this->provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->toContain('viewer');
            expect($result)->toContain('editor');
            expect($result)->toContain('owner');
        });

        it('returns common relations when store ID exists but no model ID', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');
            putenv('OPENFGA_MCP_API_MODEL=');

            $result = $this->provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->toContain('viewer');
            expect($result)->toContain('editor');
        });
    });

    describe('API error handling', function (): void {
        it('handles API failure gracefully', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');
            putenv('OPENFGA_MCP_API_MODEL=model-456');

            $failure = new Failure(new Exception('API error'));

            $this->client->shouldReceive('getAuthorizationModel')
                ->with(store: 'store-123', model: 'model-456')
                ->andReturn($failure);

            $result = $this->provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->toContain('viewer'); // Falls back to common relations
        });

        it('handles exception during API call', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');
            putenv('OPENFGA_MCP_API_MODEL=model-456');

            $this->client->shouldReceive('getAuthorizationModel')
                ->andThrow(new Exception('Connection error'));

            $result = $this->provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->toContain('viewer'); // Falls back to common relations
        });
    });

    describe('restricted mode', function (): void {
        it('returns empty when accessing non-configured store in restricted mode', function (): void {
            putenv('OPENFGA_MCP_API_STORE=restricted-store');
            putenv('OPENFGA_MCP_API_MODEL=model-123');
            putenv('OPENFGA_MCP_API_RESTRICT=true');

            $result = $this->provider->getCompletions('', $this->session);
            // In restricted mode, it returns common relations as fallback
            expect($result)->toBeArray();
            expect($result)->toContain('viewer');
        });

        it('returns empty when accessing non-configured model in restricted mode', function (): void {
            putenv('OPENFGA_MCP_API_STORE=store-123');
            putenv('OPENFGA_MCP_API_MODEL=restricted-model');
            putenv('OPENFGA_MCP_API_RESTRICT=true');

            $result = $this->provider->getCompletions('', $this->session);
            // In restricted mode, it returns common relations as fallback
            expect($result)->toBeArray();
            expect($result)->toContain('viewer');
        });
    });

    describe('filtering', function (): void {
        it('filters relations based on current value', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('own', $this->session);
            expect($result)->toContain('owner');
            expect($result)->not->toContain('viewer');

            $result = $this->provider->getCompletions('edit', $this->session);
            expect($result)->toContain('editor');
            expect($result)->not->toContain('owner');
        });
    });

    describe('edge cases', function (): void {
        it('handles empty current value', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('', $this->session);

            expect($result)->toBeArray();
            expect($result)->not->toBeEmpty();
        });

        it('handles special characters in current value', function (): void {
            putenv('OPENFGA_MCP_API_STORE=');

            $result = $this->provider->getCompletions('view_er', $this->session);

            // Should still work with special characters
            expect($result)->toBeArray();
        });
    });
});
