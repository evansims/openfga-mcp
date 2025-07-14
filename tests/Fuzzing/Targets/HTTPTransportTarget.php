<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function in_array;
use function strlen;

/**
 * Fuzzing target for HTTP transport configuration and header validation.
 */
final class HTTPTransportTarget
{
    private const MAX_HEADER_LENGTH = 8192;

    private const MAX_HEADER_NAME_LENGTH = 256;

    private const MAX_PORT_NUMBER = 65535;

    public function fuzz(string $input): void
    {
        // Test different HTTP configuration scenarios
        $this->testHostPortValidation($input);
        $this->testHeaderValidation($input);
        $this->testProxyConfiguration($input);
        $this->testTimeoutConfiguration($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Host:port combinations
            'api.example.com:8080',
            'localhost:3000',
            '127.0.0.1:8080',
            '192.168.1.1:80',
            '[::1]:8080',
            '[2001:db8::1]:443',
            'example.com:65535',
            'example.com:0',
            'example.com:99999',

            // Headers
            'Authorization: Bearer token123',
            'User-Agent: OpenFGA-MCP/1.0',
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'X-Custom-Header: value',
            "Multiple: headers\nAnother: header",
            "Injection: value\r\nX-Injected: true",
            'Long-Header: ' . str_repeat('A', 8192),

            // Proxy configurations
            'proxy:http://proxy.example.com:8080',
            'proxy:https://user:pass@proxy.example.com:8080',
            'proxy:socks5://127.0.0.1:1080',
            'proxy:ftp://invalid.proxy.com',

            // Timeout values
            '30',
            '0.5',
            '-1',
            '3600',
            '10s',
            '500ms',
            '2m',
            '1h',
            'infinity',
            'NaN',

            // Edge cases
            '',
            ':8080',
            'host:',
            ':::',
            str_repeat('a', 300) . ':8080',
            "Header-With-\0-Null: value",
            'Script-Tag: <script>alert(1)</script>',
            "SQL-Injection: ' OR '1'='1",

            // SSRF attempts
            '169.254.169.254:80',
            'metadata.google.internal:80',
            'localhost:22',
            '0.0.0.0:8080',
            '[::]:8080', // Zero-width space
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        $expectedMessages = [
            'Empty host',
            'Host too long',
            'Invalid characters in host',
            'Internal host not allowed',
            'Port must be numeric',
            'Port out of range',
            'Privileged port not allowed',
            'Invalid IPv6 address',
            'Invalid header format',
            'Header name too long',
            'Invalid header name',
            'Forbidden header',
            'Header value too long',
            'Header injection detected',
            'Null byte in header',
            'Authorization header too long',
            'Suspicious User-Agent',
            'Invalid Content-Type',
            'Too many Accept values',
            'Invalid proxy URL',
            'Invalid proxy scheme',
            'Proxy credentials too long',
            'Invalid characters in proxy credentials',
            'Negative timeout',
            'Timeout too large',
            'Infinite timeout',
            'NaN timeout',
            'Converted timeout too large',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function testHeaderValidation(string $input): void
    {
        // Test HTTP header parsing
        $lines = explode("\n", $input);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check header format
            if (! str_contains($line, ':')) {
                throw new Exception('Invalid header format');
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Validate header name
            if (self::MAX_HEADER_NAME_LENGTH < strlen($name)) {
                throw new Exception('Header name too long');
            }

            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
                throw new Exception('Invalid header name');
            }

            // Check for forbidden headers
            $forbidden = [
                'host', 'content-length', 'transfer-encoding',
                'connection', 'keep-alive', 'upgrade',
                'te', 'trailer', 'proxy-authorization',
            ];

            if (in_array(strtolower($name), $forbidden, true)) {
                throw new Exception('Forbidden header');
            }

            // Validate header value
            if (self::MAX_HEADER_LENGTH < strlen($value)) {
                throw new Exception('Header value too long');
            }

            // Check for header injection
            if (preg_match('/[\r\n]/', $value)) {
                throw new Exception('Header injection detected');
            }

            // Check for null bytes
            if (str_contains($value, "\0")) {
                throw new Exception('Null byte in header');
            }

            // Special validation for specific headers
            $this->validateSpecificHeader($name, $value);
        }
    }

    private function testHostPortValidation(string $input): void
    {
        // Test host:port combinations
        if (str_contains($input, ':')) {
            $parts = explode(':', $input, 2);
            $host = $parts[0];
            $port = $parts[1];

            // Validate host
            if (empty($host)) {
                throw new Exception('Empty host');
            }

            // Check host length
            if (253 < strlen($host)) {
                throw new Exception('Host too long');
            }

            // Check for invalid host characters
            if (preg_match('/[<>\'"\s]/', $host)) {
                throw new Exception('Invalid characters in host');
            }

            // Check for localhost/internal IPs (SSRF prevention)
            $internalPatterns = [
                '/^localhost$/i',
                '/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',
                '/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',
                '/^172\.(1[6-9]|2[0-9]|3[01])\.\d{1,3}\.\d{1,3}$/',
                '/^192\.168\.\d{1,3}\.\d{1,3}$/',
                '/^169\.254\.\d{1,3}\.\d{1,3}$/', // Link-local
                '/^::1$/', // IPv6 localhost
                '/^fe80:/i', // IPv6 link-local
                '/^fc00:/i', // IPv6 unique local
            ];

            foreach ($internalPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    throw new Exception('Internal host not allowed');
                }
            }

            // Validate port
            if (! empty($port)) {
                if (! is_numeric($port)) {
                    throw new Exception('Port must be numeric');
                }

                $portNum = (int) $port;

                if (1 > $portNum || self::MAX_PORT_NUMBER < $portNum) {
                    throw new Exception('Port out of range');
                }

                // Check for privileged ports
                if (1024 > $portNum) {
                    throw new Exception('Privileged port not allowed');
                }
            }
        }

        // Check for IPv6 format
        if (str_starts_with($input, '[') && str_contains($input, ']')) {
            $ipv6Part = substr($input, 1, strpos($input, ']') - 1);

            // Basic IPv6 validation
            if (! filter_var($ipv6Part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new Exception('Invalid IPv6 address');
            }
        }
    }

    private function testProxyConfiguration(string $input): void
    {
        // Test proxy URL parsing
        if (0 === stripos($input, 'proxy:')) {
            $proxyUrl = substr($input, 6);

            // Parse proxy URL
            $parsed = parse_url($proxyUrl);

            if (false === $parsed) {
                throw new Exception('Invalid proxy URL');
            }

            // Check proxy scheme
            if (isset($parsed['scheme']) && ! in_array($parsed['scheme'], ['http', 'https', 'socks5'], true)) {
                throw new Exception('Invalid proxy scheme');
            }

            // Check for credentials in proxy URL
            if (isset($parsed['user'], $parsed['pass'])) {
                // Validate credential format
                if (256 < strlen($parsed['user']) || 256 < strlen($parsed['pass'])) {
                    throw new Exception('Proxy credentials too long');
                }

                // Check for special characters that might cause issues
                if (preg_match('/[@:\/]/', $parsed['user']) || preg_match('/[@:\/]/', $parsed['pass'])) {
                    throw new Exception('Invalid characters in proxy credentials');
                }
            }
        }
    }

    private function testTimeoutConfiguration(string $input): void
    {
        // Test timeout values
        if (is_numeric($input)) {
            $timeout = (float) $input;

            if (0 > $timeout) {
                throw new Exception('Negative timeout');
            }

            if (3600 < $timeout) {
                throw new Exception('Timeout too large');
            }

            if (is_infinite($timeout)) {
                throw new Exception('Infinite timeout');
            }

            if (is_nan($timeout)) {
                throw new Exception('NaN timeout');
            }
        }

        // Test timeout with units
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(s|ms|m|h)$/i', $input, $matches)) {
            $value = (float) $matches[1];
            $unit = strtolower($matches[2]);

            // Convert to seconds
            switch ($unit) {
                case 'ms':
                    $value = $value / 1000;

                    break;

                case 'm':
                    $value = $value * 60;

                    break;

                case 'h':
                    $value = $value * 3600;

                    break;
            }

            if (3600 < $value) {
                throw new Exception('Converted timeout too large');
            }
        }
    }

    private function validateSpecificHeader(string $name, string $value): void
    {
        switch (strtolower($name)) {
            case 'authorization':
                // Check for credential leaks
                if (preg_match('/api[_-]?key/i', $value)) {
                    // API key in header - this is expected
                }

                if (2048 < strlen($value)) {
                    throw new Exception('Authorization header too long');
                }

                break;

            case 'user-agent':
                // Check for script tags or SQL
                if (preg_match('/<script|SELECT|UNION|DROP/i', $value)) {
                    throw new Exception('Suspicious User-Agent');
                }

                break;

            case 'content-type':
                // Validate MIME type format
                if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\/+.-]*$/', explode(';', $value)[0])) {
                    throw new Exception('Invalid Content-Type');
                }

                break;

            case 'accept':
            case 'accept-encoding':
            case 'accept-language':
                // Check for excessive values
                if (20 < substr_count($value, ',')) {
                    throw new Exception('Too many Accept values');
                }

                break;
        }
    }
}
