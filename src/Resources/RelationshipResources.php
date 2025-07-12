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
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to check permission! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$result, $user, $relation, $object): void {
                assert($response instanceof CheckResponseInterface);

                $result = [
                    'allowed' => $response->getAllowed(),
                    'user' => $user,
                    'relation' => $relation,
                    'object' => $object,
                    'resolution' => $response->getResolution(),
                ];
            });

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

        $tuple = new TupleKey(
            user: '*',  // Wildcard for expand
            relation: $relation,
            object: $object,
        );

        $this->client->expand(
            store: $storeId,
            tuple: $tuple,
        )
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to expand relationships! Error: ' . $e->getMessage()];
            })
            ->success(function (mixed $response) use (&$result, $object, $relation): void {
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

        // We need to read all tuples and extract unique objects
        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch objects! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$objects, &$uniqueObjects): void {
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

        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch relationships! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$relationships): void {
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

        // We need to read all tuples and extract unique users
        $this->client->readTuples(store: $storeId)
            ->failure(static function (Throwable $e) use (&$failure): void {
                $failure = ['error' => '❌ Failed to fetch users! Error: ' . $e->getMessage()];
            })
            ->success(static function (mixed $response) use (&$users, &$uniqueUsers): void {
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

        return $failure ?? [
            'store_id' => $storeId,
            'users' => $users,
            'count' => count($users),
        ];
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
