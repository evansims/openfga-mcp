<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use function date;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_encode;
use function mkdir;

final class DebugLogger
{
    private const string LOG_DIR = __DIR__ . '/../logs';

    private const string LOG_FILE = self::LOG_DIR . '/mcp-debug.log';

    /**
     * @param array<string, mixed>|null $context
     * @param string                    $error
     * @param ?string                   $id
     */
    public static function logError(string $error, ?string $id = null, ?array $context = null): void
    {
        if (! self::isDebugEnabled()) {
            return;
        }

        self::ensureLogDirectory();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'ERROR',
            'id' => $id,
            'error' => $error,
            'context' => $context,
        ];

        self::writeLog($entry);
    }

    /**
     * @param array<string, mixed> $params
     * @param string               $method
     * @param ?string              $id
     */
    public static function logRequest(string $method, array $params, ?string $id = null): void
    {
        if (! self::isDebugEnabled()) {
            return;
        }

        self::ensureLogDirectory();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'REQUEST',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ];

        self::writeLog($entry);
    }

    /**
     * @param array<string, mixed> $response
     * @param ?string              $id
     */
    public static function logResponse(array $response, ?string $id = null): void
    {
        if (! self::isDebugEnabled()) {
            return;
        }

        self::ensureLogDirectory();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'RESPONSE',
            'id' => $id,
            'response' => $response,
        ];

        self::writeLog($entry);
    }

    /**
     * @param array<int, mixed> $arguments
     * @param string            $toolName
     * @param mixed             $result
     * @param ?string           $id
     */
    public static function logToolCall(string $toolName, array $arguments, mixed $result, ?string $id = null): void
    {
        if (! self::isDebugEnabled()) {
            return;
        }

        self::ensureLogDirectory();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'TOOL_CALL',
            'id' => $id,
            'tool' => $toolName,
            'arguments' => $arguments,
            'result' => is_array($result) ? $result : ['value' => $result],
        ];

        self::writeLog($entry);
    }

    private static function ensureLogDirectory(): void
    {
        if (! is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0o755, true);
        }
    }

    private static function isDebugEnabled(): bool
    {
        return 'true' === getConfiguredString('OPENFGA_MCP_DEBUG', 'true');
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function writeLog(array $entry): void
    {
        $jsonString = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $jsonString) {
            $jsonString = '{"error": "Failed to encode log entry"}';
        }
        $logLine = $jsonString . "\n";
        file_put_contents(self::LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
}
