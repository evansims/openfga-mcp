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

            $this->client->readTuples(
                store: $storeId,
                tuple: new TupleKey(
                    user: '',
                    relation: '',
                    object: '',
                ),
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
        $commonPatterns = [
            'document:budget',
            'document:plan',
            'document:report',
            'folder:project',
            'folder:shared',
            'repository:main',
            'repository:backend',
            'workspace:team',
            'workspace:public',
            'organization:company',
        ];

        return $this->filterCompletions($commonPatterns, $currentValue);
    }
}
