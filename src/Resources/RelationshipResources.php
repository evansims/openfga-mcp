<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use OpenFGA\ClientInterface;
use OpenFGA\MCP\Completions\{ModelIdCompletionProvider, ObjectCompletionProvider, RelationCompletionProvider, StoreIdCompletionProvider, UserCompletionProvider};
use OpenFGA\Models\Collections\UsersListInterface;
use OpenFGA\Models\{LeafInterface, NodeInterface, TupleKey, UsersetTreeInterface};
use OpenFGA\Responses\{CheckResponseInterface, ExpandResponseInterface, ReadTuplesResponseInterface};
use PhpMcp\Server\Attributes\{CompletionProvider, McpResourceTemplate};
use Throwable;

use function array_unique;
use function assert;
use function count;
use function in_array;

final readonly class RelationshipResources extends AbstractResources
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Check if a user has a specific permission on an object.
     *
     * @param string $storeId  The ID of the store
     * @param string $user     The user to check (e.g., "user:123")
     * @param string $relation The relation to check (e.g., "reader")
     * @param string $object   The object to check (e.g., "document:456")
     * @param string $modelId  The authorization model ID (defaults to 'latest')
     *
     * @throws Throwable
     *
     * @return array<string, mixed> Permission check result
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/check?user={user}&relation={relation}&object={object}&model={modelId}',
        name: 'OpenFGA Permission Check',
        description: 'Check if a user has a specific permission on an object',
        mimeType: 'application/json',
    )]
    public function checkPermission(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
        #[CompletionProvider(provider: UserCompletionProvider::class)]
        string $user,
        #[CompletionProvider(provider: RelationCompletionProvider::class)]
        string $relation,
        #[CompletionProvider(provider: ObjectCompletionProvider::class)]
        string $object,
        #[CompletionProvider(provider: ModelIdCompletionProvider::class)]
        string $modelId = 'latest',
    ): array {
        $error = $this->checkOfflineMode('Checking permission');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $result = [];
        $called = false;

        $tuple = new TupleKey(
            user: $user,
            relation: $relation,
            object: $object,
        );

        $this->client->check(
            store: $storeId,
            model: $modelId,
            tuple: $tuple,
        )
            ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                $called = true;
                $failure = ['error' => '❌ Failed to check permission! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$result, $user, $relation, $object, &$called): void {
                $called = true;
                assert($response instanceof CheckResponseInterface);

                $result = [
                    'allowed' => $response->getAllowed(),
                    'user' => $user,
                    'relation' => $relation,
                    'object' => $object,
                    'resolution' => $response->getResolution(),
                ];
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (! $called) {
            return [
                'error' => '❌ Promise was not resolved',
            ];
        }

        return $failure ?? $result;
    }

    /**
     * Expand all users who have a specific relation to an object.
     *
     * @param string $storeId  The ID of the store
     * @param string $object   The object to expand (e.g., "document:456")
     * @param string $relation The relation to expand (e.g., "reader")
     *
     * @throws Throwable
     *
     * @return array<string, mixed> Expanded relationships
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/expand?object={object}&relation={relation}',
        name: 'OpenFGA Relationship Expansion',
        description: 'Expand all users who have a specific relation to an object',
        mimeType: 'application/json',
    )]
    public function expandRelationships(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
        #[CompletionProvider(provider: ObjectCompletionProvider::class)]
        string $object,
        #[CompletionProvider(provider: RelationCompletionProvider::class)]
        string $relation,
    ): array {
        $error = $this->checkOfflineMode('Expanding relationships');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $result = [];
        $called = false;

        $tuple = new TupleKey(
            user: '*',
            relation: $relation,
            object: $object,
        );

        $this->client->expand(
            store: $storeId,
            tuple: $tuple,
        )
            ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                $called = true;
                $failure = ['error' => '❌ Failed to expand relationships! Error: ' . $e->getMessage()];
            })
            ->success(function (mixed $response) use (&$result, $object, $relation, &$called): void {
                $called = true;
                assert($response instanceof ExpandResponseInterface);

                $tree = $response->getTree();
                $users = [];

                // Extract users from the expansion tree
                if ($tree instanceof UsersetTreeInterface) {
                    $root = $tree->getRoot();
                    $extractedUsers = $this->extractUsersFromNode($root);
                    $users = array_unique($extractedUsers);
                }

                $result = [
                    'object' => $object,
                    'relation' => $relation,
                    'users' => $users,
                    'count' => count($users),
                ];
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (! $called) {
            return [
                'object' => $object,
                'relation' => $relation,
                'users' => [],
                'count' => 0,
            ];
        }

        return $failure ?? $result;
    }

    /**
     * List all objects in a specific OpenFGA store.
     *
     * @param string $storeId The ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> List of objects
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/objects',
        name: 'OpenFGA Store Objects',
        description: 'List all objects in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listObjects(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
    ): array {
        $error = $this->checkOfflineMode('Listing objects');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $objects = [];
        $continuationToken = null;
        $called = false;

        do {
            $hasMore = false;
            $pageSize = 100;

            // Read tuples with wildcard filters (empty strings act as wildcards)
            $tuple = new TupleKey(
                user: '',
                relation: '',
                object: '',
            );

            $this->client->readTuples(
                store: $storeId,
                tuple: $tuple,
                continuationToken: $continuationToken,
                pageSize: $pageSize,
            )
                ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                    $called = true;
                    $failure = ['error' => '❌ Failed to read tuples! Error: ' . $e->getMessage()];
                })
                ->success(static function (mixed $response) use (&$objects, &$continuationToken, &$hasMore, &$called): void {
                    $called = true;
                    assert($response instanceof ReadTuplesResponseInterface);

                    $tuples = $response->getTuples();

                    foreach ($tuples as $tuple) {
                        $object = $tuple->getKey()->getObject();

                        if (! in_array($object, $objects, true)) {
                            $objects[] = $object;
                        }
                    }

                    $continuationToken = $response->getContinuationToken();
                    $hasMore = null !== $continuationToken && '' !== $continuationToken;
                });

            if (null !== $failure) {
                break;
            }
        } while ($hasMore && $called);

        return $failure ?? [
            'store_id' => $storeId,
            'objects' => $objects,
            'count' => count($objects),
        ];
    }

    /**
     * List all relationships (tuples) in a specific OpenFGA store.
     *
     * @param string $storeId The ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> List of relationships
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/relationships',
        name: 'OpenFGA Store Relationships',
        description: 'List all relationships (tuples) in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listRelationships(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
    ): array {
        $error = $this->checkOfflineMode('Listing relationships');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $relationships = [];
        $continuationToken = null;
        $called = false;

        do {
            $hasMore = false;
            $pageSize = 100;

            // Read tuples with wildcard filters (empty strings act as wildcards)
            $tuple = new TupleKey(
                user: '',
                relation: '',
                object: '',
            );

            $this->client->readTuples(
                store: $storeId,
                tuple: $tuple,
                continuationToken: $continuationToken,
                pageSize: $pageSize,
            )
                ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                    $called = true;
                    $failure = ['error' => '❌ Failed to read tuples! Error: ' . $e->getMessage()];
                })
                ->success(static function (mixed $response) use (&$relationships, &$continuationToken, &$hasMore, &$called): void {
                    $called = true;
                    assert($response instanceof ReadTuplesResponseInterface);

                    $tuples = $response->getTuples();

                    foreach ($tuples as $tuple) {
                        $key = $tuple->getKey();
                        $relationships[] = [
                            'user' => $key->getUser(),
                            'relation' => $key->getRelation(),
                            'object' => $key->getObject(),
                        ];
                    }

                    $continuationToken = $response->getContinuationToken();
                    $hasMore = null !== $continuationToken && '' !== $continuationToken;
                });

            if (null !== $failure) {
                break;
            }
        } while ($hasMore && $called);

        return $failure ?? [
            'store_id' => $storeId,
            'relationships' => $relationships,
            'count' => count($relationships),
        ];
    }

    /**
     * List all users in a specific OpenFGA store.
     *
     * @param string $storeId The ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> List of users
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/users',
        name: 'OpenFGA Store Users',
        description: 'List all users in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listUsers(
        #[CompletionProvider(provider: StoreIdCompletionProvider::class)]
        string $storeId,
    ): array {
        $error = $this->checkOfflineMode('Listing users');

        if (null !== $error) {
            return $error;
        }

        $failure = null;
        $users = [];
        $continuationToken = null;
        $called = false;

        do {
            $hasMore = false;
            $pageSize = 100;

            // Read tuples with wildcard filters (empty strings act as wildcards)
            $tuple = new TupleKey(
                user: '',
                relation: '',
                object: '',
            );

            $this->client->readTuples(
                store: $storeId,
                tuple: $tuple,
                continuationToken: $continuationToken,
                pageSize: $pageSize,
            )
                ->failure(static function (Throwable $e) use (&$failure, &$called): void {
                    $called = true;
                    $failure = ['error' => '❌ Failed to read tuples! Error: ' . $e->getMessage()];
                })
                ->success(static function (mixed $response) use (&$users, &$continuationToken, &$hasMore, &$called): void {
                    $called = true;
                    assert($response instanceof ReadTuplesResponseInterface);

                    $tuples = $response->getTuples();

                    foreach ($tuples as $tuple) {
                        $user = $tuple->getKey()->getUser();

                        if (! in_array($user, $users, true)) {
                            $users[] = $user;
                        }
                    }

                    $continuationToken = $response->getContinuationToken();
                    $hasMore = null !== $continuationToken && '' !== $continuationToken;
                });

            if (null !== $failure) {
                break;
            }
        } while ($hasMore && $called);

        return $failure ?? [
            'store_id' => $storeId,
            'users' => $users,
            'count' => count($users),
        ];
    }

    /**
     * Extract users from a node in the expansion tree.
     *
     * @param  NodeInterface $node The tree node to process
     * @return array<string> List of users
     */
    private function extractUsersFromNode(NodeInterface $node): array
    {
        $users = [];

        // Handle leaf nodes
        $leaf = $node->getLeaf();

        if ($leaf instanceof LeafInterface) {
            $usersList = $leaf->getUsers();

            if ($usersList instanceof UsersListInterface) {
                foreach ($usersList as $userList) {
                    $users[] = $userList->getUser();
                }
            }
        }

        return $users;
    }
}
