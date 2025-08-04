<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use OpenFGA\MCP\Models\SearchResult;
use Override;

use function array_map;
use function count;

final readonly class searchDocumentationResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string              $query        The search query that was executed
     * @param array<SearchResult> $results      Array of search results
     * @param string              $status       Status message indicating successful search
     * @param int|null            $totalResults Total number of results found
     */
    public function __construct(
        private string $query,
        private array $results,
        private string $status = '✅ Search Results',
        private ?int $totalResults = null,
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                                                                                             $query
     * @param  array<SearchResult>                                                                                                                                                                $results
     * @param  string                                                                                                                                                                             $status
     * @param  int|null                                                                                                                                                                           $totalResults
     * @return array{status: string, query: string, total_results: int, results: array<array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>, uri: string}>}
     */
    public static function create(
        string $query,
        array $results,
        string $status = '✅ Search Results',
        ?int $totalResults = null,
    ): array {
        return (new self($query, $results, $status, $totalResults))->jsonSerialize();
    }

    /**
     * Serialize the response to JSON format.
     *
     * @return array{status: string, query: string, total_results: int, results: array<array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>, uri: string}>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status . ' for: ' . $this->query,
            'query' => $this->query,
            'total_results' => $this->totalResults ?? count($this->results),
            'results' => array_map(static fn (SearchResult $result): array => $result->jsonSerialize(), $this->results),
        ];
    }
}
