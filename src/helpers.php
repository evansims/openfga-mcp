<?php

declare(strict_types=1);

function getConfiguredString(string $env, string $default = ''): string
{
    $value = getenv($env);

    if (false === $value) {
        return $default;
    }

    $value = trim($value);

    if ('' === $value) {
        return $default;
    }

    return $value;
}

function getConfiguredInt(string $env, int $default = 0): int
{
    $value = getenv($env);

    if (false === $value || ! is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function getConfiguredBool(string $env, bool $default = false): bool
{
    $value = getenv($env);

    if (false === $value) {
        return $default;
    }

    // Convert string representations to bool
    if ('true' === $value || '1' === $value) {
        return true;
    }

    if ('false' === $value || '0' === $value) {
        return false;
    }

    return $default;
}
