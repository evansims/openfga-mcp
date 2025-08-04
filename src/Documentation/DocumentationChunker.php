<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Documentation;

use function array_slice;
use function count;
use function is_int;
use function strlen;

final class DocumentationChunker
{
    private const int DEFAULT_CHUNK_SIZE = 3000;

    private const int MIN_CHUNK_SIZE = 500;

    /**
     * @param  string                                                                  $content
     * @return array<int, array{type: string, language: string|null, content: string}>
     */
    public function chunkByCodeBlocks(string $content): array
    {
        $chunks = [];
        $lines = explode("\n", $content);
        $currentChunk = [];
        $inCodeBlock = false;
        $codeLanguage = null;
        $textBuffer = [];

        foreach ($lines as $line) {
            $codeMatch = preg_match('/^```(\w*)$/', $line, $matches);

            if (false !== $codeMatch && 1 === $codeMatch) {
                if ($inCodeBlock) {
                    $currentChunk[] = $line;
                    $chunks[] = [
                        'type' => 'code',
                        'language' => $codeLanguage,
                        'content' => implode("\n", $currentChunk),
                    ];
                    $currentChunk = [];
                    $inCodeBlock = false;
                    $codeLanguage = null;
                } else {
                    if ([] !== $textBuffer) {
                        $chunks[] = [
                            'type' => 'text',
                            'language' => null,
                            'content' => implode("\n", $textBuffer),
                        ];
                        $textBuffer = [];
                    }
                    $inCodeBlock = true;
                    $codeLanguage = isset($matches[1]) && '' !== $matches[1] ? $matches[1] : 'plaintext';
                    $currentChunk = [$line];
                }
            } elseif ($inCodeBlock) {
                $currentChunk[] = $line;
            } else {
                $textBuffer[] = $line;

                if (50 <= count($textBuffer)) {
                    $chunks[] = [
                        'type' => 'text',
                        'language' => null,
                        'content' => implode("\n", $textBuffer),
                    ];
                    $textBuffer = [];
                }
            }
        }

        if ([] !== $textBuffer) {
            $chunks[] = [
                'type' => 'text',
                'language' => null,
                'content' => implode("\n", $textBuffer),
            ];
        }

        if ([] !== $currentChunk) {
            $chunks[] = [
                'type' => $inCodeBlock ? 'code' : 'text',
                'language' => $codeLanguage,
                'content' => implode("\n", $currentChunk),
            ];
        }

        return $chunks;
    }

    /**
     * @param  string                                                              $content
     * @return array<int, array{header: string|null, content: string, level: int}>
     */
    public function chunkByHeaders(string $content): array
    {
        if ('' === $content) {
            return [];
        }

        $chunks = [];
        $lines = explode("\n", $content);
        $currentChunk = [];
        $currentHeader = null;
        $currentLevel = 0;

        foreach ($lines as $line) {
            $headerMatch = preg_match('/^(#{1,6}) (.+)$/', $line, $matches);

            if (false !== $headerMatch && 1 === $headerMatch) {
                if ([] !== $currentChunk) {
                    $chunks[] = [
                        'header' => $currentHeader,
                        'content' => implode("\n", $currentChunk),
                        'level' => $currentLevel,
                    ];
                }

                /** @var array{0: non-falsy-string, 1: non-falsy-string, 2: non-empty-string} $matches */
                $currentHeader = trim($matches[2]);
                $currentLevel = strlen($matches[1]);
                $currentChunk = [$line];
            } else {
                $currentChunk[] = $line;
            }
        }

        // After the loop, currentChunk will always have content since we checked for empty string
        $chunks[] = [
            'header' => $currentHeader,
            'content' => implode("\n", $currentChunk),
            'level' => $currentLevel,
        ];

        return $chunks;
    }

    /**
     * @param  string             $content
     * @param  int                $maxLines
     * @return array<int, string>
     */
    public function chunkByLines(string $content, int $maxLines = 100): array
    {
        $lines = explode("\n", $content);
        $chunks = [];
        $currentChunk = [];

        foreach ($lines as $line) {
            $currentChunk[] = $line;

            if (count($currentChunk) >= $maxLines) {
                $chunks[] = implode("\n", $currentChunk);
                $currentChunk = array_slice($currentChunk, -10);
            }
        }

        if ([] !== $currentChunk) {
            $chunks[] = implode("\n", $currentChunk);
        }

        return $chunks;
    }

