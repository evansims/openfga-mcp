<?php

declare(strict_types=1);

function getConfiguredString(string $env, string $default = ''): string
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    /** @var mixed $value */
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    // Treat false, null, empty string, or the literal string 'false' as not set
    if (false === $value || null === $value || '' === $value || 'false' === $value) {
        return $default;
    }

    if (is_string($value)) {
        $stringValue = $value;
    } elseif (is_scalar($value)) {
        $stringValue = (string) $value;
    } else {
        return $default;
    }
    $stringValue = trim($stringValue);

    if ('' === $stringValue || 'false' === $stringValue) {
        return $default;
    }

    return $stringValue;
}

function getConfiguredInt(string $env, int $default = 0): int
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    /** @var mixed $value */
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    if (false === $value || null === $value || ! is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function getConfiguredBool(string $env, bool $default = false): bool
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    /** @var mixed $value */
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    // Treat false, null, empty string, or the literal string 'false' as not set
    if (false === $value || null === $value || '' === $value) {
        return $default;
    }

    if (is_string($value)) {
        $stringValue = $value;
    } elseif (is_scalar($value)) {
        $stringValue = (string) $value;
    } else {
        return $default;
    }

    // Convert string representations to bool
    if ('true' === $stringValue || '1' === $stringValue) {
        return true;
    }

    // Explicitly treat 'false', '0' as false (not default)
    if ('false' === $stringValue || '0' === $stringValue) {
        return false;
    }

    return $default;
}
