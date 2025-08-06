# Code Style and Conventions

## General Principles
- **SOLID, DRY, KISS** principles strictly enforced
- **Strict typing**: `declare(strict_types=1)` in all PHP files
- **Final classes** enforced by default
- **Type safety**: Enforced by PHPStan (level max) and Psalm (errorLevel 1)

## PHP Standards
- PSR-4 autoloading with namespace `OpenFGA\MCP`
- PSR coding standards compliance
- Modern PHP 8.3+ features utilized

## Code Structure
- Short array syntax (`[]` not `array()`)
- Single quotes for simple strings
- One class per file
- Explicit visibility declarations
- No unused imports or variables

## Documentation Requirements
- Docblocks required (Psalm `requireDocblocks=true`)
- Type hints for all parameters and returns
- Explicit exception documentation (`@throws`)
- No comments unless explicitly requested

## Error Handling
- Use emoji indicators in responses:
  - ✅ for success
  - ❌ for errors
- Never suppress warnings or errors
- Address all linter warnings directly

## Testing Requirements
- All code changes MUST have accompanying tests
- Tests use Pest framework with describe/it syntax
- Never skip tests
- Test coverage reported via Codecov

## OpenFGA SDK Patterns
- Direct client methods: `listStores()`, `getStore()`, `check()`, `expand()`
- Response methods use `get` prefix: `getStores()`, `getModels()`
- Tuple methods accessed via key: `$tuple->getKey()->getUser()`
- Use `grantPermission()` not `createRelationship()` for creating tuples

## CRITICAL RULES
- **NEVER** use suppression annotations (@suppress, @phpstan-ignore, etc.)
- **NEVER** add ignore statements to configuration files
- **ALWAYS** address the underlying issues directly
- If unable to fix a warning or test, ask for help