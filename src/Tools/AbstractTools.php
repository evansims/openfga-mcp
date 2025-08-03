<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use function getConfiguredString;
use function isOfflineMode;

abstract readonly class AbstractTools
{
    /**
     * Check if the server is in offline mode.
     *
     * @param  string      $operation
     * @return string|null Error message if in offline mode, null otherwise
     */
    protected function checkOfflineMode(string $operation): ?string
    {
        if (isOfflineMode()) {
            return '❌ ' . $operation . ' requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.';
        }

        return null;
    }

    /**
     * Check if the server is in restricted mode and validate store/model access.
     *
     * @param  ?string     $storeId
     * @param  ?string     $modelId
     * @return string|null Error message if restricted, null otherwise
     */
    protected function checkRestrictedMode(?string $storeId = null, ?string $modelId = null): ?string
    {
        if ('true' !== getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false')) {
            return null;
        }

        if (null !== $storeId) {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ('' !== $restrictedStore && $restrictedStore !== $storeId) {
                return '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.';
            }
        }

        if (null !== $modelId) {
            $restrictedModel = getConfiguredString('OPENFGA_MCP_API_MODEL', '');

            if ('' !== $restrictedModel && $restrictedModel !== $modelId) {
                return '❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than ' . $restrictedModel . ' in this mode.';
            }
        }

        return null;
    }

    /**
     * Check if the server is in restricted mode for write operations.
     *
     * @param  string      $operation
     * @return string|null Error message if restricted, null otherwise
     */
    protected function checkRestrictedModeForWrites(string $operation): ?string
    {
        if ('true' === getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false')) {
            return '❌ The MCP server is configured in restricted mode. You cannot ' . $operation . ' in this mode.';
        }

        return null;
    }

    /**
     * Check if write operations are allowed.
     *
     * @param  string      $operation
     * @return string|null Error message if writes are not allowed, null otherwise
     */
    protected function checkWritePermission(string $operation): ?string
    {
        // Write operations require explicit opt-in via OPENFGA_MCP_API_WRITEABLE
        if ('true' !== getConfiguredString('OPENFGA_MCP_API_WRITEABLE', 'false')) {
            return '❌ Write operations are disabled for safety. To enable ' . $operation . ', set OPENFGA_MCP_API_WRITEABLE=true.';
        }

        return null;
    }
}
