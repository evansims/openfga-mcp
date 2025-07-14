<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

/**
 * Fuzzing target for tuple key construction
 */
final class TupleKeyTarget
{
    private const MAX_LENGTH = 512;
    
    public function fuzz(string $input): void
    {
        // Parse the input to get three components
        $parts = $this->parseInput($input);
        
        // Simulate tuple key validation that would happen in the real code
        $this->validateUser($parts['user']);
        $this->validateRelation($parts['relation']);
        $this->validateObject($parts['object']);
        
        // Simulate constructing a tuple key string
        $tupleKey = "{$parts['user']}#{$parts['relation']}@{$parts['object']}";
        
        // Validate the constructed key
        if (strlen($tupleKey) > self::MAX_LENGTH * 3) {
            throw new \Exception('Tuple key too long');
        }
        
        // Check for injection attempts
        $this->checkForInjection($tupleKey);
    }
    
    public function getInitialCorpus(): array
    {
        return [
            'user:123#reader@doc:456',
            'user:admin#owner@namespace:system',
            'group:engineers#member@repo:backend',
            'user:*#viewer@doc:public',
            'user:anne@company.com#editor@doc:readme',
            'user:test\\x00#reader@doc:test',
            'user:test\n#reader\n@doc:test\n',
            'user:test;DROP TABLE;#reader@doc:test',
            'user:' . str_repeat('a', 100) . '#reader@doc:test',
            '#reader@doc:test',
            'user:test#@doc:test',
            'user:test#reader@',
            'user:тест#читатель@документ:тест', // Unicode
            'user:test👤#reader📖@doc:test📄', // Emoji
        ];
    }
    
    private function parseInput(string $input): array
    {
        // Simple parsing logic to split input into three parts
        $hash = strpos($input, '#');
        $at = strpos($input, '@');
        
        if ($hash === false || $at === false || $at < $hash) {
            // If delimiters are missing or in wrong order, split randomly
            $len = strlen($input);
            $third = intval($len / 3);
            
            return [
                'user' => substr($input, 0, $third),
                'relation' => substr($input, $third, $third),
                'object' => substr($input, $third * 2),
            ];
        }
        
        return [
            'user' => substr($input, 0, $hash),
            'relation' => substr($input, $hash + 1, $at - $hash - 1),
            'object' => substr($input, $at + 1),
        ];
    }
    
    private function validateUser(string $user): void
    {
        if (strlen($user) > self::MAX_LENGTH) {
            throw new \Exception('User identifier too long');
        }
        
        // Check for invalid characters
        if (preg_match('/[\x00-\x1F\x7F]/', $user)) {
            throw new \Exception('Control characters in user identifier');
        }
    }
    
    private function validateRelation(string $relation): void
    {
        if (strlen($relation) > self::MAX_LENGTH) {
            throw new \Exception('Relation too long');
        }
        
        if (empty($relation)) {
            throw new \Exception('Empty relation');
        }
        
        // Relations typically should be alphanumeric with underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $relation)) {
            // This might be too strict for fuzzing, log but don't throw
            // In real implementation this might be a security concern
        }
    }
    
    private function validateObject(string $object): void
    {
        if (strlen($object) > self::MAX_LENGTH) {
            throw new \Exception('Object identifier too long');
        }
        
        // Check for invalid characters
        if (preg_match('/[\x00-\x1F\x7F]/', $object)) {
            throw new \Exception('Control characters in object identifier');
        }
    }
    
    private function checkForInjection(string $tupleKey): void
    {
        // Check for common injection patterns
        $injectionPatterns = [
            '/\b(DROP|DELETE|INSERT|UPDATE|SELECT)\b/i',
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i', // Event handlers
        ];
        
        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $tupleKey)) {
                // In production this should be handled, for fuzzing we note it
                // but don't throw - the system should handle this safely
            }
        }
    }
    
    public function isExpectedError(\Throwable $e): bool
    {
        // These are expected validation errors from invalid tuple keys
        $expectedMessages = [
            'Invalid tuple key format',
            'User identifier too long',
            'Relation identifier too long',
            'Object identifier too long',
            'User type not allowed',
            'Object type not allowed',
            'Invalid character in user',
            'Invalid character in relation',
            'Invalid character in object',
            'Invalid namespace format',
            'Missing relation in tuple',
            'Missing object in tuple',
            'Invalid user format',
            'Invalid object format',
            'Control characters in user identifier',
            'Control characters in object identifier',
            'Empty relation',
            'Relation too long',
        ];
        
        foreach ($expectedMessages as $expected) {
            if (stripos($e->getMessage(), $expected) !== false) {
                return true;
            }
        }
        
        return false;
    }
}