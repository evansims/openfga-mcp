<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Responses\{CreateStoreResponseInterface, GetStoreResponseInterface, ListStoresResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use Throwable;

use function sprintf;

final class StoreTools
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * Create a new OpenFGA store.
     *
     * @param  string $name the name of the store to create
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'create_store')]
    public function createStore(
        string $name,
    ): string {
        $failure = null;
        $success = '';

        $this->client->createStore(name: $name)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create store! Error: ' . $e->getMessage();
            })
            ->success(static function (CreateStoreResponseInterface $store) use ($name, &$success): void {
                $success = sprintf('✅ Successfully created store named %s! Store names are not unique identifiers, so please use the ID %s for future queries relating to this specific store.', $name, $store->getId());
            });

        return $failure ?? $success;
    }

    /**
     * Delete an OpenFGA store.
     *
     * @param  string $id the ID of the store to delete
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'delete_store')]
    public function deleteStore(
        string $id,
    ): string {
        $failure = null;
        $success = '';

        $this->client->deleteStore(store: $id)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to delete store! Error: ' . $e->getMessage();
            })
            ->success(static function () use (&$success): void {
                $success = '✅ Successfully deleted store!';
            });

        return $failure ?? $success;
    }

    /**
     * Get an OpenFGA store details.
     *
     * @param  string $id the ID of the store to get details for
     * @return string the store details, or an error message
     */
    #[McpTool(name: 'get_store')]
    public function getStore(
        string $id,
    ): string | array {
        $failure = null;
        $success = '';

        $this->client->getStore(store: $id)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get store! Error: ' . $e->getMessage();
            })
            ->success(static function (GetStoreResponseInterface $store) use (&$success): void {
                $success = [
                    'id' => $store->getId(),
                    'name' => $store->getName(),
                    'created_at' => $store->getCreatedAt(),
                    'updated_at' => $store->getUpdatedAt(),
                    'deleted_at' => $store->getDeletedAt(),
                ];
            });

        return $failure ?? $success;
    }

    /**
     * List all OpenFGA stores.
     *
     * @return array{id: string, name: string}|string a list of stores, or an error message
     */
    #[McpTool(name: 'list_stores')]
    public function listStores(): string | array
    {
        $failure = null;
        $success = [];

        $this->client->listStores()
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list stores! Error: ' . $e->getMessage();
            })
            ->success(static function (ListStoresResponseInterface $stores) use (&$success): void {
                foreach ($stores->getStores() as $store) {
                    $success[] = [
                        'id' => $store->getId(),
                        'name' => $store->getName(),
                        'created_at' => $store->getCreatedAt(),
                        'updated_at' => $store->getUpdatedAt(),
                        'deleted_at' => $store->getDeletedAt(),
                    ];
                }
            });

        return $failure ?? $success;
    }
}
