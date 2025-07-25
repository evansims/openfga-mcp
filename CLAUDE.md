# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP implementation of a Model Context Protocol (MCP) server for OpenFGA, providing AI agents with tools to manage and query OpenFGA authorization servers.

## Model Context Protocol Concepts

Model Context Protocol solves the fundamental problem of connecting AI to your existing tools and data in a standardized, secure way. MCP creates a common language between AI models and external systems. Instead of building point-to-point integrations, you implement MCP servers that expose your tools, data, and prompts through a standard interface. Any MCP-compatible AI can then connect to any MCP server.

The real power emerges when your AI can seamlessly move between reading your calendar (resource), sending emails (tool), querying databases (resource template), and following company-specific workflows (prompts) - all through the same protocol, with proper security boundaries.

**Tools** are executable functions that perform actions or operations. Think of them as API endpoints the AI can call - they do things. A tool might send an email, create a database record, or run a calculation. Tools typically modify state or trigger external processes.

**Resources** are read-only data sources the AI can access for information. They provide content rather than perform actions. A resource might be a file, database query result, or API response that gives the AI context or data to work with.

**Resource Templates** are parameterized blueprints for generating resources dynamically. Instead of static resources, templates let you create resources on-demand based on input parameters. For example, a template might generate a user profile resource when given a user ID, or create a report resource based on date ranges.

**Prompts** are reusable prompt templates that can be invoked with parameters to generate structured prompts for the AI. They're essentially parameterized instructions or context that help shape how the AI approaches specific tasks.

The key distinction: tools _act_, resources _inform_, templates _generate_, and prompts _guide_. Tools change the world, resources describe it, templates make resources flexible, and prompts shape how the AI thinks about both.

This separation keeps concerns clean - you're not mixing data access with actions, and you can build flexible, reusable components that compose well together.

## Development Commands

### Linting and Code Quality

- `composer lint` - Run all linters (PHPStan, Psalm, Rector, PHP-CS-Fixer)
- `composer lint:fix` - Apply available automatic fixes from linters

### Testing

- `composer test` - Run all tests using Pest framework
- `composer test:unit` - Run unit tests using Pest framework
- `composer test:integration` - Run integration tests using Pest framework inside a Docker environment

## Architecture

The project has a minimal structure focused on implementing an MCP server:

- **src/Server.php**: Main class that implements the MCP server
- **src/Helpers.php**: Helper functions for MCP server
- **src/Tools/**: Directory containing classes that expose tools for MCP client to invoke
- **src/Resources/**: Directory containing classes that expose resources for MCP client to access
- **src/Templates/**: Directory containing classes that expose resource templates for MCP client to generate resources from
- **src/Prompts/**: Directory containing classes that expose prompts for MCP client to generate structured prompts from
- Built on top of `php-mcp/server` framework
- Uses `evansims/openfga-php` for OpenFGA client functionality

## Code Standards

- **PHP Version**: 8.3+ required
- **Type Safety**: Strict typing enforced by PHPStan (level max) and Psalm (errorLevel 1)
- **Code Style**: Modern PHP with PSR compliance, final classes enforced
- **Namespace**: `OpenFGA\MCP`
- **Autoloading**: PSR-4
- **Testing**: Pest framework, Mockery dependency

## Mission Critical Notes

- Always follow SOLID, DRY and KISS principles.
- All tests and linters MUST pass after each change.
- All code changes MUST have accompanying tests.
- All code changes SHOULD have a clear and concise purpose.
- When addressing PHPStan, Psalm or other PHP linter warnings, always address the underlying issues directly, never use suppression tactics. You are forbidden from using suppression annotations or adding ignore statements to configuration files. If you are unable to address a warning, ask for help.
- Never skip tests. If a test fails, you must address the underlying cause directly. If you are unable to fix a failing test, ask for help.

## Important Implementation Notes

### MCP Resources Implementation

When implementing MCP resources:

1. Resources are read-only data sources - they should never modify state
2. Resource URIs should follow the pattern: `openfga://[resource-path]`
3. Resource templates use URI templates (RFC 6570) for parameterization
4. All resource classes should extend `AbstractResources` and be placed in `src/Resources/`
5. Resource methods should return arrays or strings that will be auto-converted to TextContent
6. Use the same error handling pattern as tools (with emoji indicators: ✅ for success, ❌ for errors)

### OpenFGA PHP SDK Method Names

The OpenFGA PHP SDK uses these method patterns:
- Direct client methods: `listStores()`, `getStore()`, `check()`, `expand()`, `readTuples()`
- Response methods typically use `get` prefix: `getStores()`, `getModels()`, `getTuples()`
- Tuple methods are on the key: `$tuple->getKey()->getUser()`, not `$tuple->getUser()`
- The `check()` method requires both store and model parameters
- For creating tuples, use `grantPermission()` not `createRelationship()`
