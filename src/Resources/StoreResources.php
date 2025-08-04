<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use DateTimeInterface;
use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\StoreIdCompletionProvider;
use OpenFGA\Responses\{GetStoreResponseInterface, ListAuthorizationModelsResponseInterface, ListStoresResponseInterface};
use PhpMcp\Server\Attributes\{CompletionProvider, McpResource, McpResourceTemplate};
use Throwable;

use function assert;
use function count;

final readonly class StoreResources extends AbstractResources
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Get detailed information about a specific OpenFGA store.
     *
     * @param string $storeId The ID of the store to fetch
     *
     * @throws Throwable
     *
     * @return array<string, mixed> Store details
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}',
        name: 'get_store',
        description: 'Get detailed information about a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function getStore(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
    ): array {
        $error = $this->checkOfflineMode('Fetching store details');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $storeData = [];

        $this->client->getStore(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch store! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$storeData): void {
                assert($response instanceof GetStoreResponseInterface);

                $storeData = [
                    'id' => $response->getId(),
                    'name' => $response->getName(),
                    'created_at' => $response->getCreatedAt()->format(DateTimeInterface::ATOM),
                    'updated_at' => $response->getUpdatedAt()->format(DateTimeInterface::ATOM),
                    'deleted_at' => $response->getDeletedAt()?->format(DateTimeInterface::ATOM),
                ];
            });

        return $failure ?? $storeData;
    }

    /**
     * List all authorization models in a specific OpenFGA store.
     *
     * @param string $storeId The ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> List of models in the store
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/models',
        name: 'list_models',
        description: 'List all authorization models in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listStoreModels(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
    ): array {
        $error = $this->checkOfflineMode('Listing store models');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $models = [];

        $this->client->listAuthorizationModels(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch models! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$models): void {
                assert($response instanceof ListAuthorizationModelsResponseInterface);

                foreach ($response->getModels() as $model) {
                    $typeDefinitions = $model->getTypeDefinitions();
                    $models[] = [
                        'id' => $model->getId(),
                        'created_at' => null, // Not available from interface
                        'schema_version' => '1.1', // Default schema version
                        'type_definitions' => count($typeDefinitions),
                    ];
                }
            });

        return $failure ?? [
            'store_id' => $storeId,
            'models' => $models,
            'count' => count($models),
        ];
    }

    /**
     * List all available OpenFGA stores.
     *
     * @throws Throwable
     *
     * @return array<string, mixed> List of stores with their details
     */
    #[McpResource(
        uri: 'openfga://stores',
        name: 'list_stores',
        description: 'List all available OpenFGA stores',
        mimeType: 'application/json',
    )]
    public function listStores(): array
    {
        $error = $this->checkOfflineMode('Listing stores');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $stores = [];

        $this->client->listStores()
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch stores! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$stores): void {
                assert($response instanceof ListStoresResponseInterface);

                foreach ($response->getStores() as $store) {
                    $stores[] = [
                        'id' => $store->getId(),
                        'name' => $store->getName(),
                        'created_at' => $store->getCreatedAt()->format(DateTimeInterface::ATOM),
                        'updated_at' => $store->getUpdatedAt()->format(DateTimeInterface::ATOM),
                        'deleted_at' => $store->getDeletedAt()?->format(DateTimeInterface::ATOM),
                    ];
                }
            });

        return $failure ?? [
            'stores' => $stores,
            'count' => count($stores),
        ];
    }
}
