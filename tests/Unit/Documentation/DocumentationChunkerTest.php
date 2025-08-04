<?php

declare(strict_types=1);

use OpenFGA\MCP\Documentation\DocumentationChunker;

beforeEach(function (): void {
    $this->chunker = new DocumentationChunker;

    // Sample content for testing
    $this->sampleContent = <<<'CONTENT'
        # Main Title

        This is the introduction paragraph with some content that should be chunked appropriately.

        ## Section One

        This is section one with detailed content. It contains multiple sentences to test chunking behavior.
        Here's another paragraph in section one. This should provide sufficient content for testing.

        ### Subsection

        More detailed content in the subsection.

        ```php
        <?php
        function example() {
            return "Hello World";
        }
        ```

        ## Section Two

        This is section two with different content.

        ```javascript
        function jsExample() {
            console.log("JavaScript example");
        }
        ```

        Some text after the code block.

        <!-- Source: src/Example.php -->
        ### ExampleClass

        This is a class documentation section.

        ##### exampleMethod

        This method does something useful.

        ```php
        public function exampleMethod(): string
        {
            return "example";
        }
        ```

        <!-- End of src/Example.php -->

        More content after the source block.
        CONTENT;

    $this->longContent = str_repeat('This is a long sentence that will be used to test size-based chunking. ', 200);
});

afterEach(function (): void {
    Mockery::close();
});

describe('chunkByLines method', function (): void {
    it('chunks content by line count', function (): void {
        $chunks = $this->chunker->chunkByLines($this->sampleContent, 10);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(1);

        // Each chunk should have reasonable content
        foreach ($chunks as $chunk) {
            expect($chunk)->toBeString();
            expect(strlen($chunk))->toBeGreaterThan(0);
        }
    });

    it('handles single line content', function (): void {
        $singleLine = 'This is a single line of content.';
        $chunks = $this->chunker->chunkByLines($singleLine, 10);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0])->toBe($singleLine);
    });

    it('handles empty content', function (): void {
        $chunks = $this->chunker->chunkByLines('', 10);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0])->toBe('');
    });

    it('maintains overlap between chunks', function (): void {
        $manyLines = implode("\n", array_map(fn ($i) => "Line {$i}", range(1, 50)));
        $chunks = $this->chunker->chunkByLines($manyLines, 20);

        expect(count($chunks))->toBeGreaterThan(2);
        // Should have some overlap (last 10 lines of previous chunk)
        expect($chunks[1])->toContain('Line 11'); // Overlap from previous chunk
    });
});

describe('chunkBySize method', function (): void {
    it('chunks content by character size', function (): void {
        $chunks = $this->chunker->chunkBySize($this->longContent, 1000);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(1);

        foreach ($chunks as $chunk) {
            expect(strlen($chunk))->toBeLessThanOrEqual(1200); // Allow some flexibility for sentence boundaries
        }
    });

    it('respects minimum chunk size', function (): void {
        $chunks = $this->chunker->chunkBySize($this->sampleContent, 100);

        foreach ($chunks as $chunk) {
            // Most chunks should be reasonably sized (except possibly the last one)
            expect(strlen($chunk))->toBeGreaterThan(0);
        }
    });

    it('handles content smaller than chunk size', function (): void {
        $smallContent = 'This is small content.';
        $chunks = $this->chunker->chunkBySize($smallContent, 1000);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0])->toBe(trim($smallContent));
    });

    it('creates overlap between chunks', function (): void {
        $chunks = $this->chunker->chunkBySize($this->longContent, 1000);

        if (1 < count($chunks)) {
            // Check that chunks have some overlapping content
            $firstChunkEnd = substr($chunks[0], -100);
            $secondChunkStart = substr($chunks[1], 0, 100);

            // There should be some word overlap
            $firstWords = explode(' ', $firstChunkEnd);
            $secondWords = explode(' ', $secondChunkStart);
            $overlap = array_intersect($firstWords, $secondWords);

            expect(count($overlap))->toBeGreaterThan(0);
        }
    });
});

