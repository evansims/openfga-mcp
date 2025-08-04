<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getSdkDocumentationException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $sdk    The SDK identifier that caused the error
     * @param string $error  The error message
     * @param string $status Status message
     */
    public function __construct(
        private string $sdk,
        private string $error,
        private string $status = '❌ Error loading documentation',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                            $sdk
     * @param  string                                            $error
     * @param  string                                            $status
     * @return array{status: string, sdk: string, error: string}
     */
    public static function create(
        string $sdk,
        string $error,
        string $status = '❌ Error loading documentation',
    ): array {
        return (new self($sdk, $error, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, sdk: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'sdk' => $this->sdk,
            'error' => $this->error,
        ];
    }
}
