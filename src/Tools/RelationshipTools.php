<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use InvalidArgumentException;
use OpenFGA\ClientInterface;
use OpenFGA\Exceptions\{ClientException, ClientThrowable};
use OpenFGA\Models\Collections\{TupleKeys, UserTypeFilters};
use OpenFGA\Models\{TupleKey, UserObjectInterface, UserTypeFilter};
use OpenFGA\Responses\{CheckResponseInterface, ListObjectsResponseInterface, ListUsersResponseInterface, WriteTuplesResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use ReflectionException;
use Throwable;

use function assert;
use function is_string;

final readonly class RelationshipTools extends AbstractTools
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Check if something has a relation to an object. This answers the question, for example, can "user:1" (user) read (relation) "document:1" (object)?
     *
     * @param string $store    ID of the store to use
     * @param string $model    ID of the authorization model to use
     * @param string $user     ID of the user to check
     * @param string $relation relation to check
     * @param string $object   ID of the object to check
     *
     * @throws ClientException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @return string A success or error message
     */
    #[McpTool(name: 'check_permission')]
    public function checkPermission(
        string $store,
        string $model,
        string $user,
        string $relation,
        string $object,
    ): string {
        $error = $this->checkOfflineMode('Checking permissions');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->check(store: $store, model: $model, tuple: $tuple)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to check permission! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success): void {
                assert($response instanceof CheckResponseInterface);
                $allowed = $response->getAllowed();

                $success = true === $allowed ? '✅ Permission allowed' : '❌ Permission denied';
            });

        return $failure ?? $success;
    }

    /**
     * Grant permission to something on an object.
     *
     * @param string $store    ID of the store to grant permission to
     * @param string $model    ID of the authorization model to grant permission to
     * @param string $user     ID of the user to grant permission to
     * @param string $relation relation to grant permission to
     * @param string $object   ID of the object to grant permission to
     *
     * @throws ClientException
     * @throws ClientThrowable
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'grant_permission')]
    public function grantPermission(
        string $store,
        string $model,
        string $user,
        string $relation,
        string $object,
    ): string {
        $error = $this->checkOfflineMode('Granting permissions');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';
        $called = false;

        $error = $this->checkWritePermission('grant permissions');

        if (null !== $error) {
            return $error;
        }

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->writeTuples(store: $store, model: $model, writes: new TupleKeys($tuple))
            ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                $called = true;
                $failure = '❌ Failed to grant permission! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success, &$called): void {
                $called = true;
                assert($response instanceof WriteTuplesResponseInterface);
                $success = '✅ Permission granted successfully';
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (! $called) {
            return '❌ Promise was not resolved';
        }

        return $failure ?? $success;
    }

    /**
     * List objects of a type that something has a relation to.
     *
     * @param string $store    ID of the store to list objects for
     * @param string $model    ID of the authorization model to list objects for
     * @param string $type     Type of objects to list
     * @param string $user     ID of the user to list objects for
     * @param string $relation relation to list objects for
     *
     * @throws Throwable
     *
     * @return array<string>|string a list of objects, or an error message
     */
    #[McpTool(name: 'list_objects')]
    public function listObjects(
        string $store,
        string $model,
        string $type,
        string $user,
        string $relation,
    ): string | array {
        $error = $this->checkOfflineMode('Listing objects');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = [];

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $this->client->listObjects(store: $store, model: $model, type: $type, relation: $relation, user: $user)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list objects! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success): void {
                assert($response instanceof ListObjectsResponseInterface);

                foreach ($response->getObjects() as $object) {
                    $success[] = $object;
                }
            });

        return $failure ?? $success;
    }

    /**
     * List users that have a given relationship with a given object.
     *
     * @param string $store    ID of the store to list users for
     * @param string $model    ID of the authorization model to list users for
     * @param string $object   ID of the object to list users for
     * @param string $relation relation to list users for
     *
     * @throws ClientThrowable
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @return array<string>|string a list of users, or an error message
     */
    #[McpTool(name: 'list_users')]
    public function listUsers(
        string $store,
        string $model,
        string $object,
        string $relation,
    ): string | array {
        $error = $this->checkOfflineMode('Listing users');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = [];
        $called = false;

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        // Create a filter for 'user' type - this is the most common case
        // The API requires exactly 1 user filter
        $userFilter = new UserTypeFilter(type: 'user');
        $userFilters = new UserTypeFilters($userFilter);

        $this->client->listUsers(store: $store, model: $model, object: $object, relation: $relation, userFilters: $userFilters)
            ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                $called = true;
                $failure = '❌ Failed to list users! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success, &$called): void {
                $called = true;
                assert($response instanceof ListUsersResponseInterface);

                foreach ($response->getUsers() as $user) {
                    $userIdentifier = $user->getObject();

                    if (null !== $userIdentifier) {
                        if (is_string($userIdentifier)) {
                            $success[] = $userIdentifier;
                        } elseif ($userIdentifier instanceof UserObjectInterface) {
                            // Construct the user identifier from type and id
                            $success[] = $userIdentifier->getType() . ':' . $userIdentifier->getId();
                        }
                    }
                }
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (! $called) {
            return '❌ Promise was not resolved';
        }

        return $failure ?? $success;
    }

    /**
     * Revoke permission from something on an object.
     *
     * @param string $store    ID of the store to revoke permission from
     * @param string $model    ID of the authorization model to revoke permission from
     * @param string $user     ID of the user to revoke permission from
     * @param string $relation relation to revoke permission from
     * @param string $object   ID of the object to revoke permission from
     *
     * @throws ClientException
     * @throws ClientThrowable
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Throwable
     *
     * @return string a success message, or an error message
     */
    #[McpTool(name: 'revoke_permission')]
    public function revokePermission(
        string $store,
        string $model,
        string $user,
        string $relation,
        string $object,
    ): string {
        $error = $this->checkOfflineMode('Revoking permissions');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $success = '';

        $error = $this->checkWritePermission('revoke permissions');

        if (null !== $error) {
            return $error;
        }

        $error = $this->checkRestrictedMode(storeId: $store, modelId: $model);

        if (null !== $error) {
            return $error;
        }

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->writeTuples(store: $store, model: $model, deletes: new TupleKeys($tuple))
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to revoke permission! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success): void {
                assert($response instanceof WriteTuplesResponseInterface);
                $success = '✅ Permission revoked successfully';
            });

        return $failure ?? $success;
    }
}
