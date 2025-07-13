<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Resources;

use OpenFGA\ClientInterface;
use OpenFGA\Models\Collections\UsersListInterface;
use OpenFGA\Models\{LeafInterface, NodeInterface, TupleKey, UsersetTreeInterface};
use OpenFGA\Responses\{CheckResponseInterface, ExpandResponseInterface};
use PhpMcp\Server\Attributes\McpResourceTemplate;
use Throwable;

use function array_unique;
use function assert;
use function count;

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
    public function checkPermission(string $storeId, string $user, string $relation, string $object, string $modelId = 'latest'): array
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
    public function expandRelationships(string $storeId, string $object, string $relation): array
    {
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
     * @param  string               $storeId The ID of the store
     * @return array<string, mixed> List of objects
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/objects',
        name: 'OpenFGA Store Objects',
        description: 'List all objects in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listObjects(string $storeId): array
    {
        // Note: OpenFGA's Read API requires specific tuple filters and doesn't support
        // reading all tuples without a filter. This is a known limitation.
        // In a real implementation, you would need to maintain a separate index
        // of objects or use specific queries based on known relations/users.

        return [
            'store_id' => $storeId,
            'objects' => [],
            'count' => 0,
            'note' => 'Reading all objects requires specific tuple filters. Use checkPermission for specific user-relation-object queries.',
        ];
    }

    /**
     * List all relationships (tuples) in a specific OpenFGA store.
     *
     * @param  string               $storeId The ID of the store
     * @return array<string, mixed> List of relationships
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/relationships',
        name: 'OpenFGA Store Relationships',
        description: 'List all relationships (tuples) in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listRelationships(string $storeId): array
    {
        // Note: OpenFGA's Read API requires specific tuple filters and doesn't support
        // reading all tuples without a filter. This is a known limitation.
        // In a real implementation, you would need to maintain a separate index
        // of relationships or use specific queries based on known users/objects.

        return [
            'store_id' => $storeId,
            'relationships' => [],
            'count' => 0,
            'note' => 'Reading all relationships requires specific tuple filters. Use checkPermission for specific user-relation-object queries.',
        ];
    }

    /**
     * List all users in a specific OpenFGA store.
     *
     * @param  string               $storeId The ID of the store
     * @return array<string, mixed> List of users
     */
    #[McpResourceTemplate(
        uriTemplate: 'openfga://store/{storeId}/users',
        name: 'OpenFGA Store Users',
        description: 'List all users in a specific OpenFGA store',
        mimeType: 'application/json',
    )]
    public function listUsers(string $storeId): array
    {
        // Note: OpenFGA's Read API requires specific tuple filters and doesn't support
        // reading all tuples without a filter. This is a known limitation.
        // In a real implementation, you would need to maintain a separate index
        // of users or use specific queries based on known relations/objects.

        return [
            'store_id' => $storeId,
            'users' => [],
            'count' => 0,
            'note' => 'Reading all users requires specific tuple filters. Use checkPermission for specific user-relation-object queries.',
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
