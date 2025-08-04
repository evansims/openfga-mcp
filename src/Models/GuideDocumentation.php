<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Models;

use InvalidArgumentException;
use JsonSerializable;
use OpenFGA\MCP\Models\Traits\ValidatesInput;
use Override;

/**
 * Represents guide documentation metadata.
 */
final readonly class GuideDocumentation implements JsonSerializable
{
    use ValidatesInput;

    /**
     * @param string $type
     * @param string $name
     * @param int    $sections
     * @param int    $chunks
     * @param string $uri
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private string $type,
        private string $name,
        private int $sections,
        private int $chunks,
        private string $uri,
    ) {
        $this->validateNotEmpty($type, 'Guide type');
        $this->validatePattern($type, '/^[a-z]+$/', 'Guide type', 'must contain only lowercase letters');
        $this->validateNotEmpty($name, 'Guide name');
        $this->validateNonNegative($sections, 'Sections count');
        $this->validateNonNegative($chunks, 'Chunks count');
        $this->validateUri($uri, 'Documentation URI');
    }

    /**
     * @return array{type: string, name: string, sections: int, chunks: int, uri: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'sections' => $this->sections,
            'chunks' => $this->chunks,
            'uri' => $this->uri,
        ];
    }
}
