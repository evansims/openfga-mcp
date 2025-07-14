<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function strlen;

/**
 * Fuzzing target for authentication configuration parsing.
 */
final class AuthenticationConfigTarget
{
    private const MAX_STRING_LENGTH = 10000;

    public function fuzz(string $input): void
    {
        // Test various authentication configuration scenarios
        $this->testTokenAuthentication($input);
        $this->testClientCredentials($input);
        $this->testAPIEndpointParsing($input);
        $this->testIssuerAudienceValidation($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Valid tokens
            'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
            'token123',
            '',

            // Client credentials
            'client_id=test-client',
            'client_id=test-client&client_secret=secret123',
            "client_id=test\nclient_secret=secret",

            // API endpoints
            'https://api.example.com',
            'https://api.example.com:8080/v1',
            'http://localhost:8080',
            'https://[::1]:8080',
            'https://user:pass@example.com',
            'https://example.com/../admin',

            // Issuer/Audience
            'https://auth.example.com',
            'urn:example:issuer',
            'https://auth1.example.com,https://auth2.example.com',

            // Edge cases
            str_repeat('A', 10000),
            "test\x00null",
            '${INJECTED_VAR}',
            '$(command)',
            '../../../etc/passwd',
            '<script>alert(1)</script>',
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        $expectedMessages = [
            'Token too long',
            'Invalid JWT segment encoding',
            'Null byte in token',
            'Empty bearer token',
            'Invalid configuration key format',
            'Configuration value too long',
            'URL too long',
            'Malformed URL',
            'Internal host not allowed',
            'IPv6 localhost not allowed',
            'Host too long',
            'Credentials in URL not allowed',
            'Path traversal detected',
            'Invalid issuer/audience format',
            'Issuer/audience too long',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function testAPIEndpointParsing(string $input): void
    {
        // Test URL parsing
        if (2048 < strlen($input)) {
            throw new Exception('URL too long');
        }

        // Basic URL validation
        if (preg_match('/^https?:\/\//', $input)) {
            // Parse URL components
            $parsed = parse_url($input);

            if (false === $parsed) {
                throw new Exception('Malformed URL');
            }

            // Check for suspicious patterns
            if (isset($parsed['host'])) {
                // Check for localhost/internal IPs (SSRF prevention)
                if (preg_match('/^(localhost|127\.|10\.|172\.16\.|192\.168\.)/', $parsed['host'])) {
                    throw new Exception('Internal host not allowed');
                }

                // Check for IPv6 localhost
                if ('::1' === $parsed['host'] || '[::1]' === $parsed['host']) {
                    throw new Exception('IPv6 localhost not allowed');
                }

                // Check host length
                if (253 < strlen($parsed['host'])) {
                    throw new Exception('Host too long');
                }
            }

            // Check for credentials in URL
            if (isset($parsed['user']) || isset($parsed['pass'])) {
                throw new Exception('Credentials in URL not allowed');
            }

            // Check path traversal
            if (isset($parsed['path']) && str_contains($parsed['path'], '..')) {
                throw new Exception('Path traversal detected');
            }
        }
    }

    private function testClientCredentials(string $input): void
    {
        // Test client ID/secret parsing
        $lines = explode("\n", $input);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Simulate key=value parsing
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);

                // Validate key format
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($key))) {
                    throw new Exception('Invalid configuration key format');
                }

                // Check for injection in values
                if (preg_match('/[<>\"\'&]/', $value)) {
                    // Could be injection attempt
                }

                // Check value length
                if (1000 < strlen($value)) {
                    throw new Exception('Configuration value too long');
                }
            }
        }
    }

    private function testIssuerAudienceValidation(string $input): void
    {
        // Test issuer/audience URL validation
        if (empty(trim($input))) {
            return;
        }

        // Check for multiple URLs separated by common delimiters
        $urls = preg_split('/[\s,;|]+/', $input);

        foreach ($urls as $url) {
            if (empty(trim($url))) {
                continue;
            }

            // Must be a valid URL or identifier
            if (! filter_var($url, FILTER_VALIDATE_URL) && ! preg_match('/^[a-zA-Z0-9:._-]+$/', $url)) {
                throw new Exception('Invalid issuer/audience format');
            }

            // Check length
            if (1000 < strlen($url)) {
                throw new Exception('Issuer/audience too long');
            }
        }
    }

    private function testTokenAuthentication(string $input): void
    {
        // Simulate token parsing
        if (self::MAX_STRING_LENGTH < strlen($input)) {
            throw new Exception('Token too long');
        }

        // Check for common JWT structure issues
        if (2 === substr_count($input, '.')) {
            // Looks like a JWT, validate segments
            $parts = explode('.', $input);

            foreach ($parts as $part) {
                // Check for valid base64
                if (! empty($part) && ! preg_match('/^[A-Za-z0-9_-]+$/', $part)) {
                    throw new Exception('Invalid JWT segment encoding');
                }
            }
        }

        // Check for null bytes
        if (str_contains($input, "\0")) {
            throw new Exception('Null byte in token');
        }

        // Simulate Bearer token format
        if (0 === stripos($input, 'bearer')) {
            $token = substr($input, 7);

            if (empty(trim($token))) {
                throw new Exception('Empty bearer token');
            }
        }
    }
}
