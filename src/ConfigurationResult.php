<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

/**
 * Result object for configuration parsing operations.
 */
final readonly class ConfigurationResult
{
    /**
     * @param bool                  $successful    Whether the configuration was successfully parsed and applied
     * @param array<string>         $errors        List of error messages encountered
     * @param array<string>         $appliedKeys   List of configuration keys that were successfully applied
     * @param array<string, string> $appliedValues Map of applied configuration values
     */
    public function __construct(
        private bool $successful,
        private array $errors,
        private array $appliedKeys,
        private array $appliedValues,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getAppliedKeys(): array
    {
        return $this->appliedKeys;
    }

    /**
     * @return array<string, string>
     */
    public function getAppliedValues(): array
    {
        return $this->appliedValues;
    }

    public function getErrorMessage(): string
    {
        return implode('; ', $this->errors);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }
}
