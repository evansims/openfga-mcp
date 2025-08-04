<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

use function method_exists;
use function sprintf;

final class LoggingToolWrapper
{
    /**
     * @param  object                    $tool
     * @param  string                    $methodName
     * @return callable(mixed...): mixed
     */
    public static function wrapTool(object $tool, string $methodName): callable
    {
        return static function (...$args) use ($tool, $methodName) {
            $toolName = $tool::class . '::' . $methodName;

            // Convert args to int-keyed array for logging
            /** @var array<int, mixed> $intKeyedArgs */
            $intKeyedArgs = array_values($args);

            try {
                // Log the tool call with arguments
                DebugLogger::logToolCall(
                    toolName: $toolName,
                    arguments: $intKeyedArgs,
                    result: null, // Will be filled after execution
                    id: null,
                );

                // Call the original method using reflection for proper type handling
                if (method_exists($tool, $methodName)) {
                    try {
                        $reflection = new ReflectionClass($tool);
                        $method = $reflection->getMethod($methodName);

                        // ReflectionMethod::invoke returns mixed, which is expected for dynamic tool calls
                        /** @var mixed $result */
                        $result = $method->invoke($tool, ...$args);
                    } catch (ReflectionException $e) {
                        throw new Exception(sprintf('Failed to invoke method %s on %s: %s', $methodName, $tool::class, $e->getMessage()), $e->getCode(), $e);
                    }
                } else {
                    throw new Exception(sprintf('Method %s not found on %s', $methodName, $tool::class));
                }

                // Log successful result
                DebugLogger::logToolCall(
                    toolName: $toolName,
                    arguments: $intKeyedArgs,
                    result: $result,
                    id: null,
                );

                return $result;
            } catch (Exception $exception) {
                // Log error
                DebugLogger::logError(
                    error: $exception->getMessage(),
                    id: null,
                    context: [
                        'tool' => $toolName,
                        'arguments' => $intKeyedArgs,
                        'exception_class' => $exception::class,
                        'trace' => $exception->getTraceAsString(),
                    ],
                );

                // Re-throw the exception
                throw $exception;
            }
        };
    }
}
