<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function is_bool;
use function strlen;

// No namespace needed - using global functions from src/Helpers.php

/**
 * Fuzzing target for configuration parsing helpers.
 */
final class ConfigurationTarget
{
    public function fuzz(string $input): void
    {
        // Set the input as an environment variable
        putenv("FUZZ_TEST_VAR={$input}");

        // Test getConfiguredString
        try {
            $result = getConfiguredString('FUZZ_TEST_VAR', 'default');

            // Validate the result is reasonable
            if (1000000 < strlen($result)) {
                throw new Exception('String result too long');
            }
        } catch (Throwable $e) {
            // Re-throw if it's not an expected error
            if (! $this->isExpectedError($e)) {
                throw $e;
            }
        }

        // Test getConfiguredInt
        try {
            $result = getConfiguredInt('FUZZ_TEST_VAR', 42);

            // Validate the result is within reasonable bounds
            if (PHP_INT_MIN > $result || PHP_INT_MAX < $result) {
                throw new Exception('Integer out of bounds');
            }
        } catch (Throwable $e) {
            if (! $this->isExpectedError($e)) {
                throw $e;
            }
        }

        // Test getConfiguredBool
        try {
            $result = getConfiguredBool('FUZZ_TEST_VAR', false);

            // Result should always be boolean
            if (! is_bool($result)) {
                throw new Exception('Non-boolean result from getConfiguredBool');
            }
        } catch (Throwable $e) {
            if (! $this->isExpectedError($e)) {
                throw $e;
            }
        }

        // Clean up
        putenv('FUZZ_TEST_VAR');
    }

    public function getInitialCorpus(): array
    {
        return [
            '',
            '0',
            '1',
            '-1',
            'true',
            'false',
            'TRUE',
            'FALSE',
            'yes',
            'no',
            'on',
            'off',
            '123.456',
            'null',
            'undefined',
            '!@#$%^&*()',
            '\x00\x01\x02',
            str_repeat('a', 1000),
            PHP_INT_MAX . '',
            PHP_INT_MIN . '',
        ];
    }

    private function isExpectedError(Throwable $e): bool
    {
        // These are expected errors from invalid input
        $expectedMessages = [
            'expects parameter',
            'must be of type',
            'undefined constant',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }
}
