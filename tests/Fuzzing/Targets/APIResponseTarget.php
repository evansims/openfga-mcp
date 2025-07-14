<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function count;
use function is_array;
use function is_int;
use function is_string;
use function strlen;

/**
 * Fuzzing target for API response parsing and handling.
 */
final class APIResponseTarget
{
    private const MAX_ARRAY_DEPTH = 10;

    private const MAX_RESPONSE_SIZE = 10 * 1024 * 1024; // 10MB

    private const MAX_TUPLE_COUNT = 10000;

    public function fuzz(string $input): void
    {
        // Test different response formats
        $this->testJSONParsing($input);
        $this->testTupleResponseParsing($input);
        $this->testStoreListParsing($input);
        $this->testErrorResponseParsing($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Valid responses
            '{"tuples":[{"key":{"user":"user:1","relation":"viewer","object":"doc:1"}}]}',
            '{"stores":[{"id":"store1","name":"Test Store","created_at":"2023-01-01T00:00:00Z"}]}',
            '{"tuples":[],"continuation_token":"eyJwayI6IkxBVEVTVF9WRVJTSU9OIn0"}',
            '{"error":{"code":"invalid_request","message":"Invalid tuple format"}}',

            // Edge cases
            '{}',
            '[]',
            'null',
            'true',
            '"string"',
            '12345',

            // Malformed JSON
            '{tuples:[]}',
            '{"tuples":[{"key":}]}',
            '{"unclosed":',

            // Deep nesting
            '{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":{"j":{}}}}}}}}}}}',

            // Large responses
            '{"tuples":[' . str_repeat('{"key":{"user":"u","relation":"r","object":"o"}},', 100) . ']}',

            // Injection attempts
            '{"tuples":[{"key":{"user":"<script>alert(1)</script>","relation":"viewer","object":"doc:1"}}]}',
            '{"continuation_token":"../../etc/passwd"}',
            '{"stores":[{"id":"store1","name":"Test\u0000Null"}]}',

            // Unicode edge cases
            '{"message":"Test\u200e\u200f\u202a\u202b\u202c\u202d\u202e"}',
            '{"user":"user:\ud800\udc00"}', // Surrogate pair

            // Type confusion
            '{"tuples":{"0":{"key":{"user":"user:1"}}}}',
            '{"stores":"not-an-array"}',
            '{"continuation_token":12345}',
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        $expectedMessages = [
            'Response too large',
            'Null byte in JSON',
            'Array nesting too deep',
            'Object key too long',
            'String value too long',
            'Too many tuples in response',
            'Invalid timestamp format',
            'Invalid continuation token type',
            'Continuation token too long',
            'Tuple key must be object',
            'User must be string',
            'User identifier too long in response',
            'Control characters in user field',
            'Relation must be string',
            'Relation too long in response',
            'Invalid relation format in response',
            'Object must be string',
            'Object identifier too long in response',
            'Store ID must be string',
            'Store name must be string',
            'Store name too long',
            'Invalid created_at format',
            'Invalid updated_at format',
            'Error code must be string or integer',
            'Error message must be string',
            'Error message too long',
            'Stack trace exposed in error response',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function testErrorResponseParsing(string $input): void
    {
        $decoded = @json_decode($input, true);

        if (! is_array($decoded)) {
            return;
        }

        // Check error response structure
        if (isset($decoded['error']) || isset($decoded['code']) || isset($decoded['message'])) {
            // Validate error code
            if (isset($decoded['code'])) {
                if (! is_string($decoded['code']) && ! is_int($decoded['code'])) {
                    throw new Exception('Error code must be string or integer');
                }
            }

            // Validate error message
            if (isset($decoded['message'])) {
                if (! is_string($decoded['message'])) {
                    throw new Exception('Error message must be string');
                }

                if (10000 < strlen($decoded['message'])) {
                    throw new Exception('Error message too long');
                }
            }

            // Check for stack traces (should not be exposed)
            if (isset($decoded['stack']) || isset($decoded['trace'])) {
                throw new Exception('Stack trace exposed in error response');
            }
        }
    }

    private function testJSONParsing(string $input): void
    {
        if (self::MAX_RESPONSE_SIZE < strlen($input)) {
            throw new Exception('Response too large');
        }

        // Try to parse as JSON
        if (empty(trim($input))) {
            return;
        }

        // Check for null bytes
        if (str_contains($input, "\0")) {
            throw new Exception('Null byte in JSON');
        }

        $decoded = json_decode($input, true, self::MAX_ARRAY_DEPTH);

        if (JSON_ERROR_NONE !== json_last_error()) {
            // This is expected for many fuzz inputs
            return;
        }

        // Validate decoded structure
        if (is_array($decoded)) {
            $this->validateArrayStructure($decoded, 0);
        }
    }

    private function testStoreListParsing(string $input): void
    {
        $decoded = @json_decode($input, true);

        if (! is_array($decoded)) {
            return;
        }

        // Check stores array
        if (isset($decoded['stores']) && is_array($decoded['stores'])) {
            foreach ($decoded['stores'] as $store) {
                if (! is_array($store)) {
                    continue;
                }

                // Validate store ID
                if (isset($store['id']) && ! is_string($store['id'])) {
                    throw new Exception('Store ID must be string');
                }

                // Validate store name
                if (isset($store['name'])) {
                    if (! is_string($store['name'])) {
                        throw new Exception('Store name must be string');
                    }

                    if (256 < strlen($store['name'])) {
                        throw new Exception('Store name too long');
                    }
                }

                // Check timestamps
                foreach (['created_at', 'updated_at'] as $field) {
                    if (isset($store[$field]) && ! is_string($store[$field])) {
                        throw new Exception("Invalid {$field} format");
                    }
                }
            }
        }
    }

    private function testTupleResponseParsing(string $input): void
    {
        // Simulate parsing tuple response
        $decoded = @json_decode($input, true);

        if (! is_array($decoded)) {
            return;
        }

        // Check for tuples array
        if (isset($decoded['tuples']) && is_array($decoded['tuples'])) {
            if (self::MAX_TUPLE_COUNT < count($decoded['tuples'])) {
                throw new Exception('Too many tuples in response');
            }

            foreach ($decoded['tuples'] as $tuple) {
                if (! is_array($tuple)) {
                    continue;
                }

                // Validate tuple structure
                if (isset($tuple['key'])) {
                    $this->validateTupleKey($tuple['key']);
                }

                // Check for timestamp
                if (isset($tuple['timestamp']) && ! is_string($tuple['timestamp'])) {
                    throw new Exception('Invalid timestamp format');
                }
            }
        }

        // Check continuation token
        if (isset($decoded['continuation_token'])) {
            if (! is_string($decoded['continuation_token'])) {
                throw new Exception('Invalid continuation token type');
            }

            if (1024 < strlen($decoded['continuation_token'])) {
                throw new Exception('Continuation token too long');
            }

            // Check for suspicious patterns in token
            if (preg_match('/[<>\'"]/', $decoded['continuation_token'])) {
                // Potential injection
            }
        }
    }

    private function validateArrayStructure(array $data, int $depth): void
    {
        if (self::MAX_ARRAY_DEPTH < $depth) {
            throw new Exception('Array nesting too deep');
        }

        foreach ($data as $key => $value) {
            // Validate key length
            if (is_string($key) && 256 < strlen($key)) {
                throw new Exception('Object key too long');
            }

            // Recursively validate nested structures
            if (is_array($value)) {
                $this->validateArrayStructure($value, $depth + 1);
            } elseif (is_string($value)) {
                // Check string length
                if (strlen($value) > 1024 * 1024) {
                    throw new Exception('String value too long');
                }
            }
        }
    }

    private function validateTupleKey($key): void
    {
        if (! is_array($key)) {
            throw new Exception('Tuple key must be object');
        }

        // Validate user field
        if (isset($key['user'])) {
            if (! is_string($key['user'])) {
                throw new Exception('User must be string');
            }

            if (512 < strlen($key['user'])) {
                throw new Exception('User identifier too long in response');
            }

            // Check for control characters
            if (preg_match('/[\x00-\x1F\x7F]/', $key['user'])) {
                throw new Exception('Control characters in user field');
            }
        }

        // Validate relation field
        if (isset($key['relation'])) {
            if (! is_string($key['relation'])) {
                throw new Exception('Relation must be string');
            }

            if (50 < strlen($key['relation'])) {
                throw new Exception('Relation too long in response');
            }

            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key['relation'])) {
                throw new Exception('Invalid relation format in response');
            }
        }

        // Validate object field
        if (isset($key['object'])) {
            if (! is_string($key['object'])) {
                throw new Exception('Object must be string');
            }

            if (512 < strlen($key['object'])) {
                throw new Exception('Object identifier too long in response');
            }
        }
    }
}
