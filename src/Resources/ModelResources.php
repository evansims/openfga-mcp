<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use OpenFGA\ClientInterface;
use OpenFGA\Models\{AuthorizationModelInterface};
use OpenFGA\Models\Collections\TypeDefinitionRelationsInterface;
use OpenFGA\Responses\{GetAuthorizationModelResponseInterface, ListAuthorizationModelsResponseInterface};
use PhpMcp\Server\Attributes\{McpResourceTemplate};
use Throwable;

use function assert;
use function count;

final readonly class ModelResources extends AbstractResources
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Get the latest authorization model in a store.
     *
     * @param string $storeId the ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> latest model details
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/model/latest',
        name: 'OpenFGA Latest Model',
        description: 'Get the latest authorization model in a store',
        mimeType: 'application/json',
    )]
    public function getLatestModel(string $storeId): array
    {
        $failure = null;
        $modelData = [];

        $this->client->listAuthorizationModels(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch models! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$modelData, $storeId): void {
                assert($response instanceof ListAuthorizationModelsResponseInterface);

                $models = $response->getModels();

                if (0 === count($models)) {
                    $modelData = ['error' => '❌ No models found in the store'];

                    return;
                }

                // The first model in the list is the latest one
                $latestModel = $models[0];

                if ($latestModel instanceof AuthorizationModelInterface) {
                    $modelData = [
                        'store_id' => $storeId,
                        'id' => $latestModel->getId(),
                        'schema_version' => '1.1', // Default schema version
                        'created_at' => null, // Not available from interface
                        'type_definitions' => [],
                        'is_latest' => true,
                    ];
                } else {
                    return;
                }

                $typeDefinitions = $latestModel->getTypeDefinitions();

                foreach ($typeDefinitions as $typeDefinition) {
                    $typeInfo = [
                        'type' => $typeDefinition->getType(),
                        'relations' => [],
                    ];

                    $relations = $typeDefinition->getRelations();

                    if ($relations instanceof TypeDefinitionRelationsInterface) {
                        // Relations is a collection, extract the names
                        foreach ($relations as $name => $_) {
                            $typeInfo['relations'][] = $name;
                        }
                    }

                    $modelData['type_definitions'][] = $typeInfo;
                }

                $modelData['type_count'] = count($modelData['type_definitions']);
            });

        return $failure ?? $modelData;
    }

    /**
     * Get detailed information about a specific authorization model.
     *
     * @param string $storeId the ID of the store
     * @param string $modelId the ID of the model
     *
     * @throws Throwable
     *
     * @return array<string, mixed> model details
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/model/{modelId}',
        name: 'OpenFGA Model Details',
        description: 'Get detailed information about a specific authorization model',
        mimeType: 'application/json',
    )]
    public function getModel(string $storeId, string $modelId): array
    {
        $failure = null;
        $modelData = [];

        $this->client->getAuthorizationModel(store: $storeId, model: $modelId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch model! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$modelData): void {
                assert($response instanceof GetAuthorizationModelResponseInterface);

                $authModel = $response->getModel();

                if ($authModel instanceof AuthorizationModelInterface) {
                    $modelData = [
                        'id' => $authModel->getId(),
                        'schema_version' => '1.1', // Default schema version
                        'created_at' => null, // Not available from interface
                        'type_definitions' => [],
                    ];
                } else {
                    return;
                }

                $typeDefinitions = $authModel->getTypeDefinitions();

                foreach ($typeDefinitions as $typeDefinition) {
                    $typeInfo = [
                        'type' => $typeDefinition->getType(),
                        'relations' => [],
                    ];

                    $relations = $typeDefinition->getRelations();

                    if ($relations instanceof TypeDefinitionRelationsInterface) {
                        // Relations is a collection, extract the names
                        foreach ($relations as $name => $_) {
                            $typeInfo['relations'][] = $name;
                        }
                    }

                    $modelData['type_definitions'][] = $typeInfo;
                }

                $modelData['type_count'] = count($modelData['type_definitions']);
            });

        return $failure ?? $modelData;
    }
}
