# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP implementation of a Model Context Protocol (MCP) server for OpenFGA, providing AI agents with tools to manage and query OpenFGA authorization servers.

## Development Commands

### Linting and Code Quality

- `composer lint` - Run all linters (PHPStan, Psalm, Rector, PHP-CS-Fixer)
- `composer lint:fix` - Run linters with automatic fixes

### Testing

- `composer test` - Run all tests using Pest PHP framework

## Architecture

The project has a minimal structure focused on implementing an MCP server:

- **Server.php**: Main server class that accepts OpenFGA client and configuration via constructor
- Built on top of `php-mcp/server` framework
- Uses `evansims/openfga-php` for OpenFGA client functionality

## Code Standards

- **PHP Version**: 8.3+ required
- **Type Safety**: Strict typing enforced by PHPStan (level max) and Psalm (errorLevel 1)
- **Code Style**: Modern PHP with PSR compliance, final classes enforced
- **Namespace**: `OpenFGA\MCP`
- **Autoloading**: PSR-4

## Configuration Handling

The server accepts configuration through:

1. Environment variables (preferred)
2. CLI arguments as fallback

Key configuration options:

- `OPENFGA_URL` / `--url`: OpenFGA server URL (defaults to http://localhost:8080)
- `OPENFGA_STORE` / `--store`: Store ID
- `OPENFGA_MODEL` / `--model`: Model ID
- `OPENFGA_TOKEN` / `--token`: API token for pre-shared key auth
- `OPENFGA_CLIENT` / `--client`: Client ID for OAuth
- `OPENFGA_SECRET` / `--secret`: Client secret for OAuth
- `OPENFGA_ISSUER` / `--issuer`: OAuth issuer
- `OPENFGA_AUDIENCE` / `--audience`: OAuth audience

## Important Notes

- When addressing PHPStan, Psalm or other PHP linter warnings, always address the underlying issues directly, never use suppression tactics
