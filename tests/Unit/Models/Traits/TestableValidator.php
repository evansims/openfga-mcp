<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Unit\Models\Traits;

use OpenFGA\MCP\Models\Traits\ValidatesInput;

// Test class that uses the ValidatesInput trait for testing
final class TestableValidator
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
