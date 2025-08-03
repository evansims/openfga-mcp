<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\Responses\ListAuthorizationModelsResponseInterface;
use Override;
use PhpMcp\Server\Contracts\SessionInterface;
use Throwable;

use function assert;

final readonly class ModelIdCompletionProvider extends AbstractCompletions
{
    /**
     * Get completion suggestions for OpenFGA model IDs.
     *
     * This provider attempts to determine the store ID from the session context
     * or falls back to the configured default store.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        // Return ['latest'] in offline mode
        if ($this->isOffline()) {
            return $this->filterCompletions(['latest'], $currentValue);
        }

        try {
            // Try to get store ID from session context
            $storeId = $this->extractStoreIdFromSession($session);

            if (null === $storeId) {
                // If no store ID available, suggest 'latest' as a common option
                return $this->filterCompletions(['latest'], $currentValue);
            }

            // Check if access to this store is restricted
            if ($this->isRestricted($storeId)) {
                return [];
            }

            $modelIds = [];

            $this->client->listAuthorizationModels(store: $storeId)
                ->failure(static function (): void {
                    // If we can't fetch models, modelIds will remain empty
                })
                ->success(static function (mixed $response) use (&$modelIds): void {
                    assert($response instanceof ListAuthorizationModelsResponseInterface);

                    $models = $response->getModels();

                    foreach ($models as $model) {
                        $modelId = $model->getId();

                        if ('' !== $modelId) {
                            $modelIds[] = $modelId;
                        }
                    }
                });

            // Always include 'latest' as an option
            $completions = ['latest', ...$modelIds];

            return $this->filterCompletions($completions, $currentValue);
        } catch (Throwable) {
            // Handle any unexpected errors gracefully
            return $this->filterCompletions(['latest'], $currentValue);
        }
    }
}
