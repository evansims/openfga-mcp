<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class searchDocumentationException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $query  The search query that failed
     * @param string $error  Error message explaining the failure
     * @param string $status Status message indicating search failure
     */
    public function __construct(
        private string $query,
        private string $error,
        private string $status = '❌ Search failed',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                              $query
     * @param  string                                              $error
     * @param  string                                              $status
     * @return array{status: string, query: string, error: string}
     */
    public static function create(
        string $query,
        string $error,
        string $status = '❌ Search failed',
    ): array {
        return (new self($query, $error, $status))->jsonSerialize();
    }

    /**
     * Serialize the response to JSON format.
     *
     * @return array{status: string, query: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'query' => $this->query,
            'error' => $this->error,
        ];
    }
}
