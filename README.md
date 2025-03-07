# OpenFGA MCP

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans.

Built using the [OpenFGA Python SDK](https://github.com/openfga/python-sdk) and [MCP Python SDK](https://github.com/modelcontextprotocol/python-sdk).

## Overview

OpenFGA MCP provides a bridge between Large Language Models and OpenFGA, allowing for the creation of agentic AI systems that can:

- Query permissions and relationships
- Update access control policies
- Explain authorization decisions
- Identify potential security issues

## Quick Start

### Requirements

- Python 3.10+
- OpenFGA

### Installation

```bash
# Using uv (recommended)
uv pip install openfga-mcp

# From source (for development)
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
make setup
source activate_venv.sh  # Activates the virtual environment
```

### Running the MCP Server

```bash
# Basic usage
openfga-mcp-server --url "https://localhost:8000" --store "your-store-id"

# Using the Makefile (automatically uses the virtual environment)
make run

# With Docker
make docker-build
OPENFGA_API_URL="https://localhost:8000" OPENFGA_STORE_ID="your-store-id" make docker-run
```

### Connecting to a Client

Connect your LLM application to the MCP server endpoint (default: http://localhost:8090).

MCP is supported by [many clients](https://modelcontextprotocol.io/clients), including:

- [Cursor](https://www.cursor.com/cursor)
- [Windsurf](https://windsurf.dev/)
- [Cline](https://cline.bot/) (for VSCode)
- [Claude Desktop](https://docs.anthropic.com/en/docs/claude-desktop/mcp)
- [Zed](https://zed.dev/)

## Development

```bash
# Setup development environment
make setup
source activate_venv.sh  # Activates the virtual environment

# Run tests and checks
make test
make lint
make type-check

# Run all checks
make check

# Run a custom command in the virtual environment
make in-venv CMD="python -m openfga_mcp version"
```

```bash
# Get help
openfga-mcp-server --help

# Run with verbose logging
openfga-mcp-server -vv --url "https://localhost:8000" --store "your-store-id"
```

## Use Cases

### 1. Dynamic and Context-Aware Access Control

LLMs interpret natural language to determine permissions based on context.

> **Example:** User asks "Can Alice access Bob's design document?" → LLM queries OpenFGA and explains the decision.

### 2. Intelligent Policy Management

Create or adjust authorization policies through conversational interfaces.

> **Example:** User requests "Allow team leads to review project files in their department" → LLM creates the appropriate policy.

### 3. Explainable Authorization

Provide clear justifications for access decisions.

> **Example:** When asked "Why can't I view this HR file?", LLM explains "Your Engineering role doesn't have access to HR files."

### 4. Policy Debugging and Troubleshooting

Diagnose permissions issues conversationally.

> **Example:** User asks "Why can marketing contractors edit internal documents?" → LLM identifies the relevant policy rule.

### 5. Secure Collaboration

Grant temporary access with precise scope.

> **Example:** User requests "Share the quarterly report with the finance contractor until Friday" → LLM configures temporary access.

## Documentation

For detailed documentation, including:

- Complete installation instructions
- Usage examples with LLMs
- API reference
- More use cases and examples

Browse [the documentation](./docs) or run:

```bash
make docs-serve
```

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for more information.

## License

Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
