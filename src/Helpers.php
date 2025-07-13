<?php

declare(strict_types=1);

function getConfiguredString(string $env, string $default = ''): string
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    // Treat false, null, empty string, or the literal string 'false' as not set
    if (false === $value || null === $value || '' === $value || 'false' === $value) {
        return $default;
    }

    $value = trim((string) $value);

    if ('' === $value || 'false' === $value) {
        return $default;
    }

    return $value;
}

function getConfiguredInt(string $env, int $default = 0): int
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    if (false === $value || null === $value || ! is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function getConfiguredBool(string $env, bool $default = false): bool
{
    // In testing environments, check $_ENV first as it can be overridden more reliably
    $value = array_key_exists($env, $_ENV) ? $_ENV[$env] : getenv($env);

    // Treat false, null, empty string, or the literal string 'false' as not set
    if (false === $value || null === $value || '' === $value) {
        return $default;
    }

    $value = (string) $value;

    // Convert string representations to bool
    if ('true' === $value || '1' === $value) {
        return true;
    }

    // Explicitly treat 'false', '0', or empty string as false (not default)
    if ('false' === $value || '0' === $value || '' === $value) {
        return false;
    }

    return $default;
}