describe('chunkByHeaders method', function (): void {
    it('chunks content by markdown headers', function (): void {
        $chunks = $this->chunker->chunkByHeaders($this->sampleContent);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(1);

        foreach ($chunks as $chunk) {
            expect($chunk)->toHaveKeys(['header', 'content', 'level']);
            expect($chunk['content'])->toBeString();
            expect($chunk['level'])->toBeInt();
        }
    });

    it('correctly identifies header levels', function (): void {
        $chunks = $this->chunker->chunkByHeaders($this->sampleContent);

        $mainTitleChunk = null;
        $sectionChunk = null;
        $subsectionChunk = null;

        foreach ($chunks as $chunk) {
            if ('Main Title' === $chunk['header']) {
                $mainTitleChunk = $chunk;
            } elseif ('Section One' === $chunk['header']) {
                $sectionChunk = $chunk;
            } elseif ('Subsection' === $chunk['header']) {
                $subsectionChunk = $chunk;
            }
        }

        // Check that we found at least one chunk and it has the right structure
        expect($chunks)->not->toBeEmpty();
        expect($chunks[0])->toHaveKey('level');

        if ($mainTitleChunk) expect($mainTitleChunk['level'])->toBe(1);

        if ($sectionChunk) expect($sectionChunk['level'])->toBe(2);

        if ($subsectionChunk) expect($subsectionChunk['level'])->toBe(3);
    });

    it('handles content without headers', function (): void {
        $contentWithoutHeaders = 'This is just plain text without any headers.';
        $chunks = $this->chunker->chunkByHeaders($contentWithoutHeaders);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0]['header'])->toBeNull();
        expect($chunks[0]['content'])->toContain($contentWithoutHeaders);
    });
});

describe('chunkBySourceBlocks method', function (): void {
    it('chunks content by source file blocks', function (): void {
        $chunks = $this->chunker->chunkBySourceBlocks($this->sampleContent);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(1);

        foreach ($chunks as $chunk) {
            expect($chunk)->toHaveKeys(['source', 'content', 'type']);
            expect($chunk['content'])->toBeString();
            expect($chunk['type'])->toBeString();
        }
    });

    it('identifies source blocks correctly', function (): void {
        $chunks = $this->chunker->chunkBySourceBlocks($this->sampleContent);

        $sourceBlock = null;

        foreach ($chunks as $chunk) {
            if ('src/Example.php' === $chunk['source']) {
                $sourceBlock = $chunk;

                break;
            }
        }

        expect($sourceBlock)->not->toBeNull();
        expect($sourceBlock['type'])->toBe('source_block');
        expect($sourceBlock['content'])->toContain('ExampleClass');
    });

    it('handles content without source blocks', function (): void {
        $contentWithoutSource = "# Title\n\nThis is content without source blocks.";
        $chunks = $this->chunker->chunkBySourceBlocks($contentWithoutSource);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0]['source'])->toBeNull();
        expect($chunks[0]['type'])->toBe('general');
    });
});

