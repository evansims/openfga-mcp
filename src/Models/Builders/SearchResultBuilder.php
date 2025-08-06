<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models\Builders;

use InvalidArgumentException;
use OpenFGA\MCP\Models\SearchResult;

use function assert;
use function is_array;
use function is_scalar;
use function sprintf;

/**
 * Builder for creating SearchResult instances with a fluent interface.
 */
final class SearchResultBuilder
{
    private bool $autoGenerateUri = true;

    private ?string $chunkId = null;

    /**
     * @var array<mixed>
     */
    private array $metadata = [];

    private ?string $preview = null;

    private ?float $score = null;

    private ?string $sdk = null;

    private ?string $uri = null;

    /**
     * Creates a new builder instance.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Adds a single metadata entry.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return $this
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Builds and returns the SearchResult instance.
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public function build(): SearchResult
    {
        $this->validate();

        // Auto-generate URI if not explicitly set
        if ($this->autoGenerateUri && null !== $this->sdk && null !== $this->chunkId) {
            $this->uri = sprintf('openfga://docs/%s/chunk/%s', $this->sdk, $this->chunkId);
        }

        // All required fields are validated to be non-null in validate()
        assert(null !== $this->chunkId);
        assert(null !== $this->sdk);
        assert(null !== $this->score);
        assert(null !== $this->preview);
        assert(null !== $this->uri);

        return new SearchResult(
            chunkId: $this->chunkId,
            sdk: $this->sdk,
            score: $this->score,
            preview: $this->preview,
            metadata: $this->metadata,
            uri: $this->uri,
        );
    }

    /**
     * Creates a SearchResult from an existing array.
     *
     * @param  array<string, mixed> $data
     * @return $this
     */
    public function fromArray(array $data): self
    {
        if (isset($data['chunk_id']) && is_scalar($data['chunk_id'])) {
            $this->withChunkId((string) $data['chunk_id']);
        }

        if (isset($data['sdk']) && is_scalar($data['sdk'])) {
            $this->withSdk((string) $data['sdk']);
        }

        if (isset($data['score']) && is_numeric($data['score'])) {
            $this->withScore((float) $data['score']);
        }

        if (isset($data['preview']) && is_scalar($data['preview'])) {
            $this->withPreview((string) $data['preview']);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $this->withMetadata($data['metadata']);
        }

        if (isset($data['uri']) && is_scalar($data['uri'])) {
            $uriStr = (string) $data['uri'];

            if ('' !== $uriStr) {
                $this->withUri($uriStr);
            }
        }

        return $this;
    }

    /**
     * Sets the chunk ID.
     *
     * @param  string $chunkId
     * @return $this
     */
    public function withChunkId(string $chunkId): self
    {
        $this->chunkId = $chunkId;

        return $this;
    }

    /**
     * Sets the metadata.
     *
     * @param  array<mixed> $metadata
     * @return $this
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Sets the search preview.
     *
     * @param  string $preview
     * @return $this
     */
    public function withPreview(string $preview): self
    {
        $this->preview = $preview;

        return $this;
    }

    /**
     * Sets the search score.
     *
     * @param  float $score
     * @return $this
     */
    public function withScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    /**
     * Sets the SDK identifier.
     *
     * @param  string $sdk
     * @return $this
     */
    public function withSdk(string $sdk): self
    {
        $this->sdk = $sdk;

        return $this;
    }

    /**
     * Sets the URI explicitly.
     *
     * @param  string $uri
     * @return $this
     */
    public function withUri(string $uri): self
    {
        $this->uri = $uri;
        $this->autoGenerateUri = false;

        return $this;
    }

    /**
     * Validates that all required fields are set.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (null === $this->chunkId) {
            throw new InvalidArgumentException('Chunk ID is required');
        }

        if (null === $this->sdk) {
            throw new InvalidArgumentException('SDK is required');
        }

        if (null === $this->score) {
            throw new InvalidArgumentException('Score is required');
        }

        if (null === $this->preview) {
            throw new InvalidArgumentException('Preview is required');
        }

        if (null === $this->uri && ! $this->autoGenerateUri) {
            throw new InvalidArgumentException('URI is required when auto-generation is disabled');
        }
    }
}
