<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use function getConfiguredString;

abstract readonly class AbstractTools
{
    /**
     * Check if the server is in read-only mode.
     *
     * @param  string      $operation
     * @return string|null Error message if in read-only mode, null otherwise
     */
    protected function checkReadOnlyMode(string $operation): ?string
    {
        if ('true' === getConfiguredString('OPENFGA_MCP_API_READONLY', 'false')) {
            return '❌ The MCP server is configured in read only mode. You cannot ' . $operation . ' in this mode.';
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
}
