<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getSdkDocumentationNotFoundException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $requestedSdk     The SDK that was requested but not found
     * @param array<string> $availableSdks    List of available SDK identifiers
     * @param array<string> $availableGeneral List of available general documentation types
     * @param string        $status           Status message
     */
    public function __construct(
        private string $requestedSdk,
        private array $availableSdks,
        private array $availableGeneral = ['general', 'authoring'],
        private string $status = '❌ Documentation not found',
    ) {
    }

    /**
     * Create and serialize the not found response in one step.
     *
     * @param  string                                                                                                        $requestedSdk
     * @param  array<string>                                                                                                 $availableSdks
     * @param  array<string>                                                                                                 $availableGeneral
     * @param  string                                                                                                        $status
     * @return array{status: string, requested_sdk: string, available_sdks: array<string>, available_general: array<string>}
     */
    public static function create(
        string $requestedSdk,
        array $availableSdks,
        array $availableGeneral = ['general', 'authoring'],
        string $status = '❌ Documentation not found',
    ): array {
        return (new self($requestedSdk, $availableSdks, $availableGeneral, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, requested_sdk: string, available_sdks: array<string>, available_general: array<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requested_sdk' => $this->requestedSdk,
            'available_sdks' => $this->availableSdks,
            'available_general' => $this->availableGeneral,
        ];
    }
}
