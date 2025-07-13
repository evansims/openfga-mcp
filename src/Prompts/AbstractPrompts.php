<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Prompts;

use function getConfiguredString;

abstract readonly class AbstractPrompts
{
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
                return '❌ The MCP server is configured in restricted mode. You cannot access guidance for stores other than ' . $restrictedStore . ' in this mode.';
            }
        }

        if (null !== $modelId) {
            $restrictedModel = getConfiguredString('OPENFGA_MCP_API_MODEL', '');

            if ('' !== $restrictedModel && $restrictedModel !== $modelId) {
                return '❌ The MCP server is configured in restricted mode. You cannot access guidance for authorization models other than ' . $restrictedModel . ' in this mode.';
            }
        }

        return null;
    }

    /**
     * Create an error message response for prompts.
     *
     * @param  string|null                       $error
     * @return array<int, array<string, string>>
     */
    protected function createErrorResponse(?string $error): array
    {
        return [
            ['role' => 'system', 'content' => $error ?? 'Unknown error'],
        ];
    }

    /**
     * Check if error condition is met and return appropriate response.
     *
     * @param string|null $error
     */
    protected function hasError(?string $error): bool
    {
        return null !== $error;
    }
}
