<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getMethodDocumentationNotFoundException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string        $requestedMethod  The method that was requested but not found
     * @param string        $className        The class name
     * @param string        $sdk              The SDK identifier
     * @param array<string> $availableMethods List of available methods for the class
     * @param string        $status           Status message
     */
    public function __construct(
        private string $requestedMethod,
        private string $className,
        private string $sdk,
        private array $availableMethods,
        private string $status = '❌ Method documentation not found',
    ) {
    }

    /**
     * Create and serialize the not found response in one step.
     *
     * @param  string                                                                                                        $requestedMethod
     * @param  string                                                                                                        $className
     * @param  string                                                                                                        $sdk
     * @param  array<string>                                                                                                 $availableMethods
     * @param  string                                                                                                        $status
     * @return array{status: string, requested_method: string, class: string, sdk: string, available_methods: array<string>}
     */
    public static function create(
        string $requestedMethod,
        string $className,
        string $sdk,
        array $availableMethods,
        string $status = '❌ Method documentation not found',
    ): array {
        return (new self($requestedMethod, $className, $sdk, $availableMethods, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, requested_method: string, class: string, sdk: string, available_methods: array<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'requested_method' => $this->requestedMethod,
            'class' => $this->className,
            'sdk' => $this->sdk,
            'available_methods' => $this->availableMethods,
        ];
    }
}
