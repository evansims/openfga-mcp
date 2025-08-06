<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Override;
use PhpMcp\Schema\JsonRpc\{Error, Message, Notification, Parser, Request, Response};
use PhpMcp\Server\Exception\TransportException;
use PhpMcp\Server\Transports\StdioServerTransport;
use React\Promise\PromiseInterface;
use Throwable;

use function array_combine;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function error_log;
use function function_exists;
use function is_array;
use function is_numeric;
use function is_string;

final class LoggingStdioTransport extends StdioServerTransport
{
    private string $messageBuffer = '';

    /**
     * @throws TransportException
     */
    public function __construct()
    {
        parent::__construct();

        // Only output debug messages when not in testing environment
        // Check if we're running tests by looking for test functions or PHPUnit classes
        $inTestEnvironment = function_exists('test') || function_exists('it') || class_exists('PHPUnit\Framework\TestCase');

        if (! $inTestEnvironment) {
            error_log('[MCP DEBUG] LoggingStdioTransport initialized - logging is ACTIVE');
        }
    }

    /**
     * Override listen to intercept and fix JSON before parsing.
     */
    #[Override]
    public function listen(): void
    {
        // Call parent to set up streams and basic handlers
        parent::listen();

        // Remove the parent's data handler and add our custom one
        $this->stdin?->removeAllListeners('data');

        $this->stdin?->on('data', function (mixed $chunk): void {
            if (is_string($chunk)) {
                $this->messageBuffer .= $chunk;
                $this->processBufferWithFixes();
            }
        });
    }

    /**
     * Override sendMessage to log responses.
     *
     * @phpstan-param array<mixed> $context
     *
     * @return PromiseInterface<void>
     */
    #[Override]
    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        // Log the outgoing message
        $this->logOutgoingMessage($message);

        return parent::sendMessage($message, $sessionId, $context);
    }

    /**
     * Fix tool call JSON by ensuring arguments field exists.
     * This prevents CallToolRequest constructor errors.
     *
     * @param string $jsonString
     */
    private function fixToolCallJson(string $jsonString): string
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                return $jsonString;
            }

            // Check if this is a tools/call request
            if (
                isset($data['method'])
                && is_string($data['method'])
                && 'tools/call' === $data['method']
                && isset($data['params'])
                && is_array($data['params'])
            ) {
                // Ensure params has arguments field as an array
                if (! isset($data['params']['arguments'])) {
                    $data['params']['arguments'] = [];
                    $toolName = isset($data['params']['name']) && is_string($data['params']['name']) ? $data['params']['name'] : 'unknown';
                    $inTestEnvironment = function_exists('test') || function_exists('it') || class_exists('PHPUnit\Framework\TestCase');

                    if (! $inTestEnvironment) {
                        error_log('[MCP DEBUG] Added missing arguments array for tool call: ' . $toolName);
                    }
                } elseif (! is_array($data['params']['arguments'])) {
                    // Convert non-arrays to empty array
                    $data['params']['arguments'] = [];
                    $toolName = isset($data['params']['name']) && is_string($data['params']['name']) ? $data['params']['name'] : 'unknown';
                    $inTestEnvironment = function_exists('test') || function_exists('it') || class_exists('PHPUnit\Framework\TestCase');

                    if (! $inTestEnvironment) {
                        error_log('[MCP DEBUG] Converted non-array arguments to array for tool call: ' . $toolName);
                    }
                }

                return json_encode($data, JSON_THROW_ON_ERROR);
            }

            // Not a tool call, return original
            return $jsonString;
        } catch (Throwable $throwable) {
            // If JSON parsing fails, return original string
            $inTestEnvironment = function_exists('test') || function_exists('it') || class_exists('PHPUnit\Framework\TestCase');

            if (! $inTestEnvironment) {
                error_log('[MCP DEBUG] Failed to fix JSON, using original: ' . $throwable->getMessage());
            }

            return $jsonString;
        }
    }

    /**
     * @param Notification|Request $message
     */
    private function logIncomingMessage(Request | Notification $message): void
    {
        $params = [];
        $id = null;

        // Get method - both Request and Notification have this property
        $method = $message->method;

        // Get params if available
        if (null !== $message->params) {
            // Convert array keys to strings
            $stringKeys = array_map('strval', array_keys($message->params));
            $stringKeyedParams = array_combine($stringKeys, array_values($message->params));
            $params = $stringKeyedParams;
        }

        // Get ID if it's a Request (Notification doesn't have id)
        if ($message instanceof Request) {
            $messageId = $message->getId();

            if (is_string($messageId) || is_numeric($messageId)) {
                $id = (string) $messageId;
            }
        }

        // Log the request
        DebugLogger::logRequest(
            method: $method,
            params: $params,
            id: $id,
        );
    }

    private function logOutgoingMessage(Message $message): void
    {
        /** @var array<string, mixed> $response */
        $response = [];

        // Handle different message types
        if ($message instanceof Error) {
            $response['error'] = [
                'code' => $message->code,
                'message' => $message->message,
                'data' => $message->data ?? null,
            ];
        } elseif ($message instanceof Response) {
            // Response always has result
            $response['result'] = $message->result;
        }

        // Get ID
        $id = $message->getId();
        $response['id'] = is_string($id) || is_numeric($id) ? (string) $id : null;

        if (0 < count($response)) {
            DebugLogger::logResponse(
                response: $response,
                id: $response['id'] ?? null,
            );
        }
    }

    /**
     * Process buffer with JSON fixes applied before parsing.
     * This method fixes tool call requests that are missing arguments.
     */
    private function processBufferWithFixes(): void
    {
        while (str_contains($this->messageBuffer, "\n")) {
            $pos = strpos($this->messageBuffer, "\n");

            if (false === $pos) {
                break;
            }

            $line = substr($this->messageBuffer, 0, $pos);
            $this->messageBuffer = substr($this->messageBuffer, $pos + 1);

            $trimmedLine = trim($line);

            if ('' === $trimmedLine) {
                continue;
            }

            try {
                // Fix the JSON before parsing
                $fixedJson = $this->fixToolCallJson($trimmedLine);
                $message = Parser::parse($fixedJson);

                // Log the incoming message
                if ($message instanceof Request || $message instanceof Notification) {
                    $this->logIncomingMessage($message);
                }

                $this->emit('message', [$message, 'stdio']);
            } catch (Throwable $e) {
                $this->logger->error('Error parsing message', ['exception' => $e]);
                $error = Error::forParseError('Invalid JSON: ' . $e->getMessage());
                $this->sendMessage($error, 'stdio');

                continue;
            }
        }
    }
}
