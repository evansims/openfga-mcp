<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getDocumentationChunkNotFoundException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $requestedChunk The chunk ID that was requested but not found
     * @param string $sdk            The SDK identifier
     * @param string $note           Additional note about the error
     * @param string $status         Status message
     */
    public function __construct(
        private string $requestedChunk,
        private string $sdk,
        private string $note,
        private string $status = '❌ Documentation chunk not found',
    ) {
    }

    /**
     * Create and serialize the not found response in one step.
     *
     * @param  string                                                                    $requestedChunk
     * @param  string                                                                    $sdk
     * @param  string                                                                    $note
     * @param  string                                                                    $status
     * @return array{status: string, requested_chunk: string, sdk: string, note: string}
     */
    public static function create(
        string $requestedChunk,
        string $sdk,
        string $note = 'Chunk ID may be invalid or belong to a different SDK',
        string $status = '❌ Documentation chunk not found',
    ): array {
        return (new self($requestedChunk, $sdk, $note, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, requested_chunk: string, sdk: string, note: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requested_chunk' => $this->requestedChunk,
            'sdk' => $this->sdk,
            'note' => $this->note,
        ];
    }
}
