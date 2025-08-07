<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Psr\Log\{LoggerInterface, NullLogger};

use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function strlen;

final readonly class ConfigurationParser
{
    /**
     * @var array<string, string> List of all supported configuration keys and their types
     */
    private const array SUPPORTED_CONFIG = [
        // Core OpenFGA Connection
        'OPENFGA_MCP_API_URL' => 'string',
        'OPENFGA_MCP_API_TOKEN' => 'string',
        'OPENFGA_MCP_API_CLIENT_ID' => 'string',
        'OPENFGA_MCP_API_CLIENT_SECRET' => 'string',
        'OPENFGA_MCP_API_ISSUER' => 'string',
        'OPENFGA_MCP_API_AUDIENCE' => 'string',

        // Security & Access Control
        'OPENFGA_MCP_API_WRITEABLE' => 'bool',
        'OPENFGA_MCP_API_RESTRICT' => 'bool',
        'OPENFGA_MCP_API_STORE' => 'string',
        'OPENFGA_MCP_API_MODEL' => 'string',

        // Transport Configuration (rarely set via query params but supported)
        'OPENFGA_MCP_TRANSPORT' => 'string',
        'OPENFGA_MCP_TRANSPORT_HOST' => 'string',
        'OPENFGA_MCP_TRANSPORT_PORT' => 'int',
        'OPENFGA_MCP_TRANSPORT_SSE' => 'bool',
        'OPENFGA_MCP_TRANSPORT_STATELESS' => 'bool',

        // Debug & Logging
        'OPENFGA_MCP_DEBUG' => 'bool',
    ];

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Parse JSON configuration and apply to $_ENV.
     *
     * @param  string              $jsonConfig JSON-encoded configuration string
     * @return ConfigurationResult Result object with status and details
     */
    public function parseAndApply(string $jsonConfig): ConfigurationResult
    {
        $errors = [];
        $appliedKeys = [];
        $appliedValues = [];

        // Decode JSON
        $config = json_decode($jsonConfig, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $errors[] = sprintf('Invalid JSON: %s', json_last_error_msg());

            return new ConfigurationResult(false, $errors, $appliedKeys, $appliedValues);
        }

        if (! is_array($config)) {
            $errors[] = 'Configuration must be a JSON object';

            return new ConfigurationResult(false, $errors, $appliedKeys, $appliedValues);
        }

        // Process each configuration value
        /** @var mixed $value */
        foreach ($config as $key => $value) {
            // Check if key is supported
            if (! isset(self::SUPPORTED_CONFIG[$key])) {
                $this->logger->warning('Unsupported configuration key', ['key' => $key]);

                continue; // Skip unsupported keys silently
            }

            // Validate and convert value based on expected type
            $expectedType = self::SUPPORTED_CONFIG[$key];
            $processedValue = $this->processValue($value, $expectedType, $key, $errors);

            if (null !== $processedValue) {
                // Apply to $_ENV (highest precedence)
                $_ENV[$key] = $processedValue;
                $appliedKeys[] = $key;
                $appliedValues[$key] = $processedValue;

                $this->logger->debug('Configuration value set', [
                    'key' => $key,
                    'type' => $expectedType,
                    'value' => $this->maskSensitiveValue($key, $processedValue),
                ]);
            }
        }

        // Validate configuration combinations
        $this->validateConfigurationCombinations($appliedValues, $errors);

        return new ConfigurationResult(
            [] === $errors,
            $errors,
            $appliedKeys,
            $appliedValues,
        );
    }

    /**
     * Mask sensitive values for logging.
     *
     * @param  string $key   Configuration key
     * @param  string $value Configuration value
     * @return string Masked value if sensitive, original otherwise
     */
    private function maskSensitiveValue(string $key, string $value): string
    {
        $sensitiveKeys = [
            'OPENFGA_MCP_API_TOKEN',
            'OPENFGA_MCP_API_CLIENT_SECRET',
        ];

        if (in_array($key, $sensitiveKeys, true) && 4 < strlen($value)) {
            return substr($value, 0, 4) . str_repeat('*', min(12, strlen($value) - 4));
        }

        return $value;
    }

    /**
     * Process and validate a single configuration value.
     *
     * @param  mixed         $value        The raw value from JSON
     * @param  string        $expectedType The expected type (string, int, bool)
     * @param  string        $key          The configuration key (for error messages)
     * @param  array<string> $errors       Array to collect error messages
     * @return string|null   Processed value or null if invalid
     */
    private function processValue(mixed $value, string $expectedType, string $key, array &$errors): ?string
    {
        switch ($expectedType) {
            case 'string':
                if (! is_string($value) && ! is_numeric($value)) {
                    $errors[] = sprintf('%s must be a string, %s given', $key, gettype($value));

                    return null;
                }

                return (string) $value;

            case 'int':
                if (! is_numeric($value)) {
                    $errors[] = sprintf('%s must be numeric, %s given', $key, gettype($value));

                    return null;
                }

                return (string) (int) $value;

            case 'bool':
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true)) {
                    return in_array(strtolower($value), ['true', '1'], true) ? 'true' : 'false';
                }

                if (is_numeric($value)) {
                    return ((bool) $value) ? 'true' : 'false';
                }
                $errors[] = sprintf('%s must be a boolean, %s given', $key, gettype($value));

                return null;

            default:
                $errors[] = sprintf('Unknown type %s for key %s', $expectedType, $key);

                return null;
        }
    }

    /**
     * Validate that certain configuration combinations are valid.
     *
     * @param array<string, string> $config Applied configuration values
     * @param array<string>         $errors Array to collect error messages
     */
    private function validateConfigurationCombinations(array $config, array &$errors): void
    {
        // If OAuth2 client credentials are provided, all related fields must be present
        $hasClientId = isset($config['OPENFGA_MCP_API_CLIENT_ID']) && '' !== $config['OPENFGA_MCP_API_CLIENT_ID'];
        $hasClientSecret = isset($config['OPENFGA_MCP_API_CLIENT_SECRET']) && '' !== $config['OPENFGA_MCP_API_CLIENT_SECRET'];
        $hasIssuer = isset($config['OPENFGA_MCP_API_ISSUER']) && '' !== $config['OPENFGA_MCP_API_ISSUER'];
        $hasAudience = isset($config['OPENFGA_MCP_API_AUDIENCE']) && '' !== $config['OPENFGA_MCP_API_AUDIENCE'];

        if (($hasClientId || $hasClientSecret) && (! $hasClientId || ! $hasClientSecret || ! $hasIssuer || ! $hasAudience)) {
            $errors[] = 'OAuth2 client credentials require all of: OPENFGA_MCP_API_CLIENT_ID, OPENFGA_MCP_API_CLIENT_SECRET, OPENFGA_MCP_API_ISSUER, OPENFGA_MCP_API_AUDIENCE';
        }

        // If restricted mode is enabled, store and model must be provided
        $isRestricted = isset($config['OPENFGA_MCP_API_RESTRICT']) && 'true' === $config['OPENFGA_MCP_API_RESTRICT'];

        if ($isRestricted) {
            $hasStore = isset($config['OPENFGA_MCP_API_STORE']) && '' !== $config['OPENFGA_MCP_API_STORE'];
            $hasModel = isset($config['OPENFGA_MCP_API_MODEL']) && '' !== $config['OPENFGA_MCP_API_MODEL'];

            if (! $hasStore || ! $hasModel) {
                $errors[] = 'Restricted mode requires both OPENFGA_MCP_API_STORE and OPENFGA_MCP_API_MODEL to be set';
            }
        }
    }
}
