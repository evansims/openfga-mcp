<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\ClientInterface;
use PhpMcp\Server\Contracts\{CompletionProviderInterface, SessionInterface};

use function getConfiguredString;
use function isOfflineMode;

abstract readonly class AbstractCompletions implements CompletionProviderInterface
{
    public function __construct(
        protected ClientInterface $client,
    ) {
    }

    /**
     * Extract store ID from session context or completion arguments.
     *
     * @param SessionInterface $session
     */
    protected function extractStoreIdFromSession(SessionInterface $session): ?string
    {
        // Try to get store ID from configured default
        $configuredStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

        if ('' !== $configuredStore) {
            return $configuredStore;
        }

        // In a real implementation, we might extract this from the completion context
        // For now, return null to indicate we should list all stores
        return null;
    }

    /**
     * Filter completions based on the current input value.
     *
     * @param  array<string> $completions
     * @param  string        $currentValue
     * @return array<string>
     */
    protected function filterCompletions(array $completions, string $currentValue): array
    {
        if ('' === $currentValue) {
            return $completions;
        }

        return array_values(array_filter(
            $completions,
            static fn (string $completion): bool => str_starts_with(strtolower($completion), strtolower($currentValue)),
        ));
    }

    /**
     * Check if the server is in offline mode.
     *
     * @return bool True if in offline mode, false otherwise
     */
    protected function isOffline(): bool
    {
        return isOfflineMode();
    }

    /**
     * Check if the server is in restricted mode and validate store access.
     *
     * @param  ?string $storeId
     * @return bool    True if access should be restricted, false otherwise
     */
    protected function isRestricted(?string $storeId = null): bool
    {
        if ('true' !== getConfiguredString('OPENFGA_MCP_API_RESTRICT', 'false')) {
            return false;
        }

        if (null !== $storeId) {
            $restrictedStore = getConfiguredString('OPENFGA_MCP_API_STORE', '');

            if ('' !== $restrictedStore && $restrictedStore !== $storeId) {
                return true;
            }
        }

        return false;
    }
}
