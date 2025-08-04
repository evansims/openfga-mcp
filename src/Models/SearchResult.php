<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models;

use InvalidArgumentException;
use JsonSerializable;
use OpenFGA\MCP\Models\Traits\ValidatesInput;
use Override;

/**
 * Represents a documentation search result.
 */
final readonly class SearchResult implements JsonSerializable
{
    use ValidatesInput;

    /**
     * @param string       $chunkId
     * @param string       $sdk
     * @param float        $score
     * @param string       $preview
     * @param array<mixed> $metadata
     * @param string       $uri
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private string $chunkId,
        private string $sdk,
        private float $score,
        private string $preview,
        private array $metadata,
        private string $uri,
    ) {
        $this->validateNotEmpty($chunkId, 'Chunk ID');
        $this->validateNotEmpty($sdk, 'SDK identifier');
        $this->validatePattern($sdk, '/^[a-z]+$/', 'SDK identifier', 'must contain only lowercase letters');
        $this->validateRange($score, 0.0, 1.0, 'Search score');
        $this->validateNotEmpty($preview, 'Search preview');
        $this->validateUri($uri, 'Result URI');
    }

    /**
     * @return array{chunk_id: string, sdk: string, score: float, preview: string, metadata: array<mixed>, uri: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'sdk' => $this->sdk,
            'score' => $this->score,
            'preview' => $this->preview,
            'metadata' => $this->metadata,
            'uri' => $this->uri,
        ];
    }
}