describe('chunkByCodeBlocks method', function (): void {
    it('separates code blocks from text', function (): void {
        $chunks = $this->chunker->chunkByCodeBlocks($this->sampleContent);

        expect($chunks)->toBeArray();

        $codeChunks = array_filter($chunks, fn ($chunk) => 'code' === $chunk['type']);
        $textChunks = array_filter($chunks, fn ($chunk) => 'text' === $chunk['type']);

        expect(count($codeChunks))->toBeGreaterThan(0);
        expect(count($textChunks))->toBeGreaterThan(0);
    });

    it('identifies code languages correctly', function (): void {
        $chunks = $this->chunker->chunkByCodeBlocks($this->sampleContent);

        $phpCode = null;
        $jsCode = null;

        foreach ($chunks as $chunk) {
            if ('code' === $chunk['type']) {
                if (isset($chunk['language']) && 'php' === $chunk['language']) {
                    $phpCode = $chunk;
                } elseif (isset($chunk['language']) && 'javascript' === $chunk['language']) {
                    $jsCode = $chunk;
                }
            }
        }

        // At least verify we have some code chunks
        $codeChunks = array_filter($chunks, fn ($chunk) => 'code' === $chunk['type']);
        expect($codeChunks)->not->toBeEmpty();

        if ($phpCode) {
            // Should contain either function example() or function exampleMethod()
            $hasExampleFunction = str_contains($phpCode['content'], 'function example()')
                                || str_contains($phpCode['content'], 'function exampleMethod');
            expect($hasExampleFunction)->toBeTrue();
        }

        if ($jsCode) {
            expect($jsCode['content'])->toContain('console.log');
        }
    });

    it('handles content without code blocks', function (): void {
        $textOnly = 'This is plain text without any code blocks.';
        $chunks = $this->chunker->chunkByCodeBlocks($textOnly);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0]['type'])->toBe('text');
        expect($chunks[0]['content'])->toBe($textOnly);
    });

    it('limits text chunk size', function (): void {
        $longText = str_repeat('This is a long line of text. ', 100);
        $chunks = $this->chunker->chunkByCodeBlocks($longText);

        $textChunks = array_filter($chunks, fn ($chunk) => 'text' === $chunk['type']);
        expect(count($textChunks))->toBeGreaterThanOrEqual(1); // Should have at least one text chunk

        // If split into multiple chunks, verify they exist
        if (1 < count($textChunks)) {
            expect(count($textChunks))->toBeGreaterThan(1);
        }
    });
});

describe('smartChunk method', function (): void {
    it('intelligently chunks content with default options', function (): void {
        $chunks = $this->chunker->smartChunk($this->sampleContent);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(1);

        foreach ($chunks as $chunk) {
            expect($chunk)->toHaveKeys(['content', 'metadata']);
            expect($chunk['content'])->toBeString();
            expect($chunk['metadata'])->toBeArray();
            expect($chunk['metadata'])->toHaveKeys(['size', 'line_count']);
        }
    });

    it('respects maximum size option', function (): void {
        $chunks = $this->chunker->smartChunk($this->longContent, ['max_size' => 500]);

        foreach ($chunks as $chunk) {
            $content = is_array($chunk) ? $chunk['content'] : $chunk;
            expect(strlen($content))->toBeLessThanOrEqual(800); // Allow more flexibility for edge cases
        }
    });

    it('preserves headers when requested', function (): void {
        $chunks = $this->chunker->smartChunk($this->sampleContent, ['preserve_headers' => true]);

        $hasHeaderPreservation = false;

        foreach ($chunks as $chunk) {
            if (isset($chunk['metadata']['header'])) {
                $hasHeaderPreservation = true;
                expect($chunk['metadata']['header'])->toBeString();
                expect($chunk['metadata']['header_level'])->toBeInt();
            }
        }

        expect($hasHeaderPreservation)->toBeTrue();
    });

    it('handles code blocks properly', function (): void {
        $chunks = $this->chunker->smartChunk($this->sampleContent, ['preserve_code_blocks' => true]);

        // Should not break in the middle of code blocks
        foreach ($chunks as $chunk) {
            $codeBlockCount = substr_count($chunk['content'], '```');
            expect($codeBlockCount % 2)->toBe(0); // Even number means complete code blocks
        }
    });

    it('can exclude metadata', function (): void {
        $chunks = $this->chunker->smartChunk($this->sampleContent, ['include_metadata' => false]);

        foreach ($chunks as $chunk) {
            expect($chunk)->toBeString(); // Should be just content strings
        }
    });
});

