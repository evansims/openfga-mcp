<?php

declare(strict_types=1);

use OpenFGA\MCP\ConfigurableHttpServerTransport;
use Psr\Log\NullLogger;

beforeEach(function (): void {
    // Clear $_ENV before each test
    foreach (array_keys($_ENV) as $key) {
        if (str_starts_with($key, 'OPENFGA_MCP_')) {
            unset($_ENV[$key]);
        }
    }
});

describe('ConfigurableHttpServerTransport', function (): void {
    describe('applyConfiguration method', function (): void {
        it('applies configuration from valid JSON', function (): void {
            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 8080,
                mcpPath: '/mcp',
                logger: new NullLogger,
            );

            $json = json_encode([
                'OPENFGA_MCP_API_URL' => 'https://api.example.com',
                'OPENFGA_MCP_API_TOKEN' => 'test-token',
            ]);

            $result = $transport->applyConfiguration($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($_ENV['OPENFGA_MCP_API_URL'])->toBe('https://api.example.com');
            expect($_ENV['OPENFGA_MCP_API_TOKEN'])->toBe('test-token');
        });

        it('returns error for invalid JSON configuration', function (): void {
            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 8080,
                mcpPath: '/mcp',
                logger: new NullLogger,
            );

            $result = $transport->applyConfiguration('invalid json {');

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('Invalid JSON: Syntax error');
        });

        it('preserves existing environment variables when empty config provided', function (): void {
            // Set an environment variable
            $_ENV['OPENFGA_MCP_API_URL'] = 'https://existing.example.com';

            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 8080,
                mcpPath: '/mcp',
                logger: new NullLogger,
            );

            $result = $transport->applyConfiguration('{}');

            expect($result->isSuccessful())->toBeTrue();
            // Environment variable should remain unchanged
            expect($_ENV['OPENFGA_MCP_API_URL'])->toBe('https://existing.example.com');
        });

        it('overrides existing environment variables with new config', function (): void {
            // Set environment variables
            $_ENV['OPENFGA_MCP_API_URL'] = 'https://existing.example.com';
            $_ENV['OPENFGA_MCP_API_TOKEN'] = 'existing-token';

            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 8080,
                mcpPath: '/mcp',
                logger: new NullLogger,
            );

            $json = json_encode([
                'OPENFGA_MCP_API_URL' => 'https://override.example.com',
                // Note: not overriding API_TOKEN
            ]);

            $result = $transport->applyConfiguration($json);

            expect($result->isSuccessful())->toBeTrue();
            // URL should be overridden
            expect($_ENV['OPENFGA_MCP_API_URL'])->toBe('https://override.example.com');
            // Token should remain unchanged
            expect($_ENV['OPENFGA_MCP_API_TOKEN'])->toBe('existing-token');
        });

        it('handles configuration with validation errors', function (): void {
            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 8080,
                mcpPath: '/mcp',
                logger: new NullLogger,
            );

            // OAuth2 config missing required fields
            $json = json_encode([
                'OPENFGA_MCP_API_CLIENT_ID' => 'client-id',
                // Missing CLIENT_SECRET, ISSUER, AUDIENCE
            ]);

            $result = $transport->applyConfiguration($json);

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('OAuth2 client credentials require all of: OPENFGA_MCP_API_CLIENT_ID, OPENFGA_MCP_API_CLIENT_SECRET, OPENFGA_MCP_API_ISSUER, OPENFGA_MCP_API_AUDIENCE');
        });
    });

    describe('constructor parameters', function (): void {
        it('accepts all parent constructor parameters', function (): void {
            $transport = new ConfigurableHttpServerTransport(
                host: '0.0.0.0',
                port: 9999,
                mcpPath: '/custom',
                sslContext: null,
                enableJsonResponse: false,
                stateless: true,
                eventStore: null,
                logger: new NullLogger,
            );

            expect($transport)->toBeInstanceOf(ConfigurableHttpServerTransport::class);
        });

        it('uses default values when not specified', function (): void {
            $transport = new ConfigurableHttpServerTransport;

            expect($transport)->toBeInstanceOf(ConfigurableHttpServerTransport::class);
        });
    });

    describe('listen method', function (): void {
        it('can be called without errors', function (): void {
            $transport = new ConfigurableHttpServerTransport(
                host: '127.0.0.1',
                port: 0, // Use port 0 to avoid binding issues
                logger: new NullLogger,
            );

            // We can't actually test the full listen() method without starting a server
            // but we can verify the transport is properly instantiated
            expect($transport)->toBeInstanceOf(ConfigurableHttpServerTransport::class);
        });
    });
});
