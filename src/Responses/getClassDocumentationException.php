<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getClassDocumentationException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $className The class name that caused the error
     * @param string $sdk       The SDK identifier
     * @param string $error     The error message
     * @param string $status    Status message
     */
    public function __construct(
        private string $className,
        private string $sdk,
        private string $error,
        private string $status = '❌ Error loading class documentation',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                                           $className
     * @param  string                                                           $sdk
     * @param  string                                                           $error
     * @param  string                                                           $status
     * @return array{status: string, class: string, sdk: string, error: string}
     */
    public static function create(
        string $className,
        string $sdk,
        string $error,
        string $status = '❌ Error loading class documentation',
    ): array {
        return (new self($className, $sdk, $error, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, class: string, sdk: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'class' => $this->className,
            'sdk' => $this->sdk,
            'error' => $this->error,
        ];
    }
}
