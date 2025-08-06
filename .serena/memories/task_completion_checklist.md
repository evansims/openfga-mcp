# Task Completion Checklist

## After Making Code Changes

### 1. Run Linters (REQUIRED)
```bash
composer lint
```
All linters MUST pass:
- PHPStan (level max)
- Psalm (errorLevel 1)  
- Rector (no suggested changes)
- PHP-CS-Fixer (no formatting issues)

If there are issues, fix them with:
```bash
composer lint:fix
```

### 2. Run Tests (REQUIRED)
```bash
# Run unit tests
composer test:unit

# If changes affect integration
composer test:integration
```
All tests MUST pass. Never skip failing tests.

### 3. Check for Type Safety
- Ensure all parameters have type declarations
- Ensure all returns have type declarations
- No mixed types unless absolutely necessary
- No suppression annotations

### 4. Verify Code Standards
- Uses `declare(strict_types=1)`
- Follows PSR standards
- No unused imports
- No unused variables
- Consistent error handling with emoji indicators

### 5. Documentation
- Update docblocks if method signatures changed
- Ensure @throws annotations are accurate
- No unnecessary comments

## Before Committing (if asked)

### 1. Run Full Test Suite
```bash
composer test
```

### 2. Check Git Status
```bash
git status
git diff
```

### 3. Stage Changes
```bash
git add [files]
```

### 4. Create Meaningful Commit
```bash
git commit -m "type: description"
```
Types: feat, fix, docs, style, refactor, test, chore

## CRITICAL REMINDERS
- **NEVER** use @suppress or @phpstan-ignore annotations
- **NEVER** skip tests or ignore failures  
- **ALWAYS** address linter warnings directly
- **ALWAYS** include tests for new functionality
- If unable to fix an issue, ask for help

## Common Issues and Solutions

### PHPStan/Psalm Warnings
- Don't suppress - fix the underlying type issue
- Add proper type declarations
- Use PHPDocs for complex types

### Test Failures
- Fix the code, not the test
- Ensure test isolation
- Check for side effects

### Code Style Issues
- Run `composer lint:fix` first
- Manual fixes for logical issues
- Follow existing patterns in codebase