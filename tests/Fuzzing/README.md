# Fuzzing Tests

This directory contains fuzzing tests for the OpenFGA MCP server using [nikic/php-fuzzer](https://github.com/nikic/PHP-Fuzzer).

## Overview

Fuzzing is an automated testing technique that provides random or semi-random input to functions to discover bugs, crashes, and potential security vulnerabilities. Our fuzzing setup targets the most security-sensitive parts of the codebase:

1. **Configuration Parsing** - Tests environment variable parsing for edge cases
2. **Tuple Key Construction** - Tests user/relation/object validation  
3. **DSL Parser** - Tests OpenFGA DSL syntax parsing for malformed input
4. **Authentication Configuration** - Tests token, client credentials, and API endpoint parsing
5. **Resource URI Handling** - Tests URI template parsing and parameter substitution
6. **API Response Parsing** - Tests handling of malformed API responses and edge cases
7. **Store/Model ID Validation** - Tests ID format validation and restricted mode bypass attempts
8. **HTTP Transport Configuration** - Tests host/port validation, header injection, and proxy settings

## Running Fuzzing Tests

### Quick Test (1 minute)
```bash
composer test:fuzz
```

### Extended Session (5 minutes)
```bash
FUZZING_DURATION=300 php tests/Fuzzing/run-fuzzers.php
```

### CI Workflow (Nightly)
The fuzzing tests run automatically every night via GitHub Actions. You can also trigger them manually from the Actions tab.

## Adding New Fuzzing Targets

1. Create a new file in `tests/Fuzzing/Targets/` named `YourTarget.php`
2. Implement the fuzzing target class:

```php
<?php

namespace OpenFGA\MCP\Tests\Fuzzing\Targets;

final class YourTarget
{
    public function fuzz(string $input): void
    {
        // Your fuzzing logic here
        // Should throw exceptions on unexpected behavior
    }
    
    public function getInitialCorpus(): array
    {
        return [
            // Seed inputs for better coverage
            'valid input',
            'edge case',
            'potentially problematic input',
        ];
    }
}
```

## Understanding Results

### Corpus Directory
The `corpus/` directory contains inputs that increase code coverage. These are saved and reused across fuzzing sessions to maintain good coverage.

### Crashes Directory
The `crashes/` directory contains inputs that caused the application to crash or behave unexpectedly. Each crash file contains:
- The target that crashed
- The error message
- Stack trace
- The input that caused the crash

### SARIF Reports
When crashes are found, a SARIF report is generated for integration with GitHub's security tab. This provides visibility into potential security issues discovered by fuzzing.

## Current Fuzzing Targets

### 1. ConfigurationTarget
Tests environment variable parsing functions (`getConfiguredString`, `getConfiguredInt`, `getConfiguredBool`) for:
- Extreme values and boundary conditions
- Type confusion attempts
- Special characters and null bytes

### 2. TupleKeyTarget  
Tests OpenFGA tuple key format validation for:
- User/relation/object identifier validation
- Length limits and special characters
- Injection attempts (SQL, command, script)
- Control characters and encoding issues

### 3. DSLParserTarget
Tests OpenFGA DSL syntax parsing for:
- Malformed model definitions
- Recursive and circular dependencies
- Reserved word usage
- Unbalanced brackets and syntax errors

### 4. AuthenticationConfigTarget
Tests authentication configuration parsing for:
- JWT token validation and malformed tokens
- Client credential parsing
- API endpoint URL validation (SSRF prevention)
- Issuer/audience format validation

### 5. ResourceURITarget
Tests resource URI template handling for:
- URI template variable substitution
- Path traversal attempts
- URL encoding issues and double encoding
- Homograph attacks and Unicode normalization

### 6. APIResponseTarget
Tests API response parsing for:
- Malformed JSON structures
- Deep nesting and recursion limits
- Type confusion in response fields
- Large payloads and memory exhaustion

### 7. StoreModelIDTarget
Tests store and model ID validation for:
- ID format and character restrictions
- Reserved ID detection
- Homograph and Unicode normalization attacks
- Restricted mode bypass attempts
- ID list parsing and validation

### 8. HTTPTransportTarget
Tests HTTP transport configuration for:
- Host and port validation (SSRF prevention)
- HTTP header injection attempts
- Proxy configuration security
- Timeout value validation
- Internal network access prevention

## Security Considerations

Fuzzing targets should focus on:
- Input validation boundaries
- Injection vulnerabilities (SQL, command, XSS, etc.)
- Memory exhaustion attacks
- Infinite loops or recursion
- Type confusion
- Unicode and encoding issues
- SSRF and path traversal
- Authentication bypass attempts

## Best Practices

1. **Start with a good corpus** - Provide representative inputs in `getInitialCorpus()`
2. **Focus on untrusted input** - Target functions that process external data
3. **Check for both crashes and logic errors** - Not all bugs cause crashes
4. **Run regularly** - Fuzzing effectiveness increases with time
5. **Fix discovered issues promptly** - Fuzzing finds real bugs

## Troubleshooting

### Out of Memory
If fuzzing runs out of memory, try:
- Reducing `MAX_LENGTH` constants in targets
- Setting memory limits: `php -d memory_limit=512M tests/Fuzzing/run-fuzzers.php`

### No New Coverage
If fuzzing stops finding new paths:
- Add more diverse initial corpus entries
- Target different code paths
- Increase fuzzing duration

### Too Many False Positives
If fuzzing reports too many expected errors:
- Update `isExpectedError()` methods in targets
- Add input validation to filter out known bad inputs