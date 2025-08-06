# Project Structure

## Root Directory
```
/Users/evan/Developer/evansims/openfga-mcp/
├── bin/
│   └── openfga-mcp          # Main executable entry point
├── src/                     # Source code
│   ├── Server.php           # Main MCP server implementation
│   ├── Helpers.php          # Helper functions (includes isOfflineMode())
│   ├── OfflineClient.php    # Offline mode client implementation
│   ├── DebugLogger.php      # Debug logging functionality
│   ├── LoggingStdioTransport.php    # Transport layer logging
│   ├── LoggingToolWrapper.php       # Tool execution logging
│   ├── Tools/               # MCP Tools (executable functions)
│   │   ├── AbstractTools.php
│   │   ├── ModelTools.php
│   │   ├── RelationshipTools.php
│   │   └── StoreTools.php
│   ├── Resources/           # MCP Resources (read-only data)
│   │   ├── AbstractResources.php
│   │   ├── DocumentationResources.php
│   │   ├── ModelResources.php
│   │   ├── RelationshipResources.php
│   │   └── StoreResources.php
│   ├── Templates/           # Resource templates
│   ├── Prompts/            # AI prompt templates
│   ├── Completions/        # Auto-completion providers
│   ├── Models/             # Data models
│   ├── Responses/          # Response formatting
│   └── Documentation/      # Documentation indexing system
├── tests/                  # Test suite
├── docs/                   # SDK documentation
├── tools/                  # Development tools
├── scripts/               # Utility scripts
├── .github/               # GitHub configuration
│   └── workflows/         # CI/CD workflows
├── CLAUDE.md             # AI assistant instructions
├── composer.json         # PHP dependencies
├── phpstan.neon.dist    # PHPStan configuration
├── psalm.xml.dist       # Psalm configuration
├── .php-cs-fixer.dist.php  # Code style configuration
├── rector.php           # Rector configuration
├── Dockerfile          # Production container
└── docker-compose.*.yml # Docker configurations
```

## Key Architectural Components

### Entry Point
- `bin/openfga-mcp`: CLI entry point that loads autoloader and runs Server.php

### Core Classes
- `Server.php`: Main server orchestration, handles MCP protocol
- `OfflineClient.php`: Implements ClientInterface for offline mode
- `Helpers.php`: Global helper functions

### Abstract Base Classes
- `AbstractTools`: Base for all tool implementations (offline/permission checks)
- `AbstractResources`: Base for all resource implementations

### MCP Implementation Pattern
Each feature area (Store, Model, Relationship, Documentation) has:
- A Tools class for actions (create, update, delete)
- A Resources class for read operations
- Optional Templates and Prompts classes

### Namespace Structure
- Root namespace: `OpenFGA\MCP`
- Test namespace: `OpenFGA\MCP\Tests`

## Environment Variables
Configuration primarily through environment variables:
- `OPENFGA_MCP_API_*`: API configuration
- `OPENFGA_MCP_DEBUG`: Debug logging control

## Docker Architecture
- Production image based on Alpine Linux
- Multi-stage build for optimization
- Supports both online and offline modes
- Integration tests run in Docker environment