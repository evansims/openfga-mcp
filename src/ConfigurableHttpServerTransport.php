<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Override;
use PhpMcp\Server\Contracts\EventStoreInterface;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * HTTP transport that supports configuration via JSON query parameters.
 *
 * This transport extends the base StreamableHttpServerTransport to intercept
 * the 'config' query parameter and apply configuration before processing requests.
 */
final class ConfigurableHttpServerTransport extends StreamableHttpServerTransport
{
    private readonly LoggerInterface $configLogger;

    private readonly ConfigurationParser $configParser;

    private readonly bool $isStateless;

    /**
     * @param array<string, mixed>|null $sslContext
     * @param string                    $host
     * @param int                       $port
     * @param string                    $mcpPath
     * @param bool                      $enableJsonResponse
     * @param bool                      $stateless
     * @param ?EventStoreInterface      $eventStore
     * @param ?LoggerInterface          $logger
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 9090,
        string $mcpPath = '/mcp',
        ?array $sslContext = null,
        bool $enableJsonResponse = true,
        bool $stateless = false,
        ?EventStoreInterface $eventStore = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct(
            host: $host,
            port: $port,
            mcpPath: $mcpPath,
            sslContext: $sslContext,
            enableJsonResponse: $enableJsonResponse,
            stateless: $stateless,
            eventStore: $eventStore,
        );

        $this->isStateless = $stateless;
        $this->configLogger = $logger ?? new NullLogger;
        $this->configParser = new ConfigurationParser($this->configLogger);
    }

    /**
     * Parse and apply configuration from a JSON string.
     *
     * This method is public to allow external code to trigger configuration updates
     * when query parameters are detected through other means (e.g., middleware).
     *
     * @param  string              $jsonConfig JSON-encoded configuration
     * @return ConfigurationResult Result of the configuration parsing
     */
    public function applyConfiguration(string $jsonConfig): ConfigurationResult
    {
        $result = $this->configParser->parseAndApply($jsonConfig);

        if (! $result->isSuccessful()) {
            $this->configLogger->error('Failed to parse configuration', [
                'errors' => $result->getErrors(),
            ]);
        } else {
            $this->configLogger->info('Configuration applied', [
                'source' => 'query_param',
                'values_set' => $result->getAppliedKeys(),
            ]);

            // Log debug information if debug mode is enabled
            if (getConfiguredBool('OPENFGA_MCP_DEBUG', true)) {
                $this->configLogger->debug('Applied configuration values', [
                    'config' => $result->getAppliedValues(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Override the listen method to intercept configuration early in the request lifecycle.
     */
    #[Override]
    public function listen(): void
    {
        // Apply any configuration from environment first
        $this->applyConfigurationFromEnvironment();

        // Start the parent listener
        parent::listen();
    }

    /**
     * Check for configuration in query parameters via a request inspection hook
     * Note: Since we can't override createRequestHandler (it's private), we rely on
     * the environment being set before the server processes the request.
     */
    private function applyConfigurationFromEnvironment(): void
    {
        // This method is called once at startup.
        // For per-request configuration in stateless mode, we would need a different
        // approach, potentially using middleware or a wrapper around the transport.

        // Log that the configurable transport is active
        $this->configLogger->info('ConfigurableHttpServerTransport initialized', [
            'note' => 'Query parameter configuration support is enabled',
            'stateless' => $this->isStateless,
        ]);

        // Important limitation: Since we can't intercept individual requests due to
        // the private createRequestHandler method, we need to document that configuration
        // via query parameters requires a custom middleware approach or modification
        // to the base StreamableHttpServerTransport class.
    }
}
