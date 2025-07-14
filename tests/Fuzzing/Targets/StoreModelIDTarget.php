<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function count;
use function in_array;
use function strlen;

/**
 * Fuzzing target for store and model ID validation.
 */
final class StoreModelIDTarget
{
    private const MAX_ID_LENGTH = 256;

    private const VALID_ID_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/';

    public function fuzz(string $input): void
    {
        // Test different ID validation scenarios
        $this->testStoreIDValidation($input);
        $this->testModelIDValidation($input);
        $this->testRestrictedModeValidation($input);
        $this->testIDListParsing($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Valid store IDs
            'store-123',
            'my_store',
            'test-store-1',
            'STORE_PROD',
            'a1b2c3d4',

            // Valid model IDs
            '01HQPZ1M9KZZQ6V8XQB6DFR6EX',
            'v1.0.0',
            'v2.1.0-beta.1',
            '2024-01-01T00:00:00Z',
            'latest',
            'stable',

            // Reserved/invalid IDs
            'admin',
            'system',
            '../parent',
            'store/../../admin',
            'store%2Fadmin',
            'store\x00null',

            // Restricted mode tests
            'store1|store1|model1',
            'store1|store2|model1',
            'Store1|store1|model1',
            'st0re|store|model1',

            // ID lists
            'store1,store2,store3',
            'store1, store2, store3',
            "store1\nstore2\nstore3",
            'store1,store2,store3,store4,store5,store6,store7,store8,store9,store10',

            // Edge cases
            '',
            ' ',
            str_repeat('a', 256),
            str_repeat('a', 257),
            '123store',
            'store-',
            '-store',
            'store--name',
            'store__name',

            // Injection attempts
            'store; DROP TABLE stores;--',
            'store\'; DELETE FROM stores WHERE \'1\'=\'1',
            '<script>alert("xss")</script>',
            '${STORE_ID}',
            '$(echo store)',
            'store`id`',

            // Unicode and encoding
            'store\u200b\u200c\u200d',
            'ｓｔｏｒｅ', // Full-width
            'ＳＴＯＲＥ',
            'st\u00f6re', // o with umlaut
            'store\ud83d\ude00', // emoji
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        $expectedMessages = [
            'Store ID too long',
            'Store ID cannot be empty',
            'Null byte in store ID',
            'Spaces not allowed in store ID',
            'Store ID cannot start with number',
            'Invalid characters in store ID',
            'Reserved store ID',
            'Path characters in store ID',
            'URL encoded characters in store ID',
            'Model ID too long',
            'Invalid model ID format',
            'Potential injection in model ID',
            'Homograph attack detected',
            'Wildcards not allowed in restricted mode',
            'Too many IDs in list',
            'Empty ID in list',
            'Comments not allowed in ID list',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function testIDListParsing(string $input): void
    {
        // Test parsing of comma-separated ID lists
        if (str_contains($input, ',')) {
            $ids = explode(',', $input);

            if (100 < count($ids)) {
                throw new Exception('Too many IDs in list');
            }

            foreach ($ids as $id) {
                $trimmedId = trim($id);

                if (empty($trimmedId)) {
                    throw new Exception('Empty ID in list');
                }

                // Validate each ID
                try {
                    $this->testStoreIDValidation($trimmedId);
                } catch (Exception $e) {
                    // Check if it's a model ID instead
                    $this->testModelIDValidation($trimmedId);
                }
            }
        }

        // Test newline-separated lists
        if (str_contains($input, "\n")) {
            $lines = explode("\n", $input);

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                // Check for comments or metadata
                if (str_starts_with($line, '#') || str_starts_with($line, '//')) {
                    throw new Exception('Comments not allowed in ID list');
                }
            }
        }
    }

    private function testModelIDValidation(string $input): void
    {
        // Model IDs have similar but slightly different rules
        if (self::MAX_ID_LENGTH < strlen($input)) {
            throw new Exception('Model ID too long');
        }

        // Model IDs can be version strings like "01HQPZ1M9KZZQ6V8XQB6DFR6EX"
        if (preg_match('/^[0-9A-Z]{26}$/', $input)) {
            // Valid ULID format
            return;
        }

        // Check for semantic version format
        if (preg_match('/^v?\d+\.\d+\.\d+(-[a-zA-Z0-9.-]+)?(\+[a-zA-Z0-9.-]+)?$/', $input)) {
            // Valid semver
            return;
        }

        // Check for timestamp format (ISO 8601)
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $input)) {
            // Timestamp-based model ID
            return;
        }

        // Apply general ID validation
        if (! preg_match(self::VALID_ID_PATTERN, $input)) {
            throw new Exception('Invalid model ID format');
        }

        // Check for injection patterns
        if (preg_match('/[;<>\'"]/', $input)) {
            throw new Exception('Potential injection in model ID');
        }
    }

