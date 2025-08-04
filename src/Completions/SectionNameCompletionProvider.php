<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Completions;

use OpenFGA\MCP\Documentation\{DocumentationIndex, DocumentationIndexSingleton};
use Override;
use PhpMcp\Server\Contracts\{CompletionProviderInterface, SessionInterface};
use Throwable;

final readonly class SectionNameCompletionProvider implements CompletionProviderInterface
{
    private DocumentationIndex $documentationIndex;

    public function __construct()
    {
        $this->documentationIndex = DocumentationIndexSingleton::getInstance();
    }

    /**
     * Get completion suggestions for section names within a specific SDK.
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
            // Return all sections from all SDKs
            return $this->getAllSections($currentValue);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Filter completions based on the current input value.
     *
     * @param  array<string> $completions
     * @param  string        $currentValue
     * @return array<string>
     */
    private function filterCompletions(array $completions, string $currentValue): array
    {
        if ('' === $currentValue) {
            return $completions;
        }

        return array_values(array_filter(
            $completions,
            static fn (string $completion): bool => str_starts_with(strtolower($completion), strtolower($currentValue)),
        ));
    }

    /**
     * Get all sections from all SDKs.
     *
     * @param  string        $currentValue
     * @return array<string>
     */
    private function getAllSections(string $currentValue): array
    {
        $allSections = [];

        try {
            $sdks = $this->documentationIndex->getSdkList();
        } catch (Throwable) {
            return [];
        }

        // Include general documentation types
        $sdks[] = 'general';
        $sdks[] = 'authoring';

        foreach ($sdks as $sdk) {
            $overview = $this->documentationIndex->getSdkOverview($sdk);

            if (null !== $overview) {
                foreach ($overview['sections'] as $section) {
                    $allSections[] = $section;
                }
            }
        }

        return $this->filterCompletions(array_unique($allSections), $currentValue);
    }
}
