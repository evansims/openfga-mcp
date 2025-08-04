<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models\Factories;

use InvalidArgumentException;
use OpenFGA\MCP\Models\Builders\SearchResultBuilder;
use OpenFGA\MCP\Models\{GuideDocumentation, SdkDocumentation, SearchResult};

use function count;
use function is_array;
use function is_scalar;
use function sprintf;

/**
 * Factory for creating documentation-related models.
 */
final class DocumentationFactory
{
    /**
     * Creates a GuideDocumentation instance from a guide overview array.
     *
     * @param string               $type
     * @param array<string, mixed> $overview
     */
    public static function createGuideDocumentation(string $type, array $overview): ?GuideDocumentation
    {
        if (! isset($overview['name'], $overview['sections'], $overview['total_chunks'])) {
            return null;
        }

        try {
            return new GuideDocumentation(
                type: $type,
                name: is_scalar($overview['name']) ? (string) $overview['name'] : '',
                sections: is_array($overview['sections']) ? count($overview['sections']) : 0,
                chunks: is_numeric($overview['total_chunks']) ? (int) $overview['total_chunks'] : 0,
                uri: sprintf('openfga://docs/%s', $type),
            );
        } catch (InvalidArgumentException) {
            // Log validation error if needed
            return null;
        }
    }

    /**
     * Creates an SdkDocumentation instance from an SDK overview array.
     *
     * @param string               $sdk
     * @param array<string, mixed> $overview
     */
    public static function createSdkDocumentation(string $sdk, array $overview): ?SdkDocumentation
    {
        if (! isset($overview['name'], $overview['sections'], $overview['classes'], $overview['total_chunks'])) {
            return null;
        }

        try {
            return new SdkDocumentation(
                sdk: $sdk,
                name: is_scalar($overview['name']) ? (string) $overview['name'] : '',
                sections: is_array($overview['sections']) ? count($overview['sections']) : 0,
                classes: is_array($overview['classes']) ? count($overview['classes']) : 0,
                chunks: is_numeric($overview['total_chunks']) ? (int) $overview['total_chunks'] : 0,
                uri: sprintf('openfga://docs/%s', $sdk),
            );
        } catch (InvalidArgumentException) {
            // Log validation error if needed
            return null;
        }
    }

    /**
     * Creates multiple SdkDocumentation instances from an SDK list.
     *
     * @param  array<string>                           $sdkList
     * @param  callable(string): ?array<string, mixed> $overviewProvider
     * @return array<SdkDocumentation>
     */
    public static function createSdkDocumentationList(array $sdkList, callable $overviewProvider): array
    {
        $documentation = [];

        foreach ($sdkList as $sdk) {
            $overview = $overviewProvider($sdk);

            if (null === $overview) {
                continue;
            }

            $doc = self::createSdkDocumentation($sdk, $overview);

            if ($doc instanceof SdkDocumentation) {
                $documentation[] = $doc;
            }
        }

        return $documentation;
    }

    /**
     * Creates a SearchResult instance from a search result array.
     *
     * @param array<string, mixed> $result
     */
    public static function createSearchResult(array $result): ?SearchResult
    {
        if (! isset($result['chunk_id'], $result['sdk'], $result['score'], $result['preview'])) {
            return null;
        }

        try {
            // Normalize score to 0.0-1.0 range
            // Search scores typically range from 0-100 or similar
            $rawScore = is_numeric($result['score']) ? (float) $result['score'] : 0.0;
            $normalizedScore = min(1.0, max(0.0, $rawScore / 100.0));

            return new SearchResult(
                chunkId: is_scalar($result['chunk_id']) ? (string) $result['chunk_id'] : '',
                sdk: is_scalar($result['sdk']) ? (string) $result['sdk'] : '',
                score: $normalizedScore,
                preview: is_scalar($result['preview']) ? (string) $result['preview'] : '',
                metadata: isset($result['metadata']) && is_array($result['metadata']) ? $result['metadata'] : [],
                uri: sprintf(
                    'openfga://docs/%s/chunk/%s',
                    is_scalar($result['sdk']) ? (string) $result['sdk'] : '',
                    is_scalar($result['chunk_id']) ? (string) $result['chunk_id'] : '',
                ),
            );
        } catch (InvalidArgumentException) {
            // Log validation error if needed
            return null;
        }
    }

    /**
     * Creates multiple SearchResult instances from search results.
     *
     * @param  array<array<string, mixed>> $results
     * @return array<SearchResult>
     */
    public static function createSearchResults(array $results): array
    {
        $searchResults = [];

        foreach ($results as $result) {
            $searchResult = self::createSearchResult($result);

            if ($searchResult instanceof SearchResult) {
                $searchResults[] = $searchResult;
            }
        }

        return $searchResults;
    }

    /**
     * Creates a SearchResultBuilder instance for fluent construction.
     */
    public static function searchResultBuilder(): SearchResultBuilder
    {
        return SearchResultBuilder::create();
    }

    /**
     * Creates a SearchResultBuilder pre-populated from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function searchResultBuilderFromArray(array $data): SearchResultBuilder
    {
        return SearchResultBuilder::create()->fromArray($data);
    }
}
