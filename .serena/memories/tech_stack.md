# Tech Stack

## PHP Version
- **Required**: PHP 8.3+
- Uses modern PHP features including strict typing, readonly properties, union types

## Core Dependencies
- `php-mcp/server` (^3.2): MCP server framework
- `evansims/openfga-php` (^1.5): OpenFGA PHP SDK for client functionality
- `guzzlehttp/guzzle` (^7.2): HTTP client for API communication

## Development Dependencies
- **Testing**: Pest framework (^3) with Mockery for mocking
- **Static Analysis**: 
  - PHPStan (level max with strict rules)
  - Psalm (errorLevel 1)
- **Code Quality**:
  - PHP-CS-Fixer (PSR compliance, modern PHP standards)
  - Rector (automated refactoring)
- **Fuzzing**: nikic/php-fuzzer for security testing

## Infrastructure
- Docker support for containerized deployment
- Docker Compose for local development and testing
- GitHub Actions for CI/CD pipeline

## Standards
- PSR-4 autoloading
- PSR coding standards
- Composer for dependency management
- Semantic versioning