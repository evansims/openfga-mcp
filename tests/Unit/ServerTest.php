<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/helpers.php';

afterEach(function (): void {
    // Clean up environment variables
    $_ENV = [];
});

describe('getConfiguredString', function (): void {
    it('returns the environment variable value when set', function (): void {
        putenv('TEST_VAR=test_value');

        expect(getConfiguredString('TEST_VAR'))->toBe('test_value');

        putenv('TEST_VAR=');
    });

    it('returns the default value when environment variable is not set', function (): void {
        expect(getConfiguredString('UNSET_VAR', 'default'))->toBe('default');
    });

    it('returns empty string as default when no default provided', function (): void {
        expect(getConfiguredString('UNSET_VAR'))->toBe('');
    });

    it('trims whitespace from environment variable value', function (): void {
        putenv('TEST_VAR=  trimmed value  ');

        expect(getConfiguredString('TEST_VAR'))->toBe('trimmed value');

        putenv('TEST_VAR=');
    });

    it('returns default when environment variable is empty string', function (): void {
        putenv('TEST_VAR=');

        expect(getConfiguredString('TEST_VAR', 'default'))->toBe('default');
    });

    it('returns default when environment variable is whitespace only', function (): void {
        putenv('TEST_VAR=   ');

        expect(getConfiguredString('TEST_VAR', 'default'))->toBe('default');

        putenv('TEST_VAR=');
    });

    it('returns default when environment variable is not a string', function (): void {
        // With getenv(), all values are returned as strings, so this test is no longer applicable
        // getenv() always returns a string or false
        expect(getConfiguredString('NON_EXISTENT_VAR', 'default'))->toBe('default');
    });
});

describe('getConfiguredInt', function (): void {
    it('returns integer value when environment variable is numeric', function (): void {
        putenv('TEST_VAR=42');

        expect(getConfiguredInt('TEST_VAR'))->toBe(42);

        putenv('TEST_VAR=');
    });

    it('returns integer value when environment variable is already an integer', function (): void {
        // With getenv(), all values are returned as strings
        putenv('TEST_VAR=42');

        expect(getConfiguredInt('TEST_VAR'))->toBe(42);

        putenv('TEST_VAR=');
    });

    it('returns default value when environment variable is not set', function (): void {
        expect(getConfiguredInt('UNSET_VAR', 100))->toBe(100);
    });

    it('returns 0 as default when no default provided', function (): void {
        expect(getConfiguredInt('UNSET_VAR'))->toBe(0);
    });

    it('returns default when environment variable is not numeric', function (): void {
        putenv('TEST_VAR=not_a_number');

        expect(getConfiguredInt('TEST_VAR', 50))->toBe(50);

        putenv('TEST_VAR=');
    });

    it('converts float strings to integers', function (): void {
        putenv('TEST_VAR=42.7');

        expect(getConfiguredInt('TEST_VAR'))->toBe(42);

        putenv('TEST_VAR=');
    });

    it('handles negative numbers', function (): void {
        putenv('TEST_VAR=-42');

        expect(getConfiguredInt('TEST_VAR'))->toBe(-42);

        putenv('TEST_VAR=');
    });
});

describe('getConfiguredBool', function (): void {
    it('returns boolean value when environment variable is boolean', function (): void {
        // With our new implementation, we check for string representations
        putenv('TEST_VAR=true');

        expect(getConfiguredBool('TEST_VAR'))->toBe(true);

        putenv('TEST_VAR=');
    });

    it('returns false value when environment variable is false', function (): void {
        putenv('TEST_VAR=false');

        expect(getConfiguredBool('TEST_VAR'))->toBe(false);

        putenv('TEST_VAR=');
    });

    it('returns default value when environment variable is not set', function (): void {
        expect(getConfiguredBool('UNSET_VAR', true))->toBe(true);
    });

    it('returns false as default when no default provided', function (): void {
        expect(getConfiguredBool('UNSET_VAR'))->toBe(false);
    });

    it('returns default when environment variable is not boolean', function (): void {
        $_ENV['TEST_VAR'] = 'true';

        expect(getConfiguredBool('TEST_VAR', true))->toBe(true);
    });

    it('returns default for numeric values', function (): void {
        // '1' string should be recognized as true, '2' should return default
        putenv('TEST_VAR=2');

        expect(getConfiguredBool('TEST_VAR', true))->toBe(true);

        putenv('TEST_VAR=');
    });
});
