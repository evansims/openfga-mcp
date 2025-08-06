<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Unit;

use Exception;

// Test tool class for use in LoggingToolWrapperTest
final class TestTool
{
    public function complexReturnType(): object
    {
        return (object) ['key' => 'value', 'nested' => ['data' => true]];
    }

    public function methodThatThrows(): void
    {
        throw new Exception('Test exception');
    }

    public function methodWithArgs(string $arg1, int $arg2): array
    {
        return ['arg1' => $arg1, 'arg2' => $arg2];
    }

    public function simpleMethod(): string
    {
        return 'success';
    }

    private function privateMethod(): string
    {
        return 'private';
    }
}
