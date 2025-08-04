<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Exception;

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

                // Call the original method
                if (method_exists($tool, $methodName)) {
                    /** @psalm-suppress MixedAssignment */
                    /** @phpstan-ignore-next-line method.dynamicName */
                    $result = $tool->{$methodName}(...$args);
                } else {
                    throw new Exception(sprintf('Method %s not found on ', $methodName) . $tool::class);
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
