<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getDocumentationSectionNotFoundException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $requestedSection  The section that was requested but not found
     * @param string        $sdk               The SDK identifier
     * @param array<string> $availableSections List of available sections for the SDK
     * @param string        $status            Status message
     */
    public function __construct(
        private string $requestedSection,
        private string $sdk,
        private array $availableSections,
        private string $status = '❌ Documentation section not found',
    ) {
    }

    /**
     * Create and serialize the not found response in one step.
     *
     * @param  string                                                                                           $requestedSection
     * @param  string                                                                                           $sdk
     * @param  array<string>                                                                                    $availableSections
     * @param  string                                                                                           $status
     * @return array{status: string, requested_section: string, sdk: string, available_sections: array<string>}
     */
    public static function create(
        string $requestedSection,
        string $sdk,
        array $availableSections,
        string $status = '❌ Documentation section not found',
    ): array {
        return (new self($requestedSection, $sdk, $availableSections, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, requested_section: string, sdk: string, available_sections: array<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requested_section' => $this->requestedSection,
            'sdk' => $this->sdk,
            'available_sections' => $this->availableSections,
        ];
    }
}
