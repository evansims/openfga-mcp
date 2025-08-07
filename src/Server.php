<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Helpers.php';

use OpenFGA\Authentication\{ClientCredentialAuthentication, TokenAuthentication};
use OpenFGA\{Client, ClientInterface};
use OpenFGA\MCP\{ConfigurableHttpServerTransport, DebugLogger, LoggingStdioTransport, OfflineClient};
use OpenFGA\MCP\Documentation\DocumentationIndexSingleton;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\{StdioServerTransport};

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
        scanDirs: ['Tools', 'Resources', 'Prompts', 'Completions'],
        saveToCache: false,
    );

    // Initialize documentation index early for better performance
    fwrite(STDERR, "[INFO] Initializing documentation index...\n");

    try {
        $startTime = microtime(true);
        $docIndex = DocumentationIndexSingleton::getInstance();
        $docIndex->initialize();
        $endTime = microtime(true);
        $loadTime = round(($endTime - $startTime) * 1000.0, 2);

        // Get statistics about loaded documentation
        $sdks = $docIndex->getSdkList();
        $sdkCount = count($sdks);
        fwrite(STDERR, "[INFO] Documentation index initialized successfully in {$loadTime}ms\n");
        fwrite(STDERR, "[INFO] Loaded documentation for {$sdkCount} SDKs\n");

        // Show details about each SDK
        foreach ($sdks as $sdk) {
            $overview = $docIndex->getSdkOverview($sdk);

            if (null !== $overview) {
                $classCount = count($overview['classes']);
                $sectionCount = count($overview['sections']);
                $chunkCount = $overview['total_chunks'];
                fwrite(STDERR, "[INFO]   - {$overview['name']}: {$classCount} classes, {$sectionCount} sections, {$chunkCount} chunks\n");
            }
        }

        // Also check for general documentation
        foreach (['general', 'authoring'] as $generalDoc) {
            $overview = $docIndex->getSdkOverview($generalDoc);

            if (null !== $overview) {
                $sectionCount = count($overview['sections']);
                $chunkCount = $overview['total_chunks'];
                fwrite(STDERR, "[INFO]   - {$overview['name']}: {$sectionCount} sections, {$chunkCount} chunks\n");
            }
        }

        DebugLogger::logServerLifecycle('documentation_initialized', [
            'load_time_ms' => $loadTime,
            'sdk_count' => $sdkCount,
            'sdks' => $docIndex->getSdkList(),
        ]);
    } catch (Throwable $docError) {
        fwrite(STDERR, '[WARNING] Failed to initialize documentation index: ' . $docError->getMessage() . "\n");
        fwrite(STDERR, "[WARNING] Documentation features will initialize on first use\n");

        DebugLogger::logServerLifecycle('documentation_initialization_failed', [
            'error' => $docError->getMessage(),
            'file' => $docError->getFile(),
            'line' => $docError->getLine(),
        ]);
    }

    $transport = match (getConfiguredString('OPENFGA_MCP_TRANSPORT')) {
        'http' => new ConfigurableHttpServerTransport(
            host: getConfiguredString('OPENFGA_MCP_TRANSPORT_HOST', '127.0.0.1'),
            port: getConfiguredInt('OPENFGA_MCP_TRANSPORT_PORT', 9090),
            enableJsonResponse: false === getConfiguredBool('OPENFGA_MCP_TRANSPORT_SSE', true),
            stateless: getConfiguredBool('OPENFGA_MCP_TRANSPORT_STATELESS', false),
        ),
        default => getConfiguredBool('OPENFGA_MCP_DEBUG', true)
            ? new LoggingStdioTransport
            : new StdioServerTransport,
    };

    // Log server startup
    DebugLogger::logServerLifecycle('startup', [
        'version' => '1.0.0',
        'mode' => $isOfflineMode ? 'offline' : 'online',
        'transport' => '' !== getConfiguredString('OPENFGA_MCP_TRANSPORT', '') ? getConfiguredString('OPENFGA_MCP_TRANSPORT', '') : 'stdio',
        'debug' => getConfiguredBool('OPENFGA_MCP_DEBUG', true),
        'api_url' => $isOfflineMode ? null : ('' !== $apiUrl ? $apiUrl : 'http://127.0.0.1:8080'),
    ]);

    // Register exception handler for uncaught exceptions
    set_exception_handler(static function (Throwable $exception): void {
        // Check if this is the CallToolRequest stdClass issue
        if (
            $exception instanceof TypeError
            && str_contains($exception->getMessage(), 'CallToolRequest::__construct()')
            && str_contains($exception->getMessage(), 'must be of type array, stdClass given')
        ) {
            // Log the specific issue
            DebugLogger::logServerLifecycle('calltoolrequest_stdclass_error', [
                'error' => $exception->getMessage(),
                'workaround' => 'Tool called without arguments - this is a known MCP client issue',
                'suggestion' => 'MCP client should provide empty array [] instead of empty object {} for tool arguments',
            ]);

            // Log helpful message to stderr
            fwrite(STDERR, "[TOOL CALL ERROR] MCP client called tool without proper arguments\n");
            fwrite(STDERR, "This is a known issue where empty arguments {} should be sent as [] instead\n");
            fwrite(STDERR, "Tool call was ignored - client should retry with proper arguments\n");

            // Don't exit - let the server continue running
            return;
        }

        DebugLogger::logServerLifecycle('uncaught_exception', [
            'error' => $exception->getMessage(),
            'class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Also log to stderr for visibility
        fwrite(STDERR, '[UNCAUGHT EXCEPTION] ' . $exception->getMessage() . "\n");
        fwrite(STDERR, 'File: ' . $exception->getFile() . ':' . $exception->getLine() . "\n");

        // Exit with error code
        exit(1);
    });

    // Register error handler for fatal errors
    set_error_handler(static function ($severity, $message, $file, $line): false {
        // Check if this is a fatal error type
        if (($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE)) !== 0) {
            DebugLogger::logServerLifecycle('fatal_error', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            // Return false to let PHP's internal error handler also process it
            return false;
        }

        // For non-fatal errors, let PHP handle them normally
        return false;
    });

    // Register shutdown handler to catch fatal errors and abnormal terminations
    register_shutdown_function(static function (): void {
        $error = error_get_last();

        // Check if shutdown was due to a fatal error
        if (null !== $error && (($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE)) !== 0)) {
            DebugLogger::logServerLifecycle('fatal_shutdown', [
                'reason' => 'fatal_error',
                'error' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]);
        } else {
            // Normal shutdown
            DebugLogger::logServerLifecycle('shutdown', [
                'reason' => 'normal_termination',
            ]);
        }
    });

    // Register signal handlers for graceful shutdown
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, static function (): void {
            DebugLogger::logServerLifecycle('shutdown', [
                'reason' => 'SIGTERM',
            ]);

            exit(0);
        });

        pcntl_signal(SIGINT, static function (): void {
            DebugLogger::logServerLifecycle('shutdown', [
                'reason' => 'SIGINT',
            ]);

            exit(0);
        });
    }

    $server->listen($transport);
} catch (Throwable $throwable) {
    fwrite(STDERR, '[CRITICAL ERROR] ' . $throwable->getMessage() . "\n");

    // Log critical error
    DebugLogger::logServerLifecycle('critical_error', [
        'error' => $throwable->getMessage(),
        'file' => $throwable->getFile(),
        'line' => $throwable->getLine(),
        'trace' => $throwable->getTraceAsString(),
    ]);

    exit(1);
}
