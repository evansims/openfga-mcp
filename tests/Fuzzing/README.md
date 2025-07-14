# Fuzzing Tests

This directory contains fuzzing tests for the OpenFGA MCP server using [nikic/php-fuzzer](https://github.com/nikic/PHP-Fuzzer).

## Overview

Fuzzing is an automated testing technique that provides random or semi-random input to functions to discover bugs, crashes, and potential security vulnerabilities. Our fuzzing setup targets the most security-sensitive parts of the codebase:

1. **Configuration Parsing** - Tests environment variable parsing for edge cases
2. **Tuple Key Construction** - Tests user/relation/object validation
3. **DSL Parser** - Tests OpenFGA DSL syntax parsing for malformed input

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

## Security Considerations

Fuzzing targets should focus on:
- Input validation boundaries
- Injection vulnerabilities (SQL, command, etc.)
- Memory exhaustion attacks
- Infinite loops or recursion
- Type confusion
- Unicode and encoding issues

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