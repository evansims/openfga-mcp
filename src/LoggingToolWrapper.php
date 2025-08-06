<?php

declare(strict_types=1);

namespace OpenFGA\MCP;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

use function array_key_exists;
use function count;
use function is_array;
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

            // Handle MCP-style argument passing (single associative array)
            // If we have a single array argument with string keys, treat it as named parameters
            $actualArgs = $args;

            if (1 === count($args) && isset($args[0]) && is_array($args[0]) && ! array_is_list($args[0])) {
                // It's an associative array - convert to positional arguments
                $reflection = new ReflectionClass($tool);
                $method = $reflection->getMethod($methodName);
                $parameters = $method->getParameters();

                /** @var array<string, mixed> $namedArgs */
                $namedArgs = $args[0];

                $actualArgs = self::buildArgumentList($parameters, $namedArgs, $toolName);
            }

            // Convert args to int-keyed array for logging
            /** @var array<int, mixed> $intKeyedArgs */
            $intKeyedArgs = array_values($actualArgs);

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

                        // Check if method is accessible (public)
                        if (! $method->isPublic()) {
                            throw new Exception(sprintf('Cannot access non-public method %s on %s', $methodName, $tool::class));
                        }

                        // ReflectionMethod::invoke returns mixed, which is expected for dynamic tool calls
                        /** @var mixed $result */
                        $result = $method->invoke($tool, ...$actualArgs);
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

    /**
     * Build argument list from named parameters.
     *
     * @param  list<ReflectionParameter> $parameters
     * @param  array<string, mixed>      $namedArgs
     * @param  string                    $toolName
     * @return list<mixed>
     */
    private static function buildArgumentList(array $parameters, array $namedArgs, string $toolName): array
    {
        $result = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();

            if (array_key_exists($paramName, $namedArgs)) {
                /** @var mixed $value */
                $value = $namedArgs[$paramName];

                /** @var list<mixed> $result */
                $result = [...$result, $value];
            } elseif ($parameter->isDefaultValueAvailable()) {
                /** @var mixed $defaultValue */
                $defaultValue = $parameter->getDefaultValue();

                /** @var list<mixed> $result */
                $result = [...$result, $defaultValue];
            } else {
                throw new Exception(sprintf('Missing required parameter "%s" for %s', $paramName, $toolName));
            }
        }

        return $result;
    }
}
