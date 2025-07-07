<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use InvalidArgumentException;
use OpenFGA\Client;
use OpenFGA\Exceptions\{ClientException, ClientThrowable};
use OpenFGA\Models\Collections\{TupleKeys, UserTypeFilters};
use OpenFGA\Models\{TupleKey};
use OpenFGA\Responses\{CheckResponseInterface, ListObjectsResponseInterface, ListUsersResponseInterface, WriteTuplesResponseInterface};
use PhpMcp\Server\Attributes\{McpTool};
use ReflectionException;
use Throwable;

use function assert;
use function is_string;

final readonly class RelationshipTools
{
    public function __construct(
        private Client $client,
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
        $failure = null;
        $success = '';

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
        $failure = null;
        $success = '';

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->writeTuples(store: $store, model: $model, writes: new TupleKeys($tuple))
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to grant permission! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success): void {
                assert($response instanceof WriteTuplesResponseInterface);
                $success = '✅ Permission granted successfully';
            });

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
    /**
     * @param  string               $store
     * @param  string               $model
     * @param  string               $type
     * @param  string               $user
     * @param  string               $relation
     * @return array<string>|string
     */
    public function listObjects(
        string $store,
        string $model,
        string $type,
        string $user,
        string $relation,
    ): string | array {
        $failure = null;
        $success = [];

        $this->client->listObjects(store: $store, model: $model, type: $type, user: $user, relation: $relation)
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
    /**
     * @param  string               $store
     * @param  string               $model
     * @param  string               $object
     * @param  string               $relation
     * @return array<string>|string
     */
    public function listUsers(
        string $store,
        string $model,
        string $object,
        string $relation,
    ): string | array {
        $failure = null;
        $success = [];

        $this->client->listUsers(store: $store, model: $model, object: $object, relation: $relation, userFilters: new UserTypeFilters)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = '❌ Failed to list users! Error: ' . $e->getMessage();
            })
            ->success(static function (mixed $response) use (&$success): void {
                assert($response instanceof ListUsersResponseInterface);

                foreach ($response->getUsers() as $user) {
                    $object = $user->getObject();

                    if (null !== $object && is_string($object)) {
                        $success[] = $object;
                    }
                }
            });

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
        $failure = null;
        $success = '';

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
