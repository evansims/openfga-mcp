<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use function assert;
use function date;
use function dirname;
use function file_put_contents;
use function getmypid;
use function is_array;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;

final class DebugLogger
{
    private static ?string $logDir = null;

    private static ?string $logFile = null;

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

        self::initializePaths();
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

        self::initializePaths();
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

        self::initializePaths();
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
     * @param array<string, mixed> $context
     * @param string               $event
     */
    public static function logServerLifecycle(string $event, array $context = []): void
    {
        if (! self::isDebugEnabled()) {
            return;
        }

        self::initializePaths();
        self::ensureLogDirectory();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'SERVER_LIFECYCLE',
            'event' => $event,
            'context' => $context,
            'pid' => getmypid(),
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

        self::initializePaths();
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
        assert(null !== self::$logDir, 'Log directory path must be initialized');
        assert(null !== self::$logFile, 'Log file path must be initialized');

        $logDir = self::$logDir; // For Psalm null-safety
        $logFile = self::$logFile; // For Psalm null-safety

        if (! is_dir($logDir) && ! mkdir($logDir, 0o755, true)) {
            // If we can't create the directory, log to stderr as fallback
            error_log('[MCP DEBUG] Failed to create log directory: ' . $logDir);

            return;
        }

        // On first run, log where we're writing to help with debugging
        static $logged = false;

        if (! $logged) {
            error_log('[MCP DEBUG] Logging to: ' . $logFile);
            $logged = true;
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    /**
     * Get the project root directory by finding composer.json.
     */
    private static function getProjectRoot(): string
    {
        static $projectRoot = null;

        if (null === $projectRoot) {
            // Start from the current file's directory and walk up to find composer.json
            $dir = __DIR__;

            while ($dir !== dirname($dir)) {
                if (file_exists($dir . '/composer.json')) {
                    $projectRoot = $dir;

                    break;
                }
                $dir = dirname($dir);
            }

            // Fallback to the directory above src if composer.json not found
            if (null === $projectRoot) {
                $projectRoot = dirname(__DIR__);
            }
        }

        // PHPStan doesn't realize $projectRoot is guaranteed to be set above
        assert(is_string($projectRoot));

        return $projectRoot;
    }

    private static function initializePaths(): void
    {
        if (null === self::$logDir) {
            self::$logDir = self::getProjectRoot() . '/logs';
            self::$logFile = self::$logDir . '/mcp-debug.log';
        }
    }

    private static function isDebugEnabled(): bool
    {
        // Default to true for debugging
        return getConfiguredBool('OPENFGA_MCP_DEBUG', true);
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
        assert(null !== self::$logFile, 'Log file path must be initialized');

        $logFile = self::$logFile;
        $result = file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        if (false === $result) {
            error_log('[MCP DEBUG] Failed to write to log file: ' . $logFile);
        }
    }
}
