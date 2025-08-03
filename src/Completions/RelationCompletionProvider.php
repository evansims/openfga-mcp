<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Models\Collections\TypeDefinitionRelationsInterface;
use OpenFGA\Responses\GetAuthorizationModelResponseInterface;
use Override;
use PhpMcp\Server\Contracts\SessionInterface;
use Throwable;

use function assert;

final readonly class RelationCompletionProvider extends AbstractCompletions
{
    /**
     * Get completion suggestions for relation names from OpenFGA authorization models.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        // Return common relations in offline mode
        if ($this->isOffline()) {
            return $this->getCommonRelations($currentValue);
        }

        try {
            // Try to get store ID from session context
            $storeId = $this->extractStoreIdFromSession($session);

            if (null === $storeId) {
                return $this->getCommonRelations($currentValue);
            }

            // Check if access to this store is restricted
            if ($this->isRestricted($storeId)) {
                return [];
            }

            // Get the latest authorization model
            $relations = [];

            $this->client->getAuthorizationModel(store: $storeId, model: 'latest')
                ->failure(static function (): void {
                    // If we can't fetch the model, relations will remain empty
                })
                ->success(static function (mixed $response) use (&$relations): void {
                    assert($response instanceof GetAuthorizationModelResponseInterface);

                    $model = $response->getModel();

                    if ($model instanceof AuthorizationModelInterface) {
                        $typeDefinitions = $model->getTypeDefinitions();

                        foreach ($typeDefinitions as $typeDefinition) {
                            $typeRelations = $typeDefinition->getRelations();

                            if ($typeRelations instanceof TypeDefinitionRelationsInterface) {
                                // TypeDefinitionRelations is iterable - iterate to get relation names
                                foreach ($typeRelations as $relationName => $_) {
                                    if ('' !== $relationName) {
                                        $relations[] = $relationName;
                                    }
                                }
                            }
                        }
                    }
                });

            if ([] === $relations) {
                return $this->getCommonRelations($currentValue);
            }

            // Remove duplicates and sort
            $relations = array_unique($relations);
            sort($relations);

            return $this->filterCompletions($relations, $currentValue);
        } catch (Throwable) {
            // Handle any unexpected errors gracefully
            return $this->getCommonRelations($currentValue);
        }
    }

    /**
     * Get common relation names as fallback.
     *
     * @param  string        $currentValue
     * @return array<string>
     */
    private function getCommonRelations(string $currentValue): array
    {
        $commonRelations = [
            'viewer',
            'reader',
            'editor',
            'writer',
            'owner',
            'member',
            'admin',
        ];

        return $this->filterCompletions($commonRelations, $currentValue);
    }
}
