<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Responses\{CreateStoreResponseInterface, GetStoreResponseInterface, ListStoresResponseInterface};
use PhpMcp\Server\Attributes\{McpTool, McpResource, McpResourceTemplate, McpPrompt, Schema};
use Throwable;

class StoreTools
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Create a new OpenFGA store.
     *
     * @param string $name The name of the store to create.
     *
     * @return string A success message, or an error message.
     */
    #[McpTool(name: 'create_store')]
    public function createStore(
        string $name,
    ): string {
        $failure = null;
        $success = '';

        $this->client->createStore(name: $name)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to create store! Error: {$e->getMessage()}";
            })
            ->success(function (CreateStoreResponseInterface $store) use ($name, &$success) {
                $success = "✅ Successfully created store named {$name}! Store names are not unique identifiers, so please use the ID {$store->getId()} for future queries relating to this specific store.";
            });

        return $failure ?? $success;
    }

    /**
     * Delete an OpenFGA store.
     *
     * @param string $id The ID of the store to delete.
     *
     * @return string A success message, or an error message.
     */
    #[McpTool(name: 'delete_store')]
    public function deleteStore(
        string $id,
    ): string {
        $failure = null;
        $success = '';

        $this->client->deleteStore(store: $id)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to delete store! Error: {$e->getMessage()}";
            })
            ->success(function () use (&$success) {
                $success = "✅ Successfully deleted store!";
            });

        return $failure ?? $success;
    }

    /**
     * Get an OpenFGA store details.
     *
     * @param string $id The ID of the store to get details for.
     *
     * @return string The store details, or an error message.
     */
    #[McpTool(name: 'get_store')]
    public function getStore(
        string $id,
    ): string | array {
        $failure = null;
        $success = '';

        $this->client->getStore(store: $id)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to get store! Error: {$e->getMessage()}";
            })
            ->success(function (GetStoreResponseInterface $store) use (&$success) {
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
     * @return string|array{id: string, name: string} A list of stores, or an error message.
     */
    #[McpTool(name: 'list_stores')]
    public function listStores(): string | array
    {
        $failure = null;
        $success = [];

        $this->client->listStores()
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to list stores! Error: {$e->getMessage()}";
            })
            ->success(function (ListStoresResponseInterface $stores) use (&$success) {
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