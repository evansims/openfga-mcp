<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use InvalidArgumentException;
use OpenFGA\MCP\Models\Traits\ValidatesInput;

// Create a test class that uses the trait
final class ValidatesInputTest
{
    use ValidatesInput;

    public function testValidateNonNegative(int $value, string $fieldName): void
    {
        $this->validateNonNegative($value, $fieldName);
    }

    public function testValidateNotEmpty(string $value, string $fieldName): void
    {
        $this->validateNotEmpty($value, $fieldName);
    }

    public function testValidatePattern(string $value, string $pattern, string $fieldName, string $description = ''): void
    {
        $this->validatePattern($value, $pattern, $fieldName, $description);
    }

    public function testValidateRange(float $value, float $min, float $max, string $fieldName): void
    {
        $this->validateRange($value, $min, $max, $fieldName);
    }

    public function testValidateUri(string $value, string $fieldName): void
    {
        $this->validateUri($value, $fieldName);
    }
}

beforeEach(function (): void {
    $this->validator = new TestableValidator;
});

describe('ValidatesInput trait', function (): void {
    describe('validateNonNegative', function (): void {
        it('accepts zero value', function (): void {
            expect(function (): void {
                $this->validator->testValidateNonNegative(0, 'Test Field');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('accepts positive values', function (): void {
            expect(function (): void {
                $this->validator->testValidateNonNegative(1, 'Count');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateNonNegative(100, 'Size');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateNonNegative(PHP_INT_MAX, 'Max Value');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for negative values', function (): void {
            expect(fn () => $this->validator->testValidateNonNegative(-1, 'Count'))
                ->toThrow(InvalidArgumentException::class, 'Count must be non-negative, got -1');

            expect(fn () => $this->validator->testValidateNonNegative(-100, 'Size'))
                ->toThrow(InvalidArgumentException::class, 'Size must be non-negative, got -100');

            expect(fn () => $this->validator->testValidateNonNegative(PHP_INT_MIN, 'Min Value'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('includes field name and value in error message', function (): void {
            expect(fn () => $this->validator->testValidateNonNegative(-42, 'Age'))
                ->toThrow(InvalidArgumentException::class, 'Age must be non-negative, got -42');
        });
    });

    describe('validateNotEmpty', function (): void {
        it('accepts non-empty strings', function (): void {
            expect(function (): void {
                $this->validator->testValidateNotEmpty('hello', 'Name');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateNotEmpty('  text  ', 'Content');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateNotEmpty('0', 'Zero String');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateNotEmpty('false', 'False String');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for empty string', function (): void {
            expect(fn () => $this->validator->testValidateNotEmpty('', 'Name'))
                ->toThrow(InvalidArgumentException::class, 'Name cannot be empty');
        });

        it('throws exception for whitespace-only strings', function (): void {
            expect(fn () => $this->validator->testValidateNotEmpty(' ', 'Field'))
                ->toThrow(InvalidArgumentException::class, 'Field cannot be empty');

            expect(fn () => $this->validator->testValidateNotEmpty('   ', 'Content'))
                ->toThrow(InvalidArgumentException::class, 'Content cannot be empty');

            expect(fn () => $this->validator->testValidateNotEmpty("\t\n\r", 'Text'))
                ->toThrow(InvalidArgumentException::class, 'Text cannot be empty');
        });

        it('includes field name in error message', function (): void {
            expect(fn () => $this->validator->testValidateNotEmpty('', 'Username'))
                ->toThrow(InvalidArgumentException::class, 'Username cannot be empty');

            expect(fn () => $this->validator->testValidateNotEmpty('  ', 'Email Address'))
                ->toThrow(InvalidArgumentException::class, 'Email Address cannot be empty');
        });
    });

    describe('validatePattern', function (): void {
        it('accepts values matching the pattern', function (): void {
            expect(function (): void {
                $this->validator->testValidatePattern('hello123', '/^[a-z0-9]+$/', 'Identifier');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidatePattern('user@example.com', '/^[^@]+@[^@]+$/', 'Email');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidatePattern('ABC-123', '/^[A-Z]{3}-[0-9]{3}$/', 'Code');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for non-matching values', function (): void {
            expect(fn () => $this->validator->testValidatePattern('Hello123', '/^[a-z0-9]+$/', 'Identifier'))
                ->toThrow(InvalidArgumentException::class, 'Identifier does not match required pattern');

            expect(fn () => $this->validator->testValidatePattern('invalid-email', '/^[^@]+@[^@]+$/', 'Email'))
                ->toThrow(InvalidArgumentException::class, 'Email does not match required pattern');
        });

        it('includes description when provided', function (): void {
            expect(fn () => $this->validator->testValidatePattern(
                'INVALID',
                '/^[a-z]+$/',
                'Username',
                'must contain only lowercase letters',
            ))->toThrow(InvalidArgumentException::class, 'Username does not match required pattern: must contain only lowercase letters');
        });

        it('works without description', function (): void {
            expect(fn () => $this->validator->testValidatePattern('123', '/^[a-z]+$/', 'Field'))
                ->toThrow(InvalidArgumentException::class, 'Field does not match required pattern');
        });

        it('handles complex regex patterns', function (): void {
            // UUID pattern
            $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

            expect(function () use ($uuidPattern): void {
                $this->validator->testValidatePattern('550e8400-e29b-41d4-a716-446655440000', $uuidPattern, 'UUID');
            })->not->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->validator->testValidatePattern('not-a-uuid', $uuidPattern, 'UUID'))
                ->toThrow(InvalidArgumentException::class);

            // Phone number pattern
            $phonePattern = '/^\+?[1-9]\d{1,14}$/';

            expect(function () use ($phonePattern): void {
                $this->validator->testValidatePattern('+12125551234', $phonePattern, 'Phone');
            })->not->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->validator->testValidatePattern('555-1234', $phonePattern, 'Phone'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('handles special regex characters in pattern', function (): void {
            expect(function (): void {
                $this->validator->testValidatePattern('test.file', '/^[a-z]+\.[a-z]+$/', 'Filename');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidatePattern('a*b+c?', '/^[a-z*+?]+$/', 'Pattern');
            })->not->toThrow(InvalidArgumentException::class);
        });
    });

    describe('validateRange', function (): void {
        it('accepts values within range', function (): void {
            expect(function (): void {
                $this->validator->testValidateRange(0.5, 0.0, 1.0, 'Score');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(50.0, 0.0, 100.0, 'Percentage');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(-5.0, -10.0, 10.0, 'Temperature');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('accepts boundary values', function (): void {
            expect(function (): void {
                $this->validator->testValidateRange(0.0, 0.0, 1.0, 'Min Boundary');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(1.0, 0.0, 1.0, 'Max Boundary');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(100.0, 100.0, 100.0, 'Exact Value');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for values below minimum', function (): void {
            expect(fn () => $this->validator->testValidateRange(-0.1, 0.0, 1.0, 'Score'))
                ->toThrow(InvalidArgumentException::class, 'Score must be between 0.000000 and 1.000000, got -0.100000');

            expect(fn () => $this->validator->testValidateRange(-50.5, -10.0, 10.0, 'Temperature'))
                ->toThrow(InvalidArgumentException::class, 'Temperature must be between -10.000000 and 10.000000, got -50.500000');
        });

        it('throws exception for values above maximum', function (): void {
            expect(fn () => $this->validator->testValidateRange(1.1, 0.0, 1.0, 'Score'))
                ->toThrow(InvalidArgumentException::class, 'Score must be between 0.000000 and 1.000000, got 1.100000');

            expect(fn () => $this->validator->testValidateRange(101.0, 0.0, 100.0, 'Percentage'))
                ->toThrow(InvalidArgumentException::class, 'Percentage must be between 0.000000 and 100.000000, got 101.000000');
        });

        it('handles floating point precision', function (): void {
            expect(function (): void {
                $this->validator->testValidateRange(0.3333333, 0.0, 1.0, 'Precision');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(0.999999, 0.0, 1.0, 'Near Max');
            })->not->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->validator->testValidateRange(1.000001, 0.0, 1.0, 'Just Over'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('handles very large ranges', function (): void {
            expect(function (): void {
                $this->validator->testValidateRange(0.0, -PHP_FLOAT_MAX, PHP_FLOAT_MAX, 'Huge Range');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateRange(1000000.0, 0.0, 10000000.0, 'Million');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('handles negative ranges', function (): void {
            expect(function (): void {
                $this->validator->testValidateRange(-50.0, -100.0, -25.0, 'Negative Range');
            })->not->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->validator->testValidateRange(-24.0, -100.0, -25.0, 'Too High'))
                ->toThrow(InvalidArgumentException::class);

            expect(fn () => $this->validator->testValidateRange(-101.0, -100.0, -25.0, 'Too Low'))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('validateUri', function (): void {
        it('accepts valid HTTP URLs', function (): void {
            expect(function (): void {
                $this->validator->testValidateUri('http://example.com', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('https://example.com/path', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('http://localhost:8080', 'URL');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('accepts valid URLs with query parameters and fragments', function (): void {
            expect(function (): void {
                $this->validator->testValidateUri('https://example.com/path?query=value#fragment', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('https://api.example.com/v1/users?page=1&limit=10', 'API URL');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('accepts other valid URL schemes', function (): void {
            expect(function (): void {
                $this->validator->testValidateUri('ftp://ftp.example.com', 'FTP URL');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('file:///path/to/file', 'File URL');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('mailto:user@example.com', 'Mailto URL');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('accepts openfga:// URIs', function (): void {
            expect(function (): void {
                $this->validator->testValidateUri('openfga://docs', 'OpenFGA URI');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('openfga://docs/php', 'OpenFGA URI');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('openfga://docs/php/chunk/123', 'OpenFGA URI');
            })->not->toThrow(InvalidArgumentException::class);

            expect(function (): void {
                $this->validator->testValidateUri('openfga://resource/path/to/item', 'OpenFGA URI');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for invalid URIs', function (): void {
            expect(fn () => $this->validator->testValidateUri('not a uri', 'URI'))
                ->toThrow(InvalidArgumentException::class, 'URI must be a valid URI, got "not a uri"');

            expect(fn () => $this->validator->testValidateUri('just-text', 'URI'))
                ->toThrow(InvalidArgumentException::class, 'URI must be a valid URI, got "just-text"');

            expect(fn () => $this->validator->testValidateUri('', 'URI'))
                ->toThrow(InvalidArgumentException::class, 'URI must be a valid URI, got ""');
        });

        it('throws exception for malformed URLs', function (): void {
            expect(fn () => $this->validator->testValidateUri('http://', 'URL'))
                ->toThrow(InvalidArgumentException::class, 'URL must be a valid URI, got "http://"');

            expect(fn () => $this->validator->testValidateUri('://example.com', 'URL'))
                ->toThrow(InvalidArgumentException::class, 'URL must be a valid URI, got "://example.com"');

            expect(fn () => $this->validator->testValidateUri('http://[invalid', 'URL'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('handles edge cases', function (): void {
            // URLs with special characters
            expect(function (): void {
                $this->validator->testValidateUri('https://example.com/path%20with%20spaces', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            // URLs with authentication
            expect(function (): void {
                $this->validator->testValidateUri('https://user:pass@example.com', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            // URLs with non-standard ports
            expect(function (): void {
                $this->validator->testValidateUri('http://example.com:65535', 'URL');
            })->not->toThrow(InvalidArgumentException::class);

            // IPv6 URLs
            expect(function (): void {
                $this->validator->testValidateUri('http://[2001:db8::1]', 'IPv6 URL');
            })->not->toThrow(InvalidArgumentException::class);
        });

        it('includes URI value in error message', function (): void {
            expect(fn () => $this->validator->testValidateUri('invalid-uri-value', 'Website'))
                ->toThrow(InvalidArgumentException::class, 'Website must be a valid URI, got "invalid-uri-value"');

            expect(fn () => $this->validator->testValidateUri('not-a-valid-uri-at-all', 'Resource'))
                ->toThrow(InvalidArgumentException::class, 'Resource must be a valid URI, got "not-a-valid-uri-at-all"');
        });

        it('handles very long URIs', function (): void {
            $longPath = str_repeat('/segment', 100);
            $longUri = 'https://example.com' . $longPath;

            expect(function () use ($longUri): void {
                $this->validator->testValidateUri($longUri, 'Long URI');
            })->not->toThrow(InvalidArgumentException::class);

            $longQuery = '?' . str_repeat('param=value&', 100);
            $longQueryUri = 'https://example.com/path' . $longQuery;

            expect(function () use ($longQueryUri): void {
                $this->validator->testValidateUri($longQueryUri, 'Long Query URI');
            })->not->toThrow(InvalidArgumentException::class);
        });
    });
});
