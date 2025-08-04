<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getDocumentationChunkException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $chunkId The chunk ID that caused the error
     * @param string $sdk     The SDK identifier
     * @param string $error   The error message
     * @param string $status  Status message
     */
    public function __construct(
        private string $chunkId,
        private string $sdk,
        private string $error,
        private string $status = '❌ Error loading documentation chunk',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                                              $chunkId
     * @param  string                                                              $sdk
     * @param  string                                                              $error
     * @param  string                                                              $status
     * @return array{status: string, chunk_id: string, sdk: string, error: string}
     */
    public static function create(
        string $chunkId,
        string $sdk,
        string $error,
        string $status = '❌ Error loading documentation chunk',
    ): array {
        return (new self($chunkId, $sdk, $error, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, chunk_id: string, sdk: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'chunk_id' => $this->chunkId,
            'sdk' => $this->sdk,
            'error' => $this->error,
        ];
    }
}
