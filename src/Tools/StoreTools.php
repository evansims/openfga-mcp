<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use DateTimeInterface;
use InvalidArgumentException;
use OpenFGA\ClientInterface;
use OpenFGA\Responses\{CreateStoreResponseInterface, GetStoreResponseInterface, ListStoresResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use Throwable;

use function assert;
use function is_object;
use function is_string;
use function sprintf;

final readonly class StoreTools extends AbstractTools
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Create a new OpenFGA store.
     *
     * @param string $name the name of the store to create
     *
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'create_store')]
    public function createStore(
        string $name,
    ): string {
        $error = $this->checkOfflineMode('Creating stores');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkWritePermission('create stores');

        if (null !== $error) {
            return $error;
        }

        $error = $this->checkRestrictedModeForWrites('create stores');

        if (null !== $error) {
            return $error;
        }

        $this->client->createStore(name: $name)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to create store! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $store) use ($name, &$success): void {
                // The success callback receives the response directly
                if ($store instanceof CreateStoreResponseInterface) {
                    $success = sprintf('✅ Successfully created store named %s! Store names are not unique identifiers, so please use the ID %s for future queries relating to this specific store.', $name, $store->getId());
                } else {
                    // Handle case where we get a different response format
                    $storeId = 'unknown';

                    if (is_object($store) && method_exists($store, 'getId')) {
                        /** @var mixed $idValue */
                        $idValue = $store->getId();

                        if (is_string($idValue)) {
                            $storeId = $idValue;
                        }
                    }
                    $success = sprintf('✅ Successfully created store named %s! Store ID: %s', $name, $storeId);
                }
            });

        return $failure ?? $success;
    }

    /**
     * Delete an OpenFGA store.
     *
     * @param string $id the ID of the store to delete
     *
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'delete_store')]
    public function deleteStore(
        string $id,
    ): string {
        $error = $this->checkOfflineMode('Deleting stores');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkWritePermission('delete stores');

        if (null !== $error) {
            return $error;
        }

        $error = $this->checkRestrictedModeForWrites('delete stores');

        if (null !== $error) {
            return $error;
        }

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
     * @param string $id the ID of the store to get details for
     *
     * @throws Throwable
     *
     * @return array{id: string, name: string, created_at: DateTimeInterface, updated_at: DateTimeInterface, deleted_at: DateTimeInterface|null}|string the store details, or an error message
     */
    #[McpTool(name: 'get_store')]
    public function getStore(
        string $id,
    ): string | array {
        $error = $this->checkOfflineMode('Getting store details');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkRestrictedMode(storeId: $id);

        if (null !== $error) {
            return $error;
        }

        $this->client->getStore(store: $id)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to get store! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $store) use (&$success): void {
                assert($store instanceof GetStoreResponseInterface);
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
     * @throws InvalidArgumentException
     * @throws Throwable
     *
     * @return array<array{id: string, name: string, created_at: DateTimeInterface, updated_at: DateTimeInterface, deleted_at: DateTimeInterface|null}>|string a list of stores, or an error message
     */
    #[McpTool(name: 'list_stores')]
    public function listStores(): string | array
    {
        $error = $this->checkOfflineMode('Listing stores');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = [];

        $this->client->listStores()
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list stores! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $stores) use (&$success): void {
                assert($stores instanceof ListStoresResponseInterface);

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
