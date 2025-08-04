<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\MCP\Documentation\{DocumentationIndex, DocumentationIndexSingleton};
use Override;
use PhpMcp\Server\Contracts\{CompletionProviderInterface, SessionInterface};
use Throwable;

final readonly class ChunkIdCompletionProvider implements CompletionProviderInterface
{
    private DocumentationIndex $documentationIndex;

    public function __construct()
    {
        $this->documentationIndex = DocumentationIndexSingleton::getInstance();
    }

    /**
     * Get completion suggestions for chunk IDs within a specific SDK.
     *
     * @param  string           $currentValue
     * @param  SessionInterface $session
     * @return array<string>
     */
    #[Override]
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        try {
            $this->documentationIndex->initialize();

            // Currently, we cannot extract SDK from session context
            // Chunk IDs are too numerous to list without SDK context, return empty
            return [];
        } catch (Throwable) {
            return [];
        }
    }
}
