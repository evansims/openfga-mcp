# Suggested Commands

## Testing Commands
```bash
# Run all tests (unit, fuzzing, integration)
composer test

# Run unit tests with coverage
composer test:unit

# Run integration tests (uses Docker)
composer test:integration

# Run fuzzing tests (60 seconds by default)
composer test:fuzz
```

## Code Quality Commands
```bash
# Run all linters (PHPStan, Psalm, Rector, PHP-CS-Fixer)
composer lint

# Apply automatic fixes from linters
composer lint:fix
```

## Individual Linters
```bash
# PHPStan analysis (level max)
vendor/bin/phpstan analyze

# Psalm static analysis
vendor/bin/psalm

# Rector dry-run (check what would be changed)
vendor/bin/rector process src --dry-run

# PHP-CS-Fixer dry-run
vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply Rector changes
vendor/bin/rector process src

# Apply PHP-CS-Fixer changes
vendor/bin/php-cs-fixer fix
```

## Docker Commands
```bash
# Build local Docker image
composer docker
# or
docker build -t openfga-mcp:local .

# Run Docker container (offline mode)
docker run --rm -i --pull=always evansims/openfga-mcp:latest

# Run with OpenFGA connection
docker run --rm -i --pull=always \
  -e OPENFGA_MCP_API_URL=http://host.docker.internal:8080 \
  evansims/openfga-mcp:latest
```

## Documentation Commands
```bash
# Sync documentation (verbose)
composer docs:sync

# Sync documentation (quiet)
composer docs:sync:quiet

# Sync documentation from source
composer docs:sync:source
```

## Running the MCP Server
```bash
# Direct execution
bin/openfga-mcp

# Via PHP
php bin/openfga-mcp
```

## System Commands (Darwin/macOS)
```bash
# File operations
ls -la          # List files with details
find . -name    # Find files
grep -r         # Search in files (use ripgrep 'rg' if available)

# Git operations
git status
git diff
git log --oneline -n 10
git add .
git commit -m "message"

# Process management
ps aux | grep php
kill -9 [PID]

# Docker operations
docker ps
docker logs [container]
docker compose up
docker compose down
```

## Environment Setup
```bash
# Required for online mode
export OPENFGA_MCP_API_URL="http://localhost:8080"

# Enable write operations (disabled by default)
export OPENFGA_MCP_API_WRITEABLE=true

# Disable debug logging (enabled by default)
export OPENFGA_MCP_DEBUG=false

# Authentication (optional)
export OPENFGA_MCP_API_TOKEN="your-token"
# OR
export OPENFGA_MCP_API_CLIENT_ID="client-id"
export OPENFGA_MCP_API_CLIENT_SECRET="client-secret"
```