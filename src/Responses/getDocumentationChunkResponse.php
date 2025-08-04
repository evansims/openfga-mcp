<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

use function sprintf;

final readonly class getDocumentationChunkResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string                $chunkId    The chunk ID
     * @param string                $sdk        The SDK identifier
     * @param string                $content    The documentation content
     * @param array<string, mixed>  $metadata   The chunk metadata
     * @param array<string, string> $navigation Navigation links (previous/next)
     * @param string                $status     Status message
     */
    public function __construct(
        private string $chunkId,
        private string $sdk,
        private string $content,
        private array $metadata,
        private array $navigation,
        private string $status = '✅ Documentation Chunk',
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                    $chunkId
     * @param  string                                                                                                    $sdk
     * @param  string                                                                                                    $content
     * @param  array<string, mixed>                                                                                      $metadata
     * @param  array<string, string>                                                                                     $navigation
     * @param  string                                                                                                    $status
     * @return array{status: string, content: string, metadata: array<string, mixed>, navigation: array<string, string>}
     */
    public static function create(
        string $chunkId,
        string $sdk,
        string $content,
        array $metadata,
        array $navigation,
        string $status = '✅ Documentation Chunk',
    ): array {
        return (new self(
            $chunkId,
            $sdk,
            $content,
            $metadata,
            $navigation,
            $status,
        ))->jsonSerialize();
    }

    /**
     * @return array{status: string, content: string, metadata: array<string, mixed>, navigation: array<string, string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $metadata = $this->metadata;
        $metadata['chunk_id'] = $this->chunkId;
        $metadata['sdk'] = $this->sdk;

        return [
            'status' => sprintf('%s: %s', $this->status, $this->chunkId),
            'content' => $this->content,
            'metadata' => $metadata,
            'navigation' => $this->navigation,
        ];
    }
}
