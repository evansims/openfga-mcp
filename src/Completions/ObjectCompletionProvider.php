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

final readonly class ObjectCompletionProvider extends AbstractCompletions
{
    /**
     * Get completion suggestions for object identifiers from OpenFGA relationship tuples.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        // Return common object patterns in offline mode
        if ($this->isOffline()) {
            return $this->getCommonObjectPatterns($currentValue);
        }

        try {
            // Try to get store ID from session context
            $storeId = $this->extractStoreIdFromSession($session);

            if (null === $storeId) {
                return $this->getCommonObjectPatterns($currentValue);
            }

            // Check if access to this store is restricted
            if ($this->isRestricted($storeId)) {
                return [];
            }

            // Read relationship tuples to extract objects
            $objects = [];

            // Use wildcard tuple (empty strings act as wildcards) to read all tuples
            $tuple = new TupleKey(
                user: '',
                relation: '',
                object: '',
            );

            $this->client->readTuples(
                store: $storeId,
                tuple: $tuple,
                pageSize: 50,
            )
                ->failure(static function (): void {
                    // If we can't fetch tuples, objects will remain empty
                })
                ->success(static function (mixed $response) use (&$objects): void {
                    assert($response instanceof ReadTuplesResponseInterface);

                    $tuples = $response->getTuples();

                    foreach ($tuples as $tuple) {
                        $object = $tuple->getKey()->getObject();

                        if ('' !== $object) {
                            $objects[] = $object;
                        }
                    }
                });

            if ([] === $objects) {
                return $this->getCommonObjectPatterns($currentValue);
            }

            // Remove duplicates and sort
            $objects = array_unique($objects);
            sort($objects);

            // Limit to reasonable number for performance
            $objects = array_slice($objects, 0, 50);

            return $this->filterCompletions($objects, $currentValue);
        } catch (Throwable) {
            // Handle any unexpected errors gracefully
            return $this->getCommonObjectPatterns($currentValue);
        }
    }

    /**
     * Get common object identifier patterns as fallback.
     *
     * @param  string        $currentValue
     * @return array<string>
     */
    private function getCommonObjectPatterns(string $currentValue): array
    {
        // If the value already has a type prefix, provide common ID suggestions
        if (str_contains($currentValue, ':')) {
            [$type] = explode(':', $currentValue, 2);

            // Provide common ID patterns based on the type
            $suggestions = match ($type) {
                'document', 'doc' => [
                    $type . ':budget',
                    $type . ':plan',
                    $type . ':report',
                    $type . ':proposal',
                ],
                'folder' => [
                    $type . ':root',
                    $type . ':shared',
                    $type . ':public',
                    $type . ':private',
                ],
                'user' => [
                    $type . ':alice',
                    $type . ':bob',
                    $type . ':admin',
                ],
                'group' => [
                    $type . ':admins',
                    $type . ':editors',
                    $type . ':viewers',
                ],
                // For unknown types, suggest generic IDs
                default => [
                    $type . ':1',
                    $type . ':default',
                    $type . ':main',
                ],
            };

            return $this->filterCompletions($suggestions, $currentValue);
        }

        // If no type prefix, suggest common type prefixes
        $commonPatterns = [
            'document:',
            'doc:',
            'folder:',
            'user:',
            'group:',
        ];

        return $this->filterCompletions($commonPatterns, $currentValue);
    }
}
