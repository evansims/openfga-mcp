<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use function getConfiguredString;
use function isOfflineMode;

/**
 * Base class for all resource implementations.
 */
abstract readonly class AbstractResources
{
    /**
     * Check if the server is in offline mode.
     *
     * @param  string                    $operation
     * @return array<string, mixed>|null Error response if in offline mode, null otherwise
     */
    protected function checkOfflineMode(string $operation): ?array
    {
        if (isOfflineMode()) {
            return [
                'error' => '❌ ' . $operation . ' requires a live OpenFGA instance. Please configure OPENFGA_MCP_API_URL to enable administrative features.',
            ];
        }

        return null;
    }

    /**
     * Check if the server is in restricted mode and validate store/model access.
     *
     * @param  ?string                   $storeId
     * @param  ?string                   $modelId
     * @return array<string, mixed>|null Error response if restricted, null otherwise
     */
    protected function checkRestrictedMode(?string $storeId = null, ?string $modelId = null): ?array
    {
        if ('true' !== getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false')) {
            return null;
        }

        if (null !== $storeId) {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ('' !== $restrictedStore && $restrictedStore !== $storeId) {
                return [
                    'error' => '❌ The MCP server is configured in restricted mode. You cannot query stores other than ' . $restrictedStore . ' in this mode.',
                ];
            }
        }

        if (null !== $modelId) {
            $restrictedModel = getConfiguredString('OPENFGA_MCP_API_MODEL', '');

            if ('' !== $restrictedModel && $restrictedModel !== $modelId) {
                return [
                    'error' => '❌ The MCP server is configured in restricted mode. You cannot query using authorization models other than ' . $restrictedModel . ' in this mode.',
                ];
            }
        }

        return null;
    }
}