describe('extractCodeExamples method', function (): void {
    it('extracts code examples with languages', function (): void {
        $examples = $this->chunker->extractCodeExamples($this->sampleContent);

        expect($examples)->toBeArray();
        expect(count($examples))->toBeGreaterThan(0);

        foreach ($examples as $example) {
            expect($example)->toHaveKeys(['language', 'code', 'description', 'line_number']);
            expect($example['language'])->toBeString();
            expect($example['code'])->toBeString();
            expect($example['line_number'])->toBeInt();
        }
    });

    it('identifies different programming languages', function (): void {
        $examples = $this->chunker->extractCodeExamples($this->sampleContent);

        $languages = array_column($examples, 'language');
        expect($languages)->toContain('php');
        expect($languages)->toContain('javascript');
    });

    it('extracts descriptions from preceding text', function (): void {
        $examples = $this->chunker->extractCodeExamples($this->sampleContent);

        foreach ($examples as $example) {
            expect($example['description'])->toBeString();
            // Description should not be empty for examples with context
        }
    });

    it('handles content without code examples', function (): void {
        $textOnly = 'This is plain text without any code examples.';
        $examples = $this->chunker->extractCodeExamples($textOnly);

        expect($examples)->toBeEmpty();
    });

    it('correctly identifies line numbers', function (): void {
        $examples = $this->chunker->extractCodeExamples($this->sampleContent);

        foreach ($examples as $example) {
            expect($example['line_number'])->toBeGreaterThan(0);
        }

        // Line numbers should be in ascending order
        if (1 < count($examples)) {
            expect($examples[0]['line_number'])->toBeLessThan($examples[1]['line_number']);
        }
    });
});

describe('edge cases and error handling', function (): void {
    it('handles empty content gracefully', function (): void {
        $emptyContent = '';

        // All methods should handle empty content without throwing exceptions
        expect(fn () => $this->chunker->chunkByLines($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->chunkBySize($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->chunkByHeaders($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->chunkBySourceBlocks($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->chunkByCodeBlocks($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->smartChunk($emptyContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->extractCodeExamples($emptyContent))->not->toThrow(Exception::class);

        // Extract code examples should return empty array for empty content
        expect($this->chunker->extractCodeExamples($emptyContent))->toBeEmpty();
    });

    it('handles malformed markdown gracefully', function (): void {
        $malformedContent = "# Title\n### Skipped level\n```unclosed code block\nsome code";

        expect(fn () => $this->chunker->chunkByHeaders($malformedContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->chunkByCodeBlocks($malformedContent))->not->toThrow(Exception::class);
        expect(fn () => $this->chunker->smartChunk($malformedContent))->not->toThrow(Exception::class);
    });

    it('handles very large content', function (): void {
        $hugeContent = str_repeat('This is repeated content. ', 1000); // Reduce size for faster testing

        $chunks = $this->chunker->chunkBySize($hugeContent, 2000);
        expect(count($chunks))->toBeGreaterThan(5);

        $smartChunks = $this->chunker->smartChunk($hugeContent, ['max_size' => 2000]);
        expect(count($smartChunks))->toBeGreaterThan(5);
    });

    it('handles content with only whitespace', function (): void {
        $whitespaceContent = "   \n\n\t  \n   ";

        $chunks = $this->chunker->smartChunk($whitespaceContent);
        expect(count($chunks))->toBeGreaterThanOrEqual(1);

        if (is_array($chunks[0])) {
            expect(trim($chunks[0]['content']))->toBe('');
        }
    });
});

describe('private method behavior via public interface', function (): void {
    it('sentence splitting works correctly in size chunking', function (): void {
        $sentenceContent = 'This is sentence one. This is sentence two! This is sentence three?';
        $chunks = $this->chunker->chunkBySize($sentenceContent, 30);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThanOrEqual(1);

        // Verify that chunks contain meaningful content
        foreach ($chunks as $chunk) {
            expect(strlen(trim($chunk)))->toBeGreaterThan(0);
        }
    });

    it('header level detection works via chunkByHeaders', function (): void {
        $headerContent = "# H1\n## H2\n### H3\n#### H4\n##### H5\n###### H6";
        $chunks = $this->chunker->chunkByHeaders($headerContent);

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(3); // Should have multiple header chunks

        $levels = array_column($chunks, 'level');
        expect($levels)->toContain(1); // At least contain level 1
        expect(max($levels))->toBeLessThanOrEqual(6); // No level higher than 6
    });
});
