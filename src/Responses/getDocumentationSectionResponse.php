<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

use function sprintf;

final readonly class getDocumentationSectionResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $sectionName The section name
     * @param string $sdk         The SDK identifier
     * @param string $content     The documentation content
     * @param int    $chunkCount  Number of chunks in this section
     * @param int    $totalSize   Total size of the content
     * @param string $status      Status message
     */
    public function __construct(
        private string $sectionName,
        private string $sdk,
        private string $content,
        private int $chunkCount,
        private int $totalSize,
        private string $status = '✅ Documentation Section',
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                                   $sectionName
     * @param  string                                                                                                                   $sdk
     * @param  string                                                                                                                   $content
     * @param  int                                                                                                                      $chunkCount
     * @param  int                                                                                                                      $totalSize
     * @param  string                                                                                                                   $status
     * @return array{status: string, content: string, metadata: array{section: string, sdk: string, chunk_count: int, total_size: int}}
     */
    public static function create(
        string $sectionName,
        string $sdk,
        string $content,
        int $chunkCount,
        int $totalSize,
        string $status = '✅ Documentation Section',
    ): array {
        return (new self(
            $sectionName,
            $sdk,
            $content,
            $chunkCount,
            $totalSize,
            $status,
        ))->jsonSerialize();
    }

    /**
     * @return array{status: string, content: string, metadata: array{section: string, sdk: string, chunk_count: int, total_size: int}}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => sprintf('%s: %s', $this->status, $this->sectionName),
            'content' => $this->content,
            'metadata' => [
                'section' => $this->sectionName,
                'sdk' => $this->sdk,
                'chunk_count' => $this->chunkCount,
                'total_size' => $this->totalSize,
            ],
        ];
    }
}
