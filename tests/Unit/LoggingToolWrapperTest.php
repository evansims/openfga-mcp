<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use OpenFGA\MCP\LoggingToolWrapper;
use ReflectionClass;

// Test tool class for use in tests
final class LoggingToolWrapperTest
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

beforeEach(function (): void {
    $this->tool = new TestTool;
});

describe('LoggingToolWrapper', function (): void {
    describe('wrapTool', function (): void {
        it('wraps and executes simple method successfully', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'simpleMethod');

            expect($wrapped)->toBeCallable();

            $result = $wrapped();
            expect($result)->toBe('success');
        });

        it('wraps and executes method with arguments', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'methodWithArgs');

            $result = $wrapped(['arg1' => 'test', 'arg2' => 42]);

            expect($result)->toBeArray();
            expect($result['arg1'])->toBe('test');
            expect($result['arg2'])->toBe(42);
        });

        it('handles exceptions from wrapped method', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'methodThatThrows');

            expect(fn () => $wrapped())->toThrow(Exception::class, 'Test exception');
        });

        it('throws exception for non-existent method', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'nonExistentMethod');

            // Exception will be thrown when trying to invoke non-existent method
            expect(fn () => $wrapped())
                ->toThrow(Exception::class, 'Method nonExistentMethod not found');
        });

        it('handles private method access attempts', function (): void {
            // The wrapper will succeed in creating the wrapper, but calling it will throw
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'privateMethod');

            // Exception will be thrown when trying to invoke the private method
            expect(fn () => $wrapped())
                ->toThrow(Exception::class);
        });

        it('preserves argument order and types', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'methodWithArgs');

            // Test with different argument orders
            $result1 = $wrapped(['arg1' => 'first', 'arg2' => 1]);
            expect($result1)->toBe(['arg1' => 'first', 'arg2' => 1]);

            $result2 = $wrapped(['arg2' => 2, 'arg1' => 'second']);
            expect($result2)->toBe(['arg1' => 'second', 'arg2' => 2]);
        });

        it('returns callable that preserves context', function (): void {
            $wrapped1 = LoggingToolWrapper::wrapTool($this->tool, 'simpleMethod');
            $wrapped2 = LoggingToolWrapper::wrapTool($this->tool, 'simpleMethod');

            // Both should work independently
            expect($wrapped1())->toBe('success');
            expect($wrapped2())->toBe('success');
        });

        it('handles complex return types', function (): void {
            $wrapped = LoggingToolWrapper::wrapTool($this->tool, 'complexReturnType');

            $result = $wrapped();

            expect($result)->toBeObject();
            expect($result->key)->toBe('value');
            expect($result->nested)->toBeArray();
            expect($result->nested['data'])->toBeTrue();
        });
    });

    describe('static method behavior', function (): void {
        it('is a static method', function (): void {
            $reflection = new ReflectionClass(LoggingToolWrapper::class);
            $method = $reflection->getMethod('wrapTool');

            expect($method->isStatic())->toBeTrue();
            expect($method->isPublic())->toBeTrue();
        });
    });

    describe('class structure', function (): void {
        it('is a final class', function (): void {
            $reflection = new ReflectionClass(LoggingToolWrapper::class);
            expect($reflection->isFinal())->toBeTrue();
        });
    });
});
