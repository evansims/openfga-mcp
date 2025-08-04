<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use PhpMcp\Server\Transports\StdioServerTransport;
use ReflectionClass;
use ReflectionException;

use function is_array;
use function is_string;
use function json_decode;

final class LoggingStdioTransport extends StdioServerTransport
{
    /**
     * @param string $message
     *
     * @throws ReflectionException
     */
    protected function handleMessage(string $message): ?string
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($message, true);

        // Log incoming request
        if (is_array($decoded)) {
            /** @var mixed $method */
            $method = $decoded['method'] ?? 'unknown';

            /** @var mixed $params */
            $params = $decoded['params'] ?? [];
            $id = isset($decoded['id']) && (is_string($decoded['id']) || is_numeric($decoded['id']))
                ? (string) $decoded['id']
                : null;

            if (is_string($method) && is_array($params)) {
                /** @var array<string, mixed> $stringKeyedParams */
                $stringKeyedParams = [];

                /** @psalm-suppress MixedAssignment */
                foreach ($params as $key => $value) {
                    /** @psalm-suppress MixedAssignment */
                    $stringKeyedParams[(string) $key] = $value;
                }

                DebugLogger::logRequest(
                    method: $method,
                    params: $stringKeyedParams,
                    id: $id,
                );
            }
        }

        // Process the message using parent logic via reflection (since handleMessage is protected)
        $reflection = new ReflectionClass(parent::class);
        $parentMethod = $reflection->getMethod('handleMessage');

        /** @var string|null $response */
        $response = $parentMethod->invoke($this, $message);

        // Log outgoing response
        if (is_string($response)) {
            /** @var array<string, mixed>|null $decodedResponse */
            $decodedResponse = json_decode($response, true);

            if (is_array($decodedResponse) && is_array($decoded)) {
                $id = isset($decoded['id']) && (is_string($decoded['id']) || is_numeric($decoded['id']))
                    ? (string) $decoded['id']
                    : null;

                DebugLogger::logResponse(
                    response: $decodedResponse,
                    id: $id,
                );
            }
        }

        return $response;
    }
}

