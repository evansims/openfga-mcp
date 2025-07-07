<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tools;

use OpenFGA\Client;
use OpenFGA\Models\AuthorizationModelInterface;
use OpenFGA\Models\Collections\TupleKeys;
use OpenFGA\Models\Collections\UserTypeFilters;
use OpenFGA\Models\TupleKey;
use OpenFGA\Responses\{CheckResponseInterface, CreateAuthorizationModelResponseInterface, CreateStoreResponseInterface, GetAuthorizationModelResponseInterface, GetStoreResponseInterface, ListAuthorizationModelsResponseInterface, ListObjectsResponseInterface, ListStoresResponseInterface, ListUsersResponseInterface, WriteTuplesResponseInterface};
use PhpMcp\Server\Attributes\{McpTool, McpResource, McpResourceTemplate, McpPrompt, Schema};
use Throwable;

class RelationshipTools
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Check if something has a relation to an object. This answers the question, for example, can "user:1" (user) read (relation) "document:1" (object)?
     *
     * @param string $store ID of the store to use.
     * @param string $model ID of the authorization model to use.
     * @param string $user ID of the user to check.
     * @param string $relation Relation to check.
     * @param string $object ID of the object to check.
     *
     * @return string | array A list of authorization models, or an error message.
     */
    #[McpTool(name: 'check_permission')]
    public function checkPermission(
        string $store,
        string $model,
        string $user,
        string $relation,
        string $object,
    ): string | array
    {
        $failure = null;
        $success = '';

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->check(store: $store, model: $model, tuple: $tuple)
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to check permission! Error: {$e->getMessage()}";
            })
            ->success(function (CheckResponseInterface $response) use (&$success) {
                if ($response->getAllowed()) {
                    $success = "✅ Permission allowed";
                } else {
                    $success = "❌ Permission denied";
                }
            });

        return $failure ?? $success;
    }

    /**
     * Grant permission to something on an object.
     *
     * @param string $store ID of the store to grant permission to.
     * @param string $model ID of the authorization model to grant permission to.
     * @param string $user ID of the user to grant permission to.
     * @param string $relation Relation to grant permission to.
     * @param string $object ID of the object to grant permission to.
     *
     * @return string A success message, or an error message.
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
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to grant permission! Error: {$e->getMessage()}";
            })
            ->success(function (WriteTuplesResponseInterface $response) use (&$success) {
                $success = "✅ Permission granted successfully";
            });

        return $failure ?? $success;
    }

    /**
     * Revoke permission from something on an object.
     *
     * @param string $store ID of the store to revoke permission from.
     * @param string $model ID of the authorization model to revoke permission from.
     * @param string $user ID of the user to revoke permission from.
     * @param string $relation Relation to revoke permission from.
     * @param string $object ID of the object to revoke permission from.
     *
     * @return string A success message, or an error message.
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
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to revoke permission! Error: {$e->getMessage()}";
            })
            ->success(function (WriteTuplesResponseInterface $response) use (&$success) {
                $success = "✅ Permission revoked successfully";
            });

        return $failure ?? $success;
    }

    /**
     * List users that have a given relationship with a given object.
     *
     * @param string $store ID of the store to list users for.
     * @param string $model ID of the authorization model to list users for.
     * @param string $object ID of the object to list users for.
     * @param string $relation Relation to list users for.
     *
     * @return string A list of users, or an error message.
     */
    #[McpTool(name: 'list_users')]
    public function listUsers(
        string $store,
        string $model,
        string $object,
        string $relation,
    ): string | array {
        $failure = null;
        $success = [];

        $this->client->listUsers(store: $store, model: $model, object: $object, relation: $relation, userFilters: new UserTypeFilters())
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to list users! Error: {$e->getMessage()}";
            })
            ->success(function (ListUsersResponseInterface $response) use (&$success) {
                foreach ($response->getUsers() as $user) {
                    $success[] = $user->getObject();
                }
            });

        return $failure ?? $success;
    }

    /**
     * List objects of a type that something has a relation to.
     *
     * @param string $store ID of the store to list objects for.
     * @param string $model ID of the authorization model to list objects for.
     * @param string $user ID of the user to list objects for.
     * @param string $relation Relation to list objects for.
     *
     * @return string A list of objects, or an error message.
     */
    #[McpTool(name: 'list_objects')]
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
            ->failure(function (Throwable $e) use (&$failure) {
                $failure = "❌ Failed to list objects! Error: {$e->getMessage()}";
            })
            ->success(function (ListObjectsResponseInterface $response) use (&$success) {
                foreach ($response->getObjects() as $object) {
                    $success[] = $object;
                }
            });

        return $failure ?? $success;
    }
}