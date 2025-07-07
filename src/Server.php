<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenFGA\Client;
use PhpMcp\Server\Server;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Transports\{StdioServerTransport, StreamableHttpServerTransport};

try {
    $openfga = new Client(url: getConfiguredString('OPENFGA_MCP_API_URL', 'http://127.0.0.1:8080'));

    $container = new BasicContainer();
    $container->set(Client::class, $openfga);

    $server = Server::make()
        ->withServerInfo('OpenFGA MCP Server', '1.0.0')
        ->withContainer($container)
        ->build();

    $server->discover(
        basePath: __DIR__,
        scanDirs: ['Tools'],
        saveToCache: false,
    );

    $transport = match (getConfiguredString('OPENFGA_MCP_TRANSPORT')) {
        'http' => new StreamableHttpServerTransport(
            host: getConfiguredString('OPENFGA_MCP_TRANSPORT_HOST', '127.0.0.1'),
            port: getConfiguredInt('OPENFGA_MCP_TRANSPORT_PORT', 9090),
            enableJsonResponse: getConfiguredBool('OPENFGA_MCP_TRANSPORT_JSON', false)
        ),
        default => new StdioServerTransport(),
    };

    $server->listen($transport);

} catch (\Throwable $e) {
    fwrite(STDERR, "[CRITICAL ERROR] " . $e->getMessage() . "\n");
    exit(1);
}

function getConfiguredString(string $env, string $default = ''): string
{
    $value = $_ENV[$env] ?? $default;

    if (! is_string($value)) {
        return $default;
    }

    $value = trim((string)$value);

    if ($value === '') {
        return $default;
    }

    return $value;
}

function getConfiguredInt(string $env, int $default = 0): int
{
    $value = $_ENV[$env] ?? $default;

    if (! is_numeric($value)) {
        return $default;
    }

    return (int)$value;
}

function getConfiguredBool(string $env, bool $default = false): bool
{
    $value = $_ENV[$env] ?? $default;

    if (! is_bool($value)) {
        return $default;
    }

    return (bool)$value;
}
