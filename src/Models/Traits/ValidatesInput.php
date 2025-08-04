<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models\Traits;

use InvalidArgumentException;

use function sprintf;

/**
 * Provides validation methods for model inputs.
 */
trait ValidatesInput
{
    /**
     * Validates that a number is non-negative.
     *
     * @param int    $value
     * @param string $fieldName
     *
     * @throws InvalidArgumentException
     */
    protected function validateNonNegative(int $value, string $fieldName): void
    {
        if (0 > $value) {
            throw new InvalidArgumentException(sprintf('%s must be non-negative, got %d', $fieldName, $value));
        }
    }

    /**
     * Validates that a string is not empty.
     *
     * @param string $value
     * @param string $fieldName
     *
     * @throws InvalidArgumentException
     */
    protected function validateNotEmpty(string $value, string $fieldName): void
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException(sprintf('%s cannot be empty', $fieldName));
        }
    }

    /**
     * Validates that a string matches a pattern.
     *
     * @param non-empty-string $pattern
     * @param string           $value
     * @param string           $fieldName
     * @param string           $description
     *
     * @throws InvalidArgumentException
     */
    protected function validatePattern(string $value, string $pattern, string $fieldName, string $description = ''): void
    {
        if (1 !== preg_match($pattern, $value)) {
            $message = sprintf('%s does not match required pattern', $fieldName);

            if ('' !== $description) {
                $message .= ': ' . $description;
            }

            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Validates that a float is within a range.
     *
     * @param float  $value
     * @param float  $min
     * @param float  $max
     * @param string $fieldName
     *
     * @throws InvalidArgumentException
     */
    protected function validateRange(float $value, float $min, float $max, string $fieldName): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf('%s must be between %f and %f, got %f', $fieldName, $min, $max, $value));
        }
    }

    /**
     * Validates that a URI is well-formed.
     *
     * @param string $value
     * @param string $fieldName
     *
     * @throws InvalidArgumentException
     */
    protected function validateUri(string $value, string $fieldName): void
    {
        // Check if it's a valid URL or an openfga:// URI
        $isValidUrl = false !== filter_var($value, FILTER_VALIDATE_URL);
        $isOpenfgaUri = 1 === preg_match('/^openfga:\/\//', $value);

        if (! $isValidUrl && ! $isOpenfgaUri) {
            throw new InvalidArgumentException(sprintf('%s must be a valid URI, got "%s"', $fieldName, $value));
        }
    }
}
