<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenFGA\MCP\DebugLogger;
use ReflectionClass;

use function count;

beforeEach(function (): void {
    // Reset static properties
    $reflection = new ReflectionClass(DebugLogger::class);

    $logDirProperty = $reflection->getProperty('logDir');
    $logDirProperty->setAccessible(true);
    $logDirProperty->setValue(null, null);

    $logFileProperty = $reflection->getProperty('logFile');
    $logFileProperty->setAccessible(true);
    $logFileProperty->setValue(null, null);

    // Create a temporary test directory for logs
    $this->tempLogDir = sys_get_temp_dir() . '/openfga-mcp-test-' . uniqid();

    // Set test log directory
    $logDirProperty->setValue(null, $this->tempLogDir);
    $logFileProperty->setValue(null, $this->tempLogDir . '/mcp-debug.log');
});

afterEach(function (): void {
    // Clean up test directory
    if (isset($this->tempLogDir) && is_dir($this->tempLogDir)) {
        $files = glob($this->tempLogDir . '/*');

        if (false !== $files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        rmdir($this->tempLogDir);
    }

    // Reset environment
    putenv('OPENFGA_MCP_DEBUG');
});

describe('DebugLogger', function (): void {
    describe('debug mode control', function (): void {
        it('logs when debug is enabled (default)', function (): void {
            putenv('OPENFGA_MCP_DEBUG'); // Default is true

            DebugLogger::logRequest('test.method', ['param' => 'value'], 'test-id');

            expect(file_exists($this->tempLogDir . '/mcp-debug.log'))->toBeTrue();
            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            expect($content)->toContain('REQUEST');
            expect($content)->toContain('test.method');
        });

        it('does not log when debug is disabled', function (): void {
            putenv('OPENFGA_MCP_DEBUG=false');

            DebugLogger::logRequest('test.method', ['param' => 'value'], 'test-id');

            expect(file_exists($this->tempLogDir . '/mcp-debug.log'))->toBeFalse();
        });
    });

    describe('logRequest', function (): void {
        it('logs request with all fields', function (): void {
            DebugLogger::logRequest('tools/call', ['tool' => 'createStore', 'args' => ['name' => 'test']], 'req-123');

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry)->toBeArray();
            expect($logEntry['type'])->toBe('REQUEST');
            expect($logEntry['id'])->toBe('req-123');
            expect($logEntry['method'])->toBe('tools/call');
            expect($logEntry['params'])->toBe(['tool' => 'createStore', 'args' => ['name' => 'test']]);
            expect($logEntry['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        });

        it('logs request without ID', function (): void {
            DebugLogger::logRequest('initialize', ['capabilities' => []], null);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['id'])->toBeNull();
            expect($logEntry['method'])->toBe('initialize');
        });
    });

    describe('logResponse', function (): void {
        it('logs response with all fields', function (): void {
            $response = [
                'result' => ['success' => true],
                'meta' => ['duration' => 123],
            ];

            DebugLogger::logResponse($response, 'resp-456');

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['type'])->toBe('RESPONSE');
            expect($logEntry['id'])->toBe('resp-456');
            expect($logEntry['response'])->toBe($response);
            expect($logEntry['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        });
    });

    describe('logError', function (): void {
        it('logs error with context', function (): void {
            $context = [
                'file' => 'test.php',
                'line' => 42,
                'trace' => ['stack', 'trace'],
            ];

            DebugLogger::logError('Something went wrong', 'err-789', $context);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['type'])->toBe('ERROR');
            expect($logEntry['id'])->toBe('err-789');
            expect($logEntry['error'])->toBe('Something went wrong');
            expect($logEntry['context'])->toBe($context);
        });

        it('logs error without context', function (): void {
            DebugLogger::logError('Simple error', null, null);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['error'])->toBe('Simple error');
            expect($logEntry['context'])->toBeNull();
        });
    });

    describe('logToolCall', function (): void {
        it('logs tool call with array result', function (): void {
            $arguments = ['store' => 'store-123', 'model' => 'latest'];
            $result = ['allowed' => true, 'resolution' => 'direct'];

            DebugLogger::logToolCall('checkPermission', $arguments, $result, 'tool-001');

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['type'])->toBe('TOOL_CALL');
            expect($logEntry['id'])->toBe('tool-001');
            expect($logEntry['tool'])->toBe('checkPermission');
            expect($logEntry['arguments'])->toBe($arguments);
            expect($logEntry['result'])->toBe($result);
        });

        it('logs tool call with non-array result', function (): void {
            DebugLogger::logToolCall('simpleMethod', [], 'string result', null);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['result'])->toBe(['value' => 'string result']);
        });
    });

    describe('logServerLifecycle', function (): void {
        it('logs server lifecycle events with PID', function (): void {
            $context = [
                'version' => '1.0.0',
                'capabilities' => ['tools', 'resources'],
            ];

            DebugLogger::logServerLifecycle('startup', $context);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['type'])->toBe('SERVER_LIFECYCLE');
            expect($logEntry['event'])->toBe('startup');
            expect($logEntry['context'])->toBe($context);
            expect($logEntry['pid'])->toBeInt();
            expect($logEntry['pid'])->toBe(getmypid());
        });

        it('logs server lifecycle events without context', function (): void {
            DebugLogger::logServerLifecycle('shutdown');

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['event'])->toBe('shutdown');
            expect($logEntry['context'])->toBe([]);
        });
    });

    describe('file operations', function (): void {
        it('creates log directory if it does not exist', function (): void {
            // Directory should not exist initially
            expect(is_dir($this->tempLogDir))->toBeFalse();

            DebugLogger::logRequest('test', []);

            expect(is_dir($this->tempLogDir))->toBeTrue();
            expect(file_exists($this->tempLogDir . '/mcp-debug.log'))->toBeTrue();
        });

        it('appends to existing log file', function (): void {
            DebugLogger::logRequest('first', ['order' => 1]);
            DebugLogger::logRequest('second', ['order' => 2]);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $lines = explode("\n", trim($content));

            expect(count($lines))->toBe(2);

            $firstEntry = json_decode($lines[0], true);
            $secondEntry = json_decode($lines[1], true);

            expect($firstEntry['method'])->toBe('first');
            expect($secondEntry['method'])->toBe('second');
        });

        it('handles multiple log entries correctly', function (): void {
            DebugLogger::logRequest('request1', []);
            DebugLogger::logResponse(['result' => 'ok'], 'id1');
            DebugLogger::logError('Error occurred');
            DebugLogger::logToolCall('tool1', [], 'result');
            DebugLogger::logServerLifecycle('event1');

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $lines = explode("\n", trim($content));

            expect(count($lines))->toBe(5);

            $types = array_map(function ($line) {
                $entry = json_decode($line, true);

                return $entry['type'];
            }, $lines);

            expect($types)->toBe([
                'REQUEST',
                'RESPONSE',
                'ERROR',
                'TOOL_CALL',
                'SERVER_LIFECYCLE',
            ]);
        });
    });

    describe('JSON encoding', function (): void {
        it('handles special characters in log entries', function (): void {
            $params = [
                'unicode' => '日本語',
                'emoji' => '🎉',
                'special' => "line\nbreak\ttab",
            ];

            DebugLogger::logRequest('test', $params);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['params'])->toBe($params);
        });

        it('handles deeply nested structures', function (): void {
            $nested = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => 'deep value',
                        ],
                    ],
                ],
            ];

            DebugLogger::logResponse($nested);

            $content = file_get_contents($this->tempLogDir . '/mcp-debug.log');
            $logEntry = json_decode($content, true);

            expect($logEntry['response'])->toBe($nested);
        });
    });

    describe('project root detection', function (): void {
        it('finds project root from composer.json location', function (): void {
            // Use reflection to access private method
            $reflection = new ReflectionClass(DebugLogger::class);
            $method = $reflection->getMethod('getProjectRoot');
            $method->setAccessible(true);

            $projectRoot = $method->invoke(null);

            expect($projectRoot)->toBeString();
            expect(file_exists($projectRoot . '/composer.json'))->toBeTrue();
        });
    });
});
