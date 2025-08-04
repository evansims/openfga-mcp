<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Responses;

use JsonSerializable;
use Override;

final readonly class listDocumentationException extends AbstractResponse implements JsonSerializable
{
    /**
     * @param string $error  Error message indicating the failure to load documentation index
     * @param string $status Status message indicating the failure
     * @param string $note   Additional note for users to check documentation files
     */
    public function __construct(
        private string $error,
        private string $status = '❌ Failed to load documentation index',
        private string $note = 'Ensure documentation files exist in the docs/ directory',
    ) {
    }

    /**
     * Create and serialize the exception response in one step.
     *
     * @param  string                                             $error
     * @param  string                                             $status
     * @param  string                                             $note
     * @return array{status: string, error: string, note: string}
     */
    public static function create(
        string $error,
        string $status = '❌ Failed to load documentation index',
        string $note = 'Ensure documentation files exist in the docs/ directory',
    ): array {
        return (new self($error, $status, $note))->jsonSerialize();
    }

    /**
     * Serialize the response to JSON format.
     *
     * @return array{status: string, error: string, note: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'error' => $this->error,
            'note' => $this->note,
        ];
    }
}
