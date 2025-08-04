<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models;

use InvalidArgumentException;
use JsonSerializable;
use OpenFGA\MCP\Models\Traits\ValidatesInput;
use Override;

/**
 * Represents SDK documentation metadata.
 */
final readonly class SdkDocumentation implements JsonSerializable
{
    use ValidatesInput;

    /**
     * @param string $sdk
     * @param string $name
     * @param int    $sections
     * @param int    $classes
     * @param int    $chunks
     * @param string $uri
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private string $sdk,
        private string $name,
        private int $sections,
        private int $classes,
        private int $chunks,
        private string $uri,
    ) {
        $this->validateNotEmpty($sdk, 'SDK identifier');
        $this->validatePattern($sdk, '/^[a-z]+$/', 'SDK identifier', 'must contain only lowercase letters');
        $this->validateNotEmpty($name, 'SDK name');
        $this->validateNonNegative($sections, 'Sections count');
        $this->validateNonNegative($classes, 'Classes count');
        $this->validateNonNegative($chunks, 'Chunks count');
        $this->validateUri($uri, 'Documentation URI');
    }

    /**
     * @return array{sdk: string, name: string, sections: int, classes: int, chunks: int, uri: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'sdk' => $this->sdk,
            'name' => $this->name,
            'sections' => $this->sections,
            'classes' => $this->classes,
            'chunks' => $this->chunks,
            'uri' => $this->uri,
        ];
    }
}
