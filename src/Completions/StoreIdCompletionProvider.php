<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\Responses\ListStoresResponseInterface;
use Override;
use PhpMcp\Server\Contracts\SessionInterface;
use Throwable;

use function assert;

final readonly class StoreIdCompletionProvider extends AbstractCompletions
{
    /**
     * Get completion suggestions for OpenFGA store IDs.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        // Return empty array in offline mode
        if ($this->isOffline()) {
            return [];
        }

        try {
            $storeIds = [];

            $this->client->listStores()
                ->failure(static function (): void {
                    // If we can't fetch stores, storeIds will remain empty
                })
                ->success(static function (mixed $response) use (&$storeIds): void {
                    assert($response instanceof ListStoresResponseInterface);

                    $stores = $response->getStores();

                    foreach ($stores as $store) {
                        $storeId = $store->getId();

                        if ('' !== $storeId) {
                            $storeIds[] = $storeId;
                        }
                    }
                });

            return $this->filterCompletions($storeIds, $currentValue);
        } catch (Throwable) {
            // Handle any unexpected errors gracefully
            return [];
        }
    }
}
