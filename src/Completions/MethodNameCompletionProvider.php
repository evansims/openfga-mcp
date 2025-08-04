<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\MCP\Documentation\{DocumentationIndex, DocumentationIndexSingleton};
use Override;
use PhpMcp\Server\Contracts\{CompletionProviderInterface, SessionInterface};
use Throwable;

final readonly class MethodNameCompletionProvider implements CompletionProviderInterface
{
    private DocumentationIndex $documentationIndex;

    public function __construct()
    {
        $this->documentationIndex = DocumentationIndexSingleton::getInstance();
    }

    /**
     * Get completion suggestions for method names within a specific SDK and class.
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

            // Currently, we cannot extract SDK and className from session context
            // Method completions require both SDK and class context, which are not available
            return [];
        } catch (Throwable) {
            return [];
        }
    }
}
