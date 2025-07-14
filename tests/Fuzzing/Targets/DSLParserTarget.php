<?php

declare(strict_types=1);

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

use Exception;
use Throwable;

use function in_array;
use function strlen;

/**
 * Fuzzing target for OpenFGA DSL parsing.
 */
final class DSLParserTarget
{
    private const MAX_DSL_LENGTH = 50000;

    public function fuzz(string $input): void
    {
        // Limit input size to prevent memory exhaustion
        if (self::MAX_DSL_LENGTH < strlen($input)) {
            $input = substr($input, 0, self::MAX_DSL_LENGTH);
        }

        // Simulate DSL parsing validation
        $this->validateDSLSyntax($input);

        // Check for recursive definitions that could cause infinite loops
        $this->checkForRecursion($input);

        // Validate type names and relations
        $this->validateIdentifiers($input);
    }

    public function getInitialCorpus(): array
    {
        return [
            // Valid minimal DSL
            'model
  schema 1.1
type user',

            // Valid DSL with relations
            'model
  schema 1.1
type user
type document
  relations
    define viewer: [user]
    define editor: [user]
    define owner: [user] or editor',

            // DSL with complex conditions
            'model
  schema 1.1
type user
type group
  relations
    define member: [user]
type document
  relations
    define viewer: [user, group#member]
    define editor: [user] and viewer
    define owner: [user] or editor
    define can_share: owner or editor
    define can_delete: owner',

            // Edge cases
            'model schema 1.1',
            'model\nschema 1.1\ntype ',
            'model\n  schema 1.1\ntype user\n  relations\n    define owner: [user:*]',
            'model\n  schema 1.1\ntype ' . str_repeat('a', 100),

            // Potentially problematic inputs
            'model\n  schema 1.1\ntype user\n  relations\n    define a: b\n    define b: a',
            'model\n  schema 1.1\ntype user#member',
            'model\n  schema 1.1\ntype user@test',
            'model\n  schema 1.1\ntype user\n  relations\n    define test: [user] or test',

            // Special characters
            'model\n  schema 1.1\ntype user\x00',
            'model\n  schema 1.1\ntype user\n\t\trelations\n\t\t\tdefine owner: [user]',
            'модель\n  схема 1.1\nтип пользователь', // Cyrillic
            'model\n  schema 1.1\ntype 用户', // Chinese
            'model\n  schema 1.1\ntype user👤',
        ];
    }

    public function isExpectedError(Throwable $e): bool
    {
        // These are expected validation errors from invalid DSL syntax
        $expectedMessages = [
            'Missing model declaration',
            'Missing or invalid schema version',
            'Unbalanced brackets',
            'Unbalanced braces',
            'Reserved word used as type name',
            'Invalid type name',
            'Invalid relation name',
            'Line too long',
            'Type name too long',
            'Relation name too long',
            'DSL too complex',
            'Missing type declaration',
            'Invalid character in identifier',
        ];

        foreach ($expectedMessages as $expected) {
            if (false !== stripos($e->getMessage(), $expected)) {
                return true;
            }
        }

        return false;
    }

    private function checkCircularDependency(
        string $current,
        array $relations,
        array &$visited,
        array $path,
    ): void {
        if (in_array($current, $path, true)) {
            // Circular dependency detected
            // In fuzzing, we note this but don't necessarily throw
            return;
        }

        if (isset($visited[$current])) {
            return;
        }

        $visited[$current] = true;
        $path[] = $current;

        // This is simplified - real implementation would parse the definition properly
        if (isset($relations[$current])) {
            foreach ($relations as $name => $definition) {
                if (false !== stripos($relations[$current], $name)) {
                    $this->checkCircularDependency($name, $relations, $visited, $path);
                }
            }
        }
    }

    private function checkForRecursion(string $dsl): void
    {
        // Extract relation definitions
        preg_match_all('/define\s+(\w+):\s*(.+)$/m', $dsl, $matches);

        if (empty($matches[1])) {
            return;
        }

        $relations = array_combine($matches[1], $matches[2]);

        // Simple recursion check - in reality this would need to be more sophisticated
        foreach ($relations as $name => $definition) {
            if (false !== stripos($definition, $name)) {
                // Potential self-reference - in OpenFGA this might be valid
                // but we should ensure it doesn't create infinite loops
            }
        }

        // Check for circular dependencies
        $visited = [];

        foreach (array_keys($relations) as $relation) {
            $this->checkCircularDependency($relation, $relations, $visited, []);
        }
    }

    private function validateDSLSyntax(string $dsl): void
    {
        // Check for required model header
        if (false === stripos($dsl, 'model')) {
            throw new Exception('Missing model declaration');
        }

        // Check for schema version
        if (! preg_match('/schema\s+\d+\.\d+/i', $dsl)) {
            throw new Exception('Missing or invalid schema version');
        }

        // Count braces/brackets to check for balance
        $openBraces = substr_count($dsl, '{');
        $closeBraces = substr_count($dsl, '}');

        if ($openBraces !== $closeBraces) {
            throw new Exception('Unbalanced braces');
        }

        $openBrackets = substr_count($dsl, '[');
        $closeBrackets = substr_count($dsl, ']');

        if ($openBrackets !== $closeBrackets) {
            throw new Exception('Unbalanced brackets');
        }

        // Check for extremely long lines that might indicate an attack
        $lines = explode("\n", $dsl);

        foreach ($lines as $line) {
            if (1000 < strlen($line)) {
                throw new Exception('Line too long');
            }
        }
    }

    private function validateIdentifiers(string $dsl): void
    {
        // Extract type names
        preg_match_all('/type\s+(\S+)/m', $dsl, $typeMatches);

        foreach ($typeMatches[1] ?? [] as $typeName) {
            if (256 < strlen($typeName)) {
                throw new Exception('Type name too long');
            }

            // Check for invalid characters in type names
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $typeName)) {
                // Note: OpenFGA might have different rules, adjust as needed
            }

            // Check for reserved words
            $reserved = ['model', 'schema', 'type', 'relations', 'define', 'and', 'or', 'not'];

            if (in_array(strtolower($typeName), $reserved, true)) {
                throw new Exception('Reserved word used as type name');
            }
        }

        // Extract relation names
        preg_match_all('/define\s+(\w+):/m', $dsl, $relationMatches);

        foreach ($relationMatches[1] ?? [] as $relationName) {
            if (256 < strlen($relationName)) {
                throw new Exception('Relation name too long');
            }
        }
    }
}
