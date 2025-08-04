<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class getDocumentationSectionException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $sectionName The section name that caused the error
     * @param string $sdk         The SDK identifier
     * @param string $error       The error message
     * @param string $status      Status message
     */
    public function __construct(
        private string $sectionName,
        private string $sdk,
        private string $error,
        private string $status = '❌ Error loading documentation section',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                                             $sectionName
     * @param  string                                                             $sdk
     * @param  string                                                             $error
     * @param  string                                                             $status
     * @return array{status: string, section: string, sdk: string, error: string}
     */
    public static function create(
        string $sectionName,
        string $sdk,
        string $error,
        string $status = '❌ Error loading documentation section',
    ): array {
        return (new self($sectionName, $sdk, $error, $status))->jsonSerialize();
    }

    /**
     * @return array{status: string, section: string, sdk: string, error: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'section' => $this->sectionName,
            'sdk' => $this->sdk,
            'error' => $this->error,
        ];
    }
}
