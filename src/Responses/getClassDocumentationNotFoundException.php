<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getClassDocumentationNotFoundException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $requestedClass   The class that was requested but not found
     * @param string        $sdk              The SDK identifier
     * @param array<string> $availableClasses List of available classes for the SDK
     * @param string        $status           Status message
     */
    public function __construct(
        private string $requestedClass,
        private string $sdk,
        private array $availableClasses,
        private string $status = '❌ Class documentation not found',
    ) {
    }

    /**
     * Create and serialize the not found response in one step.
     *
     * @param  string                                                                                        $requestedClass
     * @param  string                                                                                        $sdk
     * @param  array<string>                                                                                 $availableClasses
     * @param  string                                                                                        $status
     * @return array{status: string, requested_class: string, sdk: string, available_classes: array<string>}
     */
    public static function create(
        string $requestedClass,
        string $sdk,
        array $availableClasses,
        string $status = '❌ Class documentation not found',
    ): array {
        return (new self($requestedClass, $sdk, $availableClasses, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, requested_class: string, sdk: string, available_classes: array<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requested_class' => $this->requestedClass,
            'sdk' => $this->sdk,
            'available_classes' => $this->availableClasses,
        ];
    }
}
