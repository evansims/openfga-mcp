<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use OpenFGA\ClientInterface;
use OpenFGA\Models\Collections\UsersListInterface;
use OpenFGA\Models\{LeafInterface, NodeInterface, TupleKey, UsersetTreeInterface};
use OpenFGA\Responses\{CheckResponseInterface, ExpandResponseInterface, ReadTuplesResponseInterface};
use PhpMcp\Server\Attributes\{McpResourceTemplate};
use Throwable;

use function array_unique;
use function assert;
use function count;
use function explode;

final readonly class RelationshipResources extends AbstractResources
{
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * Check if a user has a specific permission on an object.
     *
     * @param string $storeId  the ID of the store
     * @param string $user     the user to check (e.g., "user:123")
     * @param string $relation the relation to check (e.g., "reader")
     * @param string $object   the object to check (e.g., "document:456")
     *
     * @throws Throwable
     *
     * @return array<string, mixed> permission check result
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/check?user={user}&relation={relation}&object={object}',
        name: 'OpenFGA Permission Check',
        description: 'Check if a user has a specific permission on an object',
        mimeType: 'application/json',
    )]
    public function checkPermission(string $storeId, string $user, string $relation, string $object): array
    {
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
            model: 'latest', // Use latest model
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
        if (!$called) {
            return [
                'error' => '❌ Promise was not resolved',
            ];
        }

        return $failure ?? $result;
    }

    /**
     * Expand all users who have a specific relation to an object.
     *
     * @param string $storeId  the ID of the store
     * @param string $object   the object to expand (e.g., "document:456")
     * @param string $relation the relation to expand (e.g., "reader")
     *
     * @throws Throwable
     *
     * @return array<string, mixed> expanded relationships
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/expand?object={object}&relation={relation}',
        name: 'OpenFGA Relationship Expansion',
        description: 'Expand all users who have a specific relation to an object',
        mimeType: 'application/json',
    )]
    public function expandRelationships(string $storeId, string $object, string $relation): array
    {
        $failure = null;
        $result = [];
        $called = false;

        $tuple = new TupleKey(
            user: '*',  // Wildcard for expand
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
        if (!$called) {
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
     * @param string $storeId the ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> list of objects
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/objects',
        name: 'OpenFGA Store Objects',
        description: 'List all objects in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listObjects(string $storeId): array
    {
        $failure = null;
        $objects = [];
        $uniqueObjects = [];
        $called = false;

        // We need to read all tuples and extract unique objects
        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure, &$called, $storeId): void {
                $called = true;
                $message = $e->getMessage();
                
                // If the error is network.invalid, it might be due to empty store - treat as empty result
                if (str_contains($message, 'exception.network.invalid')) {
                    $failure = [
                        'store_id' => $storeId,
                        'objects' => [],
                        'count' => 0,
                    ];
                } else {
                    $failure = ['error' => '❌ Failed to fetch objects! Error: ' . $message];
                }
            })
            ->success(static function (mixed $response) use (&$objects, &$uniqueObjects, &$called): void {
                $called = true;
                assert($response instanceof ReadTuplesResponseInterface);

                $tuples = $response->getTuples();

                foreach ($tuples as $tuple) {
                    $objectStr = $tuple->getKey()->getObject();

                    // Extract unique objects
                    if (! isset($uniqueObjects[$objectStr])) {
                        $uniqueObjects[$objectStr] = true;
                        // Parse object type and id
                        $parts = explode(':', $objectStr, 2);
                        $objectType = $parts[0] ?? 'unknown';
                        $objectId = $parts[1] ?? $objectStr;
                        $objects[] = [
                            'object' => $objectStr,
                            'type' => $objectType,
                            'id' => $objectId,
                        ];
                    }
                }
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (!$called) {
            return [
                'store_id' => $storeId,
                'objects' => [],
                'count' => 0,
            ];
        }

        return $failure ?? [
            'store_id' => $storeId,
            'objects' => $objects,
            'count' => count($objects),
        ];
    }

    /**
     * List all relationships (tuples) in a specific OpenFGA store.
     *
     * @param string $storeId the ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> list of relationships
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/relationships',
        name: 'OpenFGA Store Relationships',
        description: 'List all relationships (tuples) in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listRelationships(string $storeId): array
    {
        $failure = null;
        $relationships = [];
        $called = false;

        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure, &$called, $storeId): void {
                $called = true;
                $message = $e->getMessage();
                
                // If the error is network.invalid, it might be due to empty store - treat as empty result
                if (str_contains($message, 'exception.network.invalid')) {
                    $failure = [
                        'store_id' => $storeId,
                        'relationships' => [],
                        'count' => 0,
                    ];
                } else {
                    $failure = ['error' => '❌ Failed to fetch relationships! Error: ' . $message];
                }
            })
            ->success(static function (mixed $response) use (&$relationships, &$called): void {
                $called = true;
                assert($response instanceof ReadTuplesResponseInterface);

                $tuples = $response->getTuples();

                foreach ($tuples as $tuple) {
                    $relationship = [
                        'user' => $tuple->getKey()->getUser(),
                        'relation' => $tuple->getKey()->getRelation(),
                        'object' => $tuple->getKey()->getObject(),
                    ];

                    // Timestamp not available from interface

                    $relationships[] = $relationship;
                }
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (!$called) {
            return [
                'store_id' => $storeId,
                'relationships' => [],
                'count' => 0,
            ];
        }

        return $failure ?? [
            'store_id' => $storeId,
            'relationships' => $relationships,
            'count' => count($relationships),
        ];
    }

    /**
     * List all users in a specific OpenFGA store.
     *
     * @param string $storeId the ID of the store
     *
     * @throws Throwable
     *
     * @return array<string, mixed> list of users
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/users',
        name: 'OpenFGA Store Users',
        description: 'List all users in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listUsers(string $storeId): array
    {
        $failure = null;
        $users = [];
        $uniqueUsers = [];
        $called = false;

        // We need to read all tuples and extract unique users
        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure, &$called, $storeId): void {
                $called = true;
                $message = $e->getMessage();
                
                // If the error is network.invalid, it might be due to empty store - treat as empty result
                if (str_contains($message, 'exception.network.invalid')) {
                    $failure = [
                        'store_id' => $storeId,
                        'users' => [],
                        'count' => 0,
                    ];
                } else {
                    $failure = ['error' => '❌ Failed to fetch users! Error: ' . $message];
                }
            })
            ->success(static function (mixed $response) use (&$users, &$uniqueUsers, &$called): void {
                $called = true;
                assert($response instanceof ReadTuplesResponseInterface);

                $tuples = $response->getTuples();

                foreach ($tuples as $tuple) {
                    $userStr = $tuple->getKey()->getUser();

                    // Extract unique users
                    if (! isset($uniqueUsers[$userStr])) {
                        $uniqueUsers[$userStr] = true;
                        // Parse user type and id
                        $parts = explode(':', $userStr, 2);
                        $userType = $parts[0] ?? 'unknown';
                        $userId = $parts[1] ?? $userStr;
                        $users[] = [
                            'user' => $userStr,
                            'type' => $userType,
                            'id' => $userId,
                        ];
                    }
                }
            });

        // If neither callback was called, it means the promise chain wasn't resolved
        if (!$called) {
            return [
                'store_id' => $storeId,
                'users' => [],
                'count' => 0,
            ];
        }

        $result = $failure ?? [
            'store_id' => $storeId,
            'users' => $users,
            'count' => count($users),
        ];
        
        return $result;
    }

    /**
     * Extract users from a node in the expansion tree.
     *
     * @param  NodeInterface $node the tree node to process
     * @return array<string> list of users
     */
    private function extractUsersFromNode(NodeInterface $node): array
    {
        $users = [];

        // Check if this is a leaf node
        $leaf = $node->getLeaf();

        if ($leaf instanceof LeafInterface) {
            $usersList = $leaf->getUsers();

            if ($usersList instanceof UsersListInterface) {
                // The users list contains UsersListUserInterface objects
                foreach ($usersList as $userList) {
                    // UsersListUserInterface has getUser() method that returns string
                    $users[] = $userList->getUser();
                }
            }
        }

        // Check union nodes - these would contain multiple nodes
        $union = $node->getUnion();

        if (null !== $union) {
            // Union nodes would have child nodes - implementation depends on actual interface
            // For now, we'll skip recursive processing
        }

        // Check intersection nodes
        $intersection = $node->getIntersection();

        if (null !== $intersection) {
            // Similar to union, would need recursive processing
        }

        return $users;
    }
}
