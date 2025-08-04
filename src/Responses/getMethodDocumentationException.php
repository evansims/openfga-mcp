<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getMethodDocumentationException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $methodName The method name that caused the error
     * @param string $className  The class name
     * @param string $sdk        The SDK identifier
     * @param string $error      The error message
     * @param string $status     Status message
     */
    public function __construct(
        private string $methodName,
        private string $className,
        private string $sdk,
        private string $error,
        private string $status = '❌ Error loading method documentation',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                                                           $methodName
     * @param  string                                                                           $className
     * @param  string                                                                           $sdk
     * @param  string                                                                           $error
     * @param  string                                                                           $status
     * @return array{status: string, method: string, class: string, sdk: string, error: string}
     */
    public static function create(
        string $methodName,
        string $className,
        string $sdk,
        string $error,
        string $status = '❌ Error loading method documentation',
    ): array {
        return (new self($methodName, $className, $sdk, $error, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, method: string, class: string, sdk: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'method' => $this->methodName,
            'class' => $this->className,
            'sdk' => $this->sdk,
            'error' => $this->error,
        ];
    }
}
