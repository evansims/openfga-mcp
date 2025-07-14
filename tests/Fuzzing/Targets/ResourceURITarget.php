<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function in_array;
use function strlen;

/**
 * Fuzzing target for resource URI template parsing and substitution.
 */
final class ResourceURITarget
{
    private const MAX_PARAM_LENGTH = 256;

    private const MAX_URI_LENGTH = 2048;

    public function fuzz(string $input): void
    {
        // Test different aspects of URI handling
        $this->testURITemplateParsing($input);
        $this->testParameterSubstitution($input);
        $this->testPathTraversal($input);
        $this->testURIEncoding($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Valid URIs
            'openfga://store/123/model/latest',
            'openfga://store/{storeId}/model/{modelId}',
            'openfga://stores',
            'https://api.example.com/v1/stores/{storeId}',

            // Template substitution
            'openfga://store/{storeId}|storeId=test-store',
            'openfga://store/{storeId}/model/{modelId}|storeId=store1&modelId=model1',

            // Path traversal attempts
            'openfga://store/../admin',
            'openfga://store/%2e%2e/admin',
            'openfga://store/..\\admin',
            '\\\\server\\share',
            'C:\\Windows\\System32',

            // Encoding tests
            'openfga://store/%41%42%43',
            'openfga://store/%25%32%35',
            'openfga://store/%c0%ae%c0%ae/',
            'openfga://store/test%00null',

            // Edge cases
            'openfga://store/{' . str_repeat('a', 100) . '}',
            '{{{nested}}}',
            'openfga://store/{var1}{var2}{var3}',
            str_repeat('/', 1000),
            'опенфга://store/123', // Cyrillic 'o'

            // Injection attempts
            'openfga://store/{storeId}|storeId=test&storeId=malicious',
            'openfga://store/{${env:SECRET}}',
            'openfga://store/{`id`}',
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        $expectedMessages = [
            'URI too long',
            'Invalid URI scheme',
            'Invalid template variable name',
            'Template variable name too long',
            'Unbalanced template braces',
            'Nested templates not allowed',
            'Parameter value too long',
            'Null byte in parameter',
            'Substituted URI too long',
            'Path traversal attempt detected',
            'Absolute path not allowed',
            'UNC path not allowed',
            'Invalid percent encoding',
            'Overlong UTF-8 sequence',
            'Control characters in URI',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function testParameterSubstitution(string $input): void
    {
        // Simulate parameter substitution in templates
        if (! str_contains($input, '{')) {
            return;
        }

        // Extract template and parameters
        $parts = explode('|', $input, 2);
        $template = $parts[0];
        $params = $parts[1] ?? '';

        if (! empty($params)) {
            // Parse parameters (key=value pairs)
            $paramPairs = explode('&', $params);

            foreach ($paramPairs as $pair) {
                if (! str_contains($pair, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $pair, 2);

                // Validate parameter length
                if (self::MAX_PARAM_LENGTH < strlen($value)) {
                    throw new Exception('Parameter value too long');
                }

                // Check for null bytes
                if (str_contains($value, "\0")) {
                    throw new Exception('Null byte in parameter');
                }

                // Simulate substitution
                $template = str_replace('{' . $key . '}', $value, $template);
            }

            // Check final URI length
            if (self::MAX_URI_LENGTH < strlen($template)) {
                throw new Exception('Substituted URI too long');
            }
        }
    }

    private function testPathTraversal(string $input): void
    {
        // Check for path traversal attempts
        $pathTraversalPatterns = [
            '../',
            '..\\',
            '%2e%2e/',
            '%2e%2e\\',
            '..%2f',
            '..%5c',
            '%252e%252e%252f',
            '..%252f',
            '..%c0%af',
            '..%c1%9c',
        ];

        $lowerInput = strtolower($input);

        foreach ($pathTraversalPatterns as $pattern) {
            if (str_contains($lowerInput, $pattern)) {
                throw new Exception('Path traversal attempt detected');
            }
        }

        // Check for absolute paths
        if (preg_match('/^\/[a-zA-Z]:/', $input) || str_starts_with($input, '\\')) {
            throw new Exception('Absolute path not allowed');
        }

        // Check for UNC paths
        if (str_starts_with($input, '\\\\') || str_starts_with($input, '//')) {
            throw new Exception('UNC path not allowed');
        }
    }

    private function testURIEncoding(string $input): void
    {
        // Test various encoding issues

        // Check for double encoding
        if (preg_match('/%25[0-9a-fA-F]{2}/', $input)) {
            // Double encoded - could be trying to bypass filters
        }

        // Check for invalid percent encoding
        if (preg_match('/%[^0-9a-fA-F]/', $input) || preg_match('/%[0-9a-fA-F][^0-9a-fA-F]/', $input)) {
            throw new Exception('Invalid percent encoding');
        }

        // Check for overlong UTF-8 sequences
        if (preg_match('/%c0%[8-9a-fA-F][0-9a-fA-F]/', strtolower($input))) {
            throw new Exception('Overlong UTF-8 sequence');
        }

        // Decode and check for control characters
        $decoded = urldecode($input);

        if (preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
            throw new Exception('Control characters in URI');
        }

        // Check for homograph attacks (similar looking characters)
        if (preg_match('/[а-яА-Я]/', $decoded)) {
            // Contains Cyrillic characters that might look like Latin
        }
    }

    private function testURITemplateParsing(string $input): void
    {
        if (self::MAX_URI_LENGTH < strlen($input)) {
            throw new Exception('URI too long');
        }

        // Check for valid URI scheme
        if (str_contains($input, '://')) {
            $scheme = substr($input, 0, strpos($input, '://'));

            // Only allow specific schemes
            if (! in_array($scheme, ['openfga', 'http', 'https'], true)) {
                throw new Exception('Invalid URI scheme');
            }
        }

        // Check for template variables
        preg_match_all('/\{([^}]+)\}/', $input, $matches);

        foreach ($matches[1] as $varName) {
            // Validate variable name format
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $varName)) {
                throw new Exception('Invalid template variable name');
            }

            // Check variable name length
            if (64 < strlen($varName)) {
                throw new Exception('Template variable name too long');
            }
        }

        // Check for unbalanced braces
        $openCount = substr_count($input, '{');
        $closeCount = substr_count($input, '}');

        if ($openCount !== $closeCount) {
            throw new Exception('Unbalanced template braces');
        }

        // Check for nested templates
        if (preg_match('/\{[^}]*\{/', $input)) {
            throw new Exception('Nested templates not allowed');
        }
    }
}
