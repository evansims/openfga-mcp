<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Exception;

$options = getopt(
    join('', [
        'h::',
        'u::',
        's::',
        'm::',
        't::',
        'c::',
        's::',
        'i::',
        'a::'
    ]),
    [
        'help::',
        'url::',
        'store::',
        'model::',
        'token::',
        'client::',
        'secret::',
        'issuer::',
        'audience::'
    ]
);

$server = new Server(
    url: $_ENV['OPENFGA_URL'] ?? $options['url'] ?? 'http://localhost:8080',
    store: $_ENV['OPENFGA_STORE'] ?? $options['store'] ?? null,
    model: $_ENV['OPENFGA_MODEL'] ?? $options['model'] ?? null,
    token: $_ENV['OPENFGA_TOKEN'] ?? $options['token'] ?? null,
    client: $_ENV['OPENFGA_CLIENT'] ?? $options['client'] ?? null,
    secret: $_ENV['OPENFGA_SECRET'] ?? $options['secret'] ?? null,
    issuer: $_ENV['OPENFGA_ISSUER'] ?? $options['issuer'] ?? null,
    audience: $_ENV['OPENFGA_AUDIENCE'] ?? $options['audience'] ?? null,
);

function getOption(string $short, ?string $long = null, ?string $env = null, ?string $default = null): ?string
{
    if (isset($_ENV[$env])) {
        $val = null;

        try {
            $val = filter_var($_ENV[$env], FILTER_SANITIZE_STRING);
            $val = is_string($val) ? trim($val) : '';
            $val = $val !== '' ? $val : null;
        } catch (Exception $e) {
            $val = null;
        }

        if ($val !== null) {
            return $val;
        }
    }

    $options = getopt($short, $long);

    if (isset($options[$env])) {
        $val = null;

        try {
            $val = filter_var($_ENV[$env], FILTER_SANITIZE_STRING);
            $val = is_string($val) ? trim($val) : '';
            $val = $val !== '' ? $val : null;
        } catch (Exception $e) {
            $val = null;
        }

        if ($val !== null) {
            return $val;
        }
    }

    return $default;
}
