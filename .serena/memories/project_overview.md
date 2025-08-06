# OpenFGA MCP Server - Project Overview

## Purpose
This is a PHP implementation of a Model Context Protocol (MCP) server for OpenFGA, providing AI agents with tools to manage and query OpenFGA authorization servers. It enables AI-powered authorization management for OpenFGA and Auth0 FGA.

## Key Use Cases
- **Plan & Design**: Design efficient authorization models using best practice patterns
- **Generate Code**: Generate accurate SDK integrations with comprehensive documentation context
- **Manage Instances**: Query and control live OpenFGA servers through AI agents

## Operating Modes

### Online Mode (Full Features)
When `OPENFGA_MCP_API_URL` is configured, the server operates with full functionality:
- All Tools work (create/manage stores, models, relationships)
- All Resources provide live data from OpenFGA
- Dynamic Completions fetch data from the server
- Prompts work as normal

### Offline Mode (Planning & Coding Only)
When no OpenFGA configuration is provided:
- Tools return error messages guiding users to configure OpenFGA
- Resources return error responses with helpful messages
- Completions return static defaults
- **Prompts work normally** - key feature for design and planning

## Security Model
- **Default**: Write operations are disabled (`OPENFGA_MCP_API_WRITEABLE=false`)
- **To Enable**: Must explicitly set `OPENFGA_MCP_API_WRITEABLE=true`
- Prevents accidental destructive operations on OpenFGA instances
- Read operations always allowed when connected

## MCP Components
- **Tools**: Executable functions for managing stores, models, and relationships
- **Resources**: Read-only data sources for accessing OpenFGA information
- **Resource Templates**: Parameterized blueprints for generating resources dynamically
- **Prompts**: Reusable prompt templates for AI guidance

## Documentation System
Comprehensive documentation system exposing OpenFGA SDK documentation as MCP resources and tools:
- Multi-SDK coverage (PHP, Go, Python, Java, .NET, JavaScript, Laravel)
- Smart chunking and search capabilities
- Code example extraction
- Offline support

## Debug Logging
- Enabled by default (disable with `OPENFGA_MCP_DEBUG=false`)
- Logs all MCP protocol interactions to `logs/mcp-debug.log`
- Includes requests, responses, errors with full context