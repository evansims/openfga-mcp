<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class searchDocumentationNoResultsResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $query         The search query that returned no results
     * @param array<string> $availableSdks List of available SDKs to help user refine search
     * @param string        $status        Status message indicating no results found
     * @param string        $suggestion    Suggestion for the user to improve their search
     */
    public function __construct(
        private string $query,
        private array $availableSdks,
        private string $status = '❌ No results found',
        private string $suggestion = 'Try broader search terms or check spelling',
    ) {
    }

    /**
     * Create and serialize the no results response in one step.
     *
     * @param  string                                                                                  $query
     * @param  array<string>                                                                           $availableSdks
     * @param  string                                                                                  $status
     * @param  string                                                                                  $suggestion
     * @return array{status: string, query: string, suggestion: string, available_sdks: array<string>}
     */
    public static function create(
        string $query,
        array $availableSdks,
        string $status = '❌ No results found',
        string $suggestion = 'Try broader search terms or check spelling',
    ): array {
        return (new self($query, $availableSdks, $status, $suggestion))->jsonSerialize();
    }

    /**
     * Serialize the response to JSON format.
     *
     * @return array{status: string, query: string, suggestion: string, available_sdks: array<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'query' => $this->query,
            'suggestion' => $this->suggestion,
            'available_sdks' => $this->availableSdks,
        ];
    }
}
