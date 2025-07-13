<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Helpers.php';

use OpenFGA\Authentication\{ClientCredentialAuthentication, TokenAuthentication};
use OpenFGA\{Client, ClientInterface};
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\{StdioServerTransport, StreamableHttpServerTransport};

try {
    $authentication = null;

    if ('' !== getConfiguredString('OPENFGA_MCP_API_TOKEN', '')) {
        $authentication = new TokenAuthentication(
            token: getConfiguredString('OPENFGA_MCP_API_TOKEN', ''),
        );
    }

    if ('' !== getConfiguredString('OPENFGA_MCP_API_CLIENT_ID', '')) {
        $authentication = new ClientCredentialAuthentication(
            clientId: getConfiguredString('OPENFGA_MCP_API_CLIENT_ID', ''),
            clientSecret: getConfiguredString('OPENFGA_MCP_API_CLIENT_SECRET', ''),
            issuer: getConfiguredString('OPENFGA_MCP_API_ISSUER', ''),
            audience: getConfiguredString('OPENFGA_MCP_API_AUDIENCE', ''),
        );
    }

    $openfga = new Client(
        url: getConfiguredString('OPENFGA_MCP_API_URL', 'http://127.0.0.1:8080'),
        authentication: $authentication,
    );

    $container = new BasicContainer;
    $container->set(ClientInterface::class, $openfga);

    $server = Server::make()
        ->withServerInfo('OpenFGA MCP Server', '1.0.0')
        ->withContainer($container)
        ->build();

    $server->discover(
        basePath: __DIR__,
        scanDirs: ['Tools', 'Resources', 'Templates', 'Prompts'],
        saveToCache: false,
    );

    $transport = match (getConfiguredString('OPENFGA_MCP_TRANSPORT')) {
        'http' => new StreamableHttpServerTransport(
            host: getConfiguredString('OPENFGA_MCP_TRANSPORT_HOST', '127.0.0.1'),
            port: getConfiguredInt('OPENFGA_MCP_TRANSPORT_PORT', 9090),
            enableJsonResponse: getConfiguredBool('OPENFGA_MCP_TRANSPORT_JSON', false),
        ),
        default => new StdioServerTransport,
    };

    $server->listen($transport);
} catch (Throwable $throwable) {
    fwrite(STDERR, '[CRITICAL ERROR] ' . $throwable->getMessage() . "\n");

    exit(1);
}
