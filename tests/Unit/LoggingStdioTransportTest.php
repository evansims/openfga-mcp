<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenFGA\MCP\LoggingStdioTransport;
use ReflectionClass;

describe('LoggingStdioTransport', function (): void {
    describe('constructor', function (): void {
        it('initializes transport', function (): void {
            $transport = new LoggingStdioTransport;
            expect($transport)->toBeInstanceOf(LoggingStdioTransport::class);
        });
    });

    describe('class structure', function (): void {
        it('extends StdioServerTransport', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            $parent = $reflection->getParentClass();
            expect($parent->getName())->toBe('PhpMcp\Server\Transports\StdioServerTransport');
        });

        it('is a final class', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            expect($reflection->isFinal())->toBeTrue();
        });

        it('has expected private methods', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);

            $methods = [
                'fixToolCallJson',
                'logIncomingMessage',
                'logOutgoingMessage',
                'processBufferWithFixes',
            ];

            foreach ($methods as $methodName) {
                $method = $reflection->getMethod($methodName);
                expect($method->isPrivate())->toBeTrue("Method {$methodName} should be private");
            }
        });
    });

    describe('fixToolCallJson method', function (): void {
        it('processes tool call JSON correctly', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            $method = $reflection->getMethod('fixToolCallJson');
            $method->setAccessible(true);

            $transport = new LoggingStdioTransport;

            // Test adding missing arguments
            $input = json_encode([
                'method' => 'tools/call',
                'params' => [
                    'name' => 'testTool',
                ],
            ]);

            $result = $method->invoke($transport, $input);
            $decoded = json_decode($result, true);

            expect($decoded)->toHaveKey('params');
            expect($decoded['params'])->toHaveKey('arguments');
            expect($decoded['params']['arguments'])->toBe([]);
        });

        it('preserves existing arguments', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            $method = $reflection->getMethod('fixToolCallJson');
            $method->setAccessible(true);

            $transport = new LoggingStdioTransport;

            $input = json_encode([
                'method' => 'tools/call',
                'params' => [
                    'name' => 'testTool',
                    'arguments' => ['key' => 'value'],
                ],
            ]);

            $result = $method->invoke($transport, $input);
            $decoded = json_decode($result, true);

            expect($decoded['params']['arguments'])->toBe(['key' => 'value']);
        });

        it('handles non-tool-call methods', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            $method = $reflection->getMethod('fixToolCallJson');
            $method->setAccessible(true);

            $transport = new LoggingStdioTransport;

            $input = json_encode([
                'method' => 'other/method',
                'params' => ['data' => 'test'],
            ]);

            $result = $method->invoke($transport, $input);
            expect($result)->toBe($input);
        });

        it('handles invalid JSON gracefully', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            $method = $reflection->getMethod('fixToolCallJson');
            $method->setAccessible(true);

            $transport = new LoggingStdioTransport;

            $input = 'invalid json {';
            $result = $method->invoke($transport, $input);
            expect($result)->toBe($input);
        });
    });

    describe('processBufferWithFixes method', function (): void {
        it('exists and is private', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);

            expect($reflection->hasMethod('processBufferWithFixes'))->toBeTrue();

            $method = $reflection->getMethod('processBufferWithFixes');
            expect($method->isPrivate())->toBeTrue();
        });
    });

    describe('logging functionality', function (): void {
        it('has method for logging incoming messages', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            expect($reflection->hasMethod('logIncomingMessage'))->toBeTrue();

            $method = $reflection->getMethod('logIncomingMessage');
            expect($method->isPrivate())->toBeTrue();
        });

        it('has method for logging outgoing messages', function (): void {
            $reflection = new ReflectionClass(LoggingStdioTransport::class);
            expect($reflection->hasMethod('logOutgoingMessage'))->toBeTrue();

            $method = $reflection->getMethod('logOutgoingMessage');
            expect($method->isPrivate())->toBeTrue();
        });
    });
});