    /**
     * @param  string             $content
     * @param  int                $maxSize
     * @return array<int, string>
     */
    public function chunkBySize(string $content, int $maxSize = self::DEFAULT_CHUNK_SIZE): array
    {
        $chunks = [];
        $currentChunk = '';
        $sentences = $this->splitIntoSentences($content);

        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) > $maxSize && self::MIN_CHUNK_SIZE < strlen($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $overlapText = $this->getOverlapText($currentChunk);
                $currentChunk = $overlapText . ' ' . $sentence;
            } else {
                $currentChunk .= ' ' . $sentence;
            }
        }

        if ('' !== trim($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * @param  string                                                                $content
     * @return array<int, array{source: string|null, content: string, type: string}>
     */
    public function chunkBySourceBlocks(string $content): array
    {
        $chunks = [];
        $lines = explode("\n", $content);
        $currentChunk = [];
        $inSourceBlock = false;
        $sourceFile = null;

        foreach ($lines as $line) {
            $sourceMatch = preg_match('/^<!-- Source: (.+) -->$/', $line, $matches);

            if (false !== $sourceMatch && 1 === $sourceMatch) {
                if ([] !== $currentChunk) {
                    $chunks[] = [
                        'source' => $sourceFile,
                        'content' => implode("\n", $currentChunk),
                        'type' => 'source_block',
                    ];
                }
                $inSourceBlock = true;
                $sourceFile = isset($matches[1]) ? trim($matches[1]) : null;
                $currentChunk = [];

                continue;
            }

            $endMatch = preg_match('/^<!-- End of .+ -->$/', $line);

            if (false !== $endMatch && 1 === $endMatch) {
                if ([] !== $currentChunk) {
                    $chunks[] = [
                        'source' => $sourceFile,
                        'content' => implode("\n", $currentChunk),
                        'type' => 'source_block',
                    ];
                }
                $inSourceBlock = false;
                $sourceFile = null;
                $currentChunk = [];

                continue;
            }

            $currentChunk[] = $line;
        }

        if ([] !== $currentChunk) {
            $chunks[] = [
                'source' => $sourceFile,
                'content' => implode("\n", $currentChunk),
                'type' => $inSourceBlock ? 'source_block' : 'general',
            ];
        }

        return $chunks;
    }

    /**
     * @param  string                                                                                   $content
     * @return array<int, array{language: string, code: string, description: string, line_number: int}>
     */
    public function extractCodeExamples(string $content): array
    {
        $examples = [];
        $lines = explode("\n", $content);
        $inCodeBlock = false;
        $currentCode = [];
        $codeLanguage = null;
        $precedingText = '';
        $counter = count($lines);

        for ($i = 0; $i < $counter; ++$i) {
            $line = $lines[$i];

            $codeMatch = preg_match('/^```(\w*)$/', $line, $matches);

            if (false !== $codeMatch && 1 === $codeMatch) {
                if ($inCodeBlock) {
                    $examples[] = [
                        'language' => $codeLanguage ?? 'plaintext',
                        'code' => implode("\n", $currentCode),
                        'description' => $this->extractDescription($precedingText),
                        'line_number' => $i - count($currentCode),
                    ];
                    $currentCode = [];
                    $inCodeBlock = false;
                    $codeLanguage = null;
                    $precedingText = '';
                } else {
                    $inCodeBlock = true;
                    $codeLanguage = isset($matches[1]) && '' !== $matches[1] ? $matches[1] : 'plaintext';
                    $precedingText = $this->getPrecedingText($lines, $i, 5);
                }
            } elseif ($inCodeBlock) {
                $currentCode[] = $line;
            }
        }

        return $examples;
    }

    /**
     * @param  array<string, mixed>                                                      $options
     * @param  string                                                                    $content
     * @return array<int, array{content: string, metadata: array<string, mixed>}|string>
     */
    public function smartChunk(string $content, array $options = []): array
    {
        /** @var mixed $maxSizeValue */
        $maxSizeValue = $options['max_size'] ?? null;
        $maxSize = is_int($maxSizeValue) ? $maxSizeValue : self::DEFAULT_CHUNK_SIZE;
        $preserveHeaders = (bool) ($options['preserve_headers'] ?? true);
        $includeMetadata = (bool) ($options['include_metadata'] ?? true);

        $chunks = [];
        $lines = explode("\n", $content);
        $currentChunk = [];
        $currentMetadata = [];
        $inCodeBlock = false;
        $currentHeader = null;
        $currentSize = 0;

        foreach ($lines as $line) {
            $lineSize = strlen($line);

            $codeBlockMatch = preg_match('/^```/', $line);

            if (false !== $codeBlockMatch && 1 === $codeBlockMatch) {
                $inCodeBlock = ! $inCodeBlock;
            }

            $headerMatch = preg_match('/^(#{1,6}) (.+)$/', $line, $matches);

            if (! $inCodeBlock && false !== $headerMatch && 1 === $headerMatch) {
                if (self::MIN_CHUNK_SIZE < $currentSize) {
                    $this->finalizeChunk($chunks, $currentChunk, $currentMetadata, $includeMetadata);
                    $currentChunk = [];
                    $currentSize = 0;
                }
                $currentHeader = isset($matches[2]) ? trim($matches[2]) : null;
                $currentMetadata['header'] = $currentHeader;
                $currentMetadata['header_level'] = isset($matches[1]) ? strlen($matches[1]) : 0;
            }

            // Handle case where a single line is longer than max size
            if ($lineSize > $maxSize && ! $inCodeBlock) {
                // If we have existing content, finalize it first
                if ([] !== $currentChunk) {
                    $this->finalizeChunk($chunks, $currentChunk, $currentMetadata, $includeMetadata);
                }

                // Split the line using sentence-based chunking
                $sentenceChunks = $this->chunkBySize($line, $maxSize);

                // Add all but the last sentence chunk
                for ($i = 0; $i < count($sentenceChunks) - 1; ++$i) {
                    $this->finalizeChunk($chunks, [$sentenceChunks[$i]], $currentMetadata, $includeMetadata);
                }

                // Keep the last sentence chunk for the next iteration
                $currentChunk = [$sentenceChunks[count($sentenceChunks) - 1]];
                $currentSize = strlen($currentChunk[0]);
            } else {
                $currentChunk[] = $line;
                $currentSize += $lineSize;

                if ($currentSize >= $maxSize && ! $inCodeBlock) {
                    $this->finalizeChunk($chunks, $currentChunk, $currentMetadata, $includeMetadata);

                    if ($preserveHeaders && null !== $currentHeader) {
                        $currentChunk = [str_repeat('#', $currentMetadata['header_level'] ?? 2) . ' ' . $currentHeader . ' (continued)'];
                        $currentSize = strlen($currentChunk[0]);
                    } else {
                        $currentChunk = [];
                        $currentSize = 0;
                    }
                }
            }
        }

        if ([] !== $currentChunk) {
            $this->finalizeChunk($chunks, $currentChunk, $currentMetadata, $includeMetadata);
        }

        return $chunks;
    }

    private function extractDescription(string $text): string
    {
        $text = trim($text);

        $descMatch = preg_match('/(?:Example|Usage|Sample|Code):\s*(.+)$/i', $text, $matches);

        if (false !== $descMatch && 1 === $descMatch && isset($matches[1])) {
            return trim($matches[1]);
        }

        $sentences = $this->splitIntoSentences($text);

        return $sentences[count($sentences) - 1] ?? '';
    }

    /**
     * @param array<int, array{content: string, metadata: array<string, mixed>}|string> $chunks
     * @param array<int, string>                                                        $lines
     * @param array<string, mixed>                                                      $metadata
     * @param bool                                                                      $includeMetadata
     */
    private function finalizeChunk(array &$chunks, array $lines, array $metadata, bool $includeMetadata): void
    {
        $content = implode("\n", $lines);

        if ($includeMetadata) {
            $chunks[] = [
                'content' => $content,
                'metadata' => array_merge($metadata, [
                    'size' => strlen($content),
                    'line_count' => count($lines),
                ]),
            ];
        } else {
            $chunks[] = $content;
        }
    }

    private function getOverlapText(string $chunk): string
    {
        $words = explode(' ', $chunk);
        $overlapWords = array_slice($words, -20);

        return implode(' ', $overlapWords);
    }

    /**
     * @param array<int, string> $lines
     * @param int                $currentIndex
     * @param int                $lookback
     */
    private function getPrecedingText(array $lines, int $currentIndex, int $lookback = 5): string
    {
        $start = max(0, $currentIndex - $lookback);
        $precedingLines = array_slice($lines, $start, $currentIndex - $start);

        return implode(' ', array_filter($precedingLines, static fn ($line): bool => '' !== trim($line)));
    }

    /**
     * @param  string             $text
     * @return array<int, string>
     */
    private function splitIntoSentences(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return false !== $sentences ? $sentences : [$text];
    }
}
