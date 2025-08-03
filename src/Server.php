<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Helpers.php';

use OpenFGA\Authentication\{ClientCredentialAuthentication, TokenAuthentication};
use OpenFGA\{Client, ClientInterface};
use OpenFGA\MCP\OfflineClient;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\{StdioServerTransport, StreamableHttpServerTransport};

try {
    // Check if OpenFGA configuration is provided
    $apiUrl = getConfiguredString('OPENFGA_MCP_API_URL', '');
    $hasToken = '' !== getConfiguredString('OPENFGA_MCP_API_TOKEN', '');
    $hasClientId = '' !== getConfiguredString('OPENFGA_MCP_API_CLIENT_ID', '');

    // Determine if we're in offline mode
    $isOfflineMode = ('' === $apiUrl && ! $hasToken && ! $hasClientId);

    if ($isOfflineMode) {
        // Use offline client for planning and coding features
        $openfga = new OfflineClient;
        fwrite(STDERR, "[INFO] Starting OpenFGA MCP Server in OFFLINE MODE\n");
        fwrite(STDERR, "[INFO] Available features: Planning (Prompts) and Coding assistance\n");
        fwrite(STDERR, "[INFO] To enable administrative features, configure OPENFGA_MCP_API_URL\n\n");
    } else {
        // Use real client for full functionality
        $authentication = null;

        if ($hasToken) {
            $authentication = new TokenAuthentication(
                token: getConfiguredString('OPENFGA_MCP_API_TOKEN', ''),
            );
        }

        if ($hasClientId) {
            $authentication = new ClientCredentialAuthentication(
                clientId: getConfiguredString('OPENFGA_MCP_API_CLIENT_ID', ''),
                clientSecret: getConfiguredString('OPENFGA_MCP_API_CLIENT_SECRET', ''),
                issuer: getConfiguredString('OPENFGA_MCP_API_ISSUER', ''),
                audience: getConfiguredString('OPENFGA_MCP_API_AUDIENCE', ''),
            );
        }

        // Use provided URL or default
        $finalUrl = '' !== $apiUrl ? $apiUrl : 'http://127.0.0.1:8080';

        $openfga = new Client(
            url: $finalUrl,
            authentication: $authentication,
        );

        // Validate connection to OpenFGA during startup
        try {
            $openfga->listStores(pageSize: 1)->success(static function (): void {
                // Connection successful
            })->failure(static function (mixed $error): void {
                throw new RuntimeException('Failed to connect to OpenFGA: ' . (string) $error);
            });
        } catch (Throwable $connectionError) {
            fwrite(STDERR, '[WARNING] Could not validate OpenFGA connection: ' . $connectionError->getMessage() . "\n");
            fwrite(STDERR, "[WARNING] The server will start but operations may fail.\n");
            fwrite(STDERR, "[WARNING] Please verify your OPENFGA_MCP_API_URL and authentication settings.\n\n");
        }

        fwrite(STDERR, "[INFO] Starting OpenFGA MCP Server in ONLINE MODE\n");
        fwrite(STDERR, sprintf('[INFO] Connected to: %s%s', $finalUrl, PHP_EOL));
        fwrite(STDERR, "[INFO] All features enabled: Planning, Coding, and Administrative\n\n");
    }

    $container = new BasicContainer;
    $container->set(ClientInterface::class, $openfga);

    $server = Server::make()
        ->withServerInfo('OpenFGA MCP Server', '1.0.0')
        ->withContainer($container)
        ->build();

    $server->discover(
        basePath: __DIR__,
        scanDirs: ['Tools', 'Resources', 'Templates', 'Prompts', 'Completions'],
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
