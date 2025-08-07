<?php

declare(strict_types=1);

use OpenFGA\MCP\ConfigurationParser;
use Psr\Log\NullLogger;

beforeEach(function (): void {
    // Clear $_ENV before each test
    foreach (array_keys($_ENV) as $key) {
        if (str_starts_with($key, 'OPENFGA_MCP_')) {
            unset($_ENV[$key]);
        }
    }
});

describe('ConfigurationParser', function (): void {
    describe('parseAndApply', function (): void {
        it('parses valid JSON configuration', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_URL' => 'https://api.example.com',
                'OPENFGA_MCP_API_TOKEN' => 'test-token',
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($result->getAppliedKeys())->toContain('OPENFGA_MCP_API_URL');
            expect($result->getAppliedKeys())->toContain('OPENFGA_MCP_API_TOKEN');
            expect($_ENV['OPENFGA_MCP_API_URL'])->toBe('https://api.example.com');
            expect($_ENV['OPENFGA_MCP_API_TOKEN'])->toBe('test-token');
        });

        it('handles invalid JSON gracefully', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $result = $parser->parseAndApply('invalid json {');

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('Invalid JSON: Syntax error');
            expect($result->getAppliedKeys())->toBeEmpty();
        });

        it('converts boolean values correctly', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_WRITEABLE' => true,
                'OPENFGA_MCP_DEBUG' => false,
                'OPENFGA_MCP_TRANSPORT_SSE' => 1,
                'OPENFGA_MCP_TRANSPORT_STATELESS' => '0',
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($_ENV['OPENFGA_MCP_API_WRITEABLE'])->toBe('true');
            expect($_ENV['OPENFGA_MCP_DEBUG'])->toBe('false');
            expect($_ENV['OPENFGA_MCP_TRANSPORT_SSE'])->toBe('true');
            expect($_ENV['OPENFGA_MCP_TRANSPORT_STATELESS'])->toBe('false');
        });

        it('converts integer values correctly', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_TRANSPORT_PORT' => 8080,
                'OPENFGA_MCP_TRANSPORT_HOST' => '127.0.0.1', // string should stay string
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($_ENV['OPENFGA_MCP_TRANSPORT_PORT'])->toBe('8080');
            expect($_ENV['OPENFGA_MCP_TRANSPORT_HOST'])->toBe('127.0.0.1');
        });

        it('ignores unsupported configuration keys silently', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_URL' => 'https://api.example.com',
                'UNSUPPORTED_KEY' => 'value',
                'ANOTHER_INVALID' => 123,
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($result->getAppliedKeys())->toContain('OPENFGA_MCP_API_URL');
            expect($result->getAppliedKeys())->not->toContain('UNSUPPORTED_KEY');
            expect($result->getAppliedKeys())->not->toContain('ANOTHER_INVALID');
            expect($_ENV)->toHaveKey('OPENFGA_MCP_API_URL');
            expect($_ENV)->not->toHaveKey('UNSUPPORTED_KEY');
        });

        it('validates OAuth2 configuration combinations', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_CLIENT_ID' => 'client-id',
                'OPENFGA_MCP_API_CLIENT_SECRET' => 'client-secret',
                // Missing ISSUER and AUDIENCE
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('OAuth2 client credentials require all of: OPENFGA_MCP_API_CLIENT_ID, OPENFGA_MCP_API_CLIENT_SECRET, OPENFGA_MCP_API_ISSUER, OPENFGA_MCP_API_AUDIENCE');
        });

        it('validates restricted mode configuration', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_RESTRICT' => true,
                'OPENFGA_MCP_API_STORE' => 'store-id',
                // Missing MODEL
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('Restricted mode requires both OPENFGA_MCP_API_STORE and OPENFGA_MCP_API_MODEL to be set');
        });

        it('accepts valid OAuth2 configuration', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_CLIENT_ID' => 'client-id',
                'OPENFGA_MCP_API_CLIENT_SECRET' => 'client-secret',
                'OPENFGA_MCP_API_ISSUER' => 'https://issuer.example.com',
                'OPENFGA_MCP_API_AUDIENCE' => 'https://api.example.com',
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($_ENV['OPENFGA_MCP_API_CLIENT_ID'])->toBe('client-id');
            expect($_ENV['OPENFGA_MCP_API_CLIENT_SECRET'])->toBe('client-secret');
            expect($_ENV['OPENFGA_MCP_API_ISSUER'])->toBe('https://issuer.example.com');
            expect($_ENV['OPENFGA_MCP_API_AUDIENCE'])->toBe('https://api.example.com');
        });

        it('accepts valid restricted mode configuration', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_RESTRICT' => true,
                'OPENFGA_MCP_API_STORE' => 'store-id',
                'OPENFGA_MCP_API_MODEL' => 'model-id',
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($_ENV['OPENFGA_MCP_API_RESTRICT'])->toBe('true');
            expect($_ENV['OPENFGA_MCP_API_STORE'])->toBe('store-id');
            expect($_ENV['OPENFGA_MCP_API_MODEL'])->toBe('model-id');
        });

        it('reports type validation errors', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_WRITEABLE' => ['not', 'a', 'boolean'],
                'OPENFGA_MCP_TRANSPORT_PORT' => 'not-a-number',
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('OPENFGA_MCP_API_WRITEABLE must be a boolean, array given');
            expect($result->getErrors())->toContain('OPENFGA_MCP_TRANSPORT_PORT must be numeric, string given');
        });

        it('handles empty configuration object', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
            expect($result->getAppliedKeys())->toBeEmpty();
        });

        it('rejects non-object JSON', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode('string value');

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeFalse();
            expect($result->hasErrors())->toBeTrue();
            expect($result->getErrors())->toContain('Configuration must be a JSON object');
        });

        it('masks sensitive values in applied values', function (): void {
            $parser = new ConfigurationParser(new NullLogger);

            $json = json_encode([
                'OPENFGA_MCP_API_TOKEN' => 'very-secret-token-12345',
                'OPENFGA_MCP_API_URL' => 'https://api.example.com', // not sensitive
            ]);

            $result = $parser->parseAndApply($json);

            expect($result->isSuccessful())->toBeTrue();
            // The actual values in $_ENV should be unmasked
            expect($_ENV['OPENFGA_MCP_API_TOKEN'])->toBe('very-secret-token-12345');
            expect($_ENV['OPENFGA_MCP_API_URL'])->toBe('https://api.example.com');
        });
    });
});
