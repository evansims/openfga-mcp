<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use OpenFGA\MCP\Models\{GuideDocumentation, SdkDocumentation};
use Override;

use function array_map;
use function count;

final readonly class listDocumentationResponse extends AbstractResponse implements JsonSerializable
{
    /**
     * @param array<SdkDocumentation>   $sdkDocumentation    List of SDK documentation
     * @param array<GuideDocumentation> $guidesDocumentation List of guides documentation
     * @param string                    $status              Status message indicating the availability of OpenFGA documentation
     * @param int|null                  $totalSdks           Total number of SDKs available
     * @param array<string, string>     $endpoints           List of endpoints for accessing documentation
     */
    public function __construct(
        private array $sdkDocumentation,
        private array $guidesDocumentation,
        private string $status = '✅ OpenFGA Documentation Available',
        private ?int $totalSdks = null,
        private array $endpoints = [
            'openfga://docs' => 'List all OpenFGA documentation',
            'openfga://docs/{sdk}' => 'Get OpenFGA SDK overview',
            'openfga://docs/{sdk}/class/{className}' => 'Get OpenFGA SDK class documentation',
            'openfga://docs/{sdk}/method/{className}/{methodName}' => 'Get OpenFGA SDK method documentation',
            'openfga://docs/{sdk}/section/{sectionName}' => 'Get OpenFGA documentation section',
            'openfga://docs/{sdk}/chunk/{chunkId}' => 'Get OpenFGA specific content chunk',
        ],
    ) {
    }

    /**
     * Create and serialize the response in one step.
     *
     * @param  array<SdkDocumentation>                                                                                                                                                                                                                                                                              $sdkDocumentation
     * @param  array<GuideDocumentation>                                                                                                                                                                                                                                                                            $guidesDocumentation
     * @param  string                                                                                                                                                                                                                                                                                               $status
     * @param  int|null                                                                                                                                                                                                                                                                                             $totalSdks
     * @param  array<string, string>                                                                                                                                                                                                                                                                                $endpoints
     * @return array{status: string, sdk_documentation: array<array{sdk: string, name: string, sections: int, classes: int, chunks: int, uri: string}>, guides_documentation: array<array{type: string, name: string, sections: int, chunks: int, uri: string}>, total_sdks: int, endpoints: array<string, string>}
     */
    public static function create(
        array $sdkDocumentation,
        array $guidesDocumentation,
        string $status = '✅ OpenFGA Documentation Available',
        ?int $totalSdks = null,
        array $endpoints = [
            'openfga://docs' => 'List all OpenFGA documentation',
            'openfga://docs/{sdk}' => 'Get OpenFGA SDK overview',
            'openfga://docs/{sdk}/class/{className}' => 'Get OpenFGA SDK class documentation',
            'openfga://docs/{sdk}/method/{className}/{methodName}' => 'Get OpenFGA SDK method documentation',
            'openfga://docs/{sdk}/section/{sectionName}' => 'Get OpenFGA documentation section',
            'openfga://docs/{sdk}/chunk/{chunkId}' => 'Get OpenFGA specific content chunk',
        ],
    ): array {
        return (new self($sdkDocumentation, $guidesDocumentation, $status, $totalSdks, $endpoints))->jsonSerialize();
    }

    /**
     * Serialize the response to JSON format.
     *
     * @return array{status: string, sdk_documentation: array<array{sdk: string, name: string, sections: int, classes: int, chunks: int, uri: string}>, guides_documentation: array<array{type: string, name: string, sections: int, chunks: int, uri: string}>, total_sdks: int, endpoints: array<string, string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'sdk_documentation' => array_map(static fn (SdkDocumentation $doc): array => $doc->jsonSerialize(), $this->sdkDocumentation),
            'guides_documentation' => array_map(static fn (GuideDocumentation $doc): array => $doc->jsonSerialize(), $this->guidesDocumentation),
            'total_sdks' => $this->totalSdks ?? count($this->sdkDocumentation) + count($this->guidesDocumentation),
            'endpoints' => $this->endpoints,
        ];
    }
}