    private function testRestrictedModeValidation(string $input): void
    {
        // Test validation in restricted mode scenarios
        $parts = explode('|', $input, 3);

        if (2 <= count($parts)) {
            $storeId = $parts[0];
            $restrictedStoreId = $parts[1];
            $modelId = $parts[2] ?? '';

            // In restricted mode, check if store IDs match
            if (! empty($restrictedStoreId)) {
                // Normalize for comparison
                $normalizedStore = trim(strtolower($storeId));
                $normalizedRestricted = trim(strtolower($restrictedStoreId));

                // Check various bypass attempts
                if ($normalizedStore !== $normalizedRestricted) {
                    // Check Unicode normalization attacks
                    if (mb_strtolower($storeId) === mb_strtolower($restrictedStoreId)) {
                        // Unicode case folding attack
                    }

                    // Check homograph attacks
                    $homographs = [
                        'o' => '0', 'O' => '0',
                        'l' => '1', 'I' => '1',
                        'S' => '5', 's' => '5',
                    ];

                    $transformedStore = strtr($normalizedStore, $homographs);
                    $transformedRestricted = strtr($normalizedRestricted, $homographs);

                    if ($transformedStore === $transformedRestricted) {
                        throw new Exception('Homograph attack detected');
                    }
                }

                // Check for wildcard attempts
                if (str_contains($storeId, '*') || str_contains($storeId, '?')) {
                    throw new Exception('Wildcards not allowed in restricted mode');
                }
            }
        }
    }

    private function testStoreIDValidation(string $input): void
    {
        // Check ID length
        if (self::MAX_ID_LENGTH < strlen($input)) {
            throw new Exception('Store ID too long');
        }

        // Check for empty ID
        if (empty(trim($input))) {
            throw new Exception('Store ID cannot be empty');
        }

        // Check for null bytes
        if (str_contains($input, "\0")) {
            throw new Exception('Null byte in store ID');
        }

        // Validate ID format
        if (! preg_match(self::VALID_ID_PATTERN, $input)) {
            // Check specific invalid patterns
            if (str_contains($input, ' ')) {
                throw new Exception('Spaces not allowed in store ID');
            }

            if (preg_match('/^[0-9]/', $input)) {
                throw new Exception('Store ID cannot start with number');
            }

            if (preg_match('/[^a-zA-Z0-9_-]/', $input)) {
                throw new Exception('Invalid characters in store ID');
            }
        }

        // Check for reserved IDs
        $reserved = ['admin', 'api', 'root', 'system', 'internal', 'private', 'public'];

        if (in_array(strtolower($input), $reserved, true)) {
            throw new Exception('Reserved store ID');
        }

        // Check for path traversal patterns in ID
        if (preg_match('/\.\.|\/|\\\\/', $input)) {
            throw new Exception('Path characters in store ID');
        }

        // Check for URL encoding
        if (preg_match('/%[0-9a-fA-F]{2}/', $input)) {
            throw new Exception('URL encoded characters in store ID');
        }
    }
}
