<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\Models\TupleKey;
use OpenFGA\Responses\ReadTuplesResponseInterface;
use Override;
use PhpMcp\Server\Contracts\SessionInterface;
use Throwable;

use function array_slice;
use function assert;

final readonly class UserCompletionProvider extends AbstractCompletions
{
    /**
     * Get completion suggestions for user identifiers from OpenFGA relationship tuples.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        try {
            // Try to get store ID from session context
            $storeId = $this->extractStoreIdFromSession($session);

            if (null === $storeId) {
                return $this->getCommonUserPatterns($currentValue);
            }

            // Check if access to this store is restricted
            if ($this->isRestricted($storeId)) {
                return [];
            }

            // Read relationship tuples to extract users
            $users = [];

            $this->client->readTuples(
                store: $storeId,
                tuple: new TupleKey(
                    user: '',
                    relation: '',
                    object: '',
                ),
            )
                ->failure(static function (): void {
                    // If we can't fetch tuples, users will remain empty
                })
                ->success(static function (mixed $response) use (&$users): void {
                    assert($response instanceof ReadTuplesResponseInterface);

                    $tuples = $response->getTuples();

                    foreach ($tuples as $tuple) {
                        $user = $tuple->getKey()->getUser();

                        if ('' !== $user) {
                            $users[] = $user;
                        }
                    }
                });

            if ([] === $users) {
                return $this->getCommonUserPatterns($currentValue);
            }

            // Remove duplicates and sort
            $users = array_unique($users);
            sort($users);

            // Limit to reasonable number for performance
            $users = array_slice($users, 0, 50);

            return $this->filterCompletions($users, $currentValue);
        } catch (Throwable) {
            // Handle any unexpected errors gracefully
            return $this->getCommonUserPatterns($currentValue);
        }
    }

    /**
     * Get common user identifier patterns as fallback.
     *
     * @param  string        $currentValue
     * @return array<string>
     */
    private function getCommonUserPatterns(string $currentValue): array
    {
        $commonPatterns = [
            'user:alice',
            'user:bob',
            'user:admin',
            'user:guest',
            'group:admins',
            'group:users',
            'group:viewers',
            'service:api',
            'service:backend',
        ];

        return $this->filterCompletions($commonPatterns, $currentValue);
    }
}
