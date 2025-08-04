<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

use function sprintf;

final readonly class getSdkDocumentationResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string                $sdk         The SDK identifier
     * @param string                $name        The human-readable name
     * @param string                $type        Either 'sdk' or 'general'
     * @param array<string>         $sections    List of available sections
     * @param int                   $totalChunks Total number of documentation chunks
     * @param array<string, string> $endpoints   Available endpoints for this SDK
     * @param string|null           $source      Source of the documentation
     * @param string|null           $generated   When the documentation was generated
     * @param int|null              $classes     Number of classes (only for SDK docs)
     * @param string                $status      Status message
     */
    public function __construct(
        private string $sdk,
        private string $name,
        private string $type,
        private array $sections,
        private int $totalChunks,
        private array $endpoints,
        private ?string $source = null,
        private ?string $generated = null,
        private ?int $classes = null,
        private string $status = '✅ Documentation',
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  string                                                                                                                                                                                                   $sdk
     * @param  string                                                                                                                                                                                                   $name
     * @param  string                                                                                                                                                                                                   $type
     * @param  array<string>                                                                                                                                                                                            $sections
     * @param  int                                                                                                                                                                                                      $totalChunks
     * @param  array<string, string>                                                                                                                                                                                    $endpoints
     * @param  string|null                                                                                                                                                                                              $source
     * @param  string|null                                                                                                                                                                                              $generated
     * @param  int|null                                                                                                                                                                                                 $classes
     * @param  string                                                                                                                                                                                                   $status
     * @return array{status: string, type: string, sdk: string, name: string, source: string|null, generated: string|null, sections: array<string>, total_chunks: int, classes?: int, endpoints: array<string, string>}
     */
    public static function create(
        string $sdk,
        string $name,
        string $type,
        array $sections,
        int $totalChunks,
        array $endpoints,
        ?string $source = null,
        ?string $generated = null,
        ?int $classes = null,
        string $status = '✅ Documentation',
    ): array {
        return (new self(
            $sdk,
            $name,
            $type,
            $sections,
            $totalChunks,
            $endpoints,
            $source,
            $generated,
            $classes,
            $status,
        ))->jsonSerialize();
    }

    /**
     * @return array{status: string, type: string, sdk: string, name: string, source: string|null, generated: string|null, sections: array<string>, total_chunks: int, classes?: int, endpoints: array<string, string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $result = [
            'status' => sprintf('%s: %s', $this->status, $this->name),
            'type' => $this->type,
            'sdk' => $this->sdk,
            'name' => $this->name,
            'source' => $this->source,
            'generated' => $this->generated,
            'sections' => $this->sections,
            'total_chunks' => $this->totalChunks,
        ];

        // Only add classes for SDK documentation
        if ('sdk' === $this->type && null !== $this->classes) {
            $result['classes'] = $this->classes;
        }

        $result['endpoints'] = $this->endpoints;

        return $result;
    }
}
