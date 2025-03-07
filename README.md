# OpenFGA MCP

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans.

Built using the [OpenFGA Python SDK](https://github.com/openfga/python-sdk) and [MCP Python SDK](https://github.com/modelcontextprotocol/python-sdk).

## Quick Start

### Requirements

- Python 3.10+
- OpenFGA

### Installation

```bash
# Using pip/uv
uv pip install openfga-mcp

# From source
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
make setup
source activate_venv.sh
```

### Running

```bash
# Direct CLI usage
openfga-mcp-server --url "https://localhost:8000" --store "your-store-id"

# Using Make
make run

# With Docker
make docker-build
OPENFGA_API_URL="https://localhost:8000" OPENFGA_STORE_ID="your-store-id" make docker-run
```

### Connecting

Connect your LLM application to the MCP server endpoint (default: http://localhost:8090).

Compatible with [MCP clients](https://modelcontextprotocol.io/clients) including Cursor, Windsurf, Cline, Claude Desktop, and Zed.

## Development

```bash
# Setup
make setup
source activate_venv.sh

# Common tasks (all run in virtual environment automatically)
make test        # Run tests
make lint        # Run linting
make type-check  # Run type checking
make check       # Run all checks

# Interactive development
make shell       # Start shell in virtual environment
make repl        # Start Python REPL
make ipython     # Start IPython REPL

# Run a custom command
make in-venv CMD="python -m openfga_mcp version"
```

## Use Cases

1. **Dynamic Access Control**: LLMs interpret natural language to determine permissions based on context
2. **Policy Management**: Create or adjust authorization policies through conversational interfaces
3. **Explainable Authorization**: Provide clear justifications for access decisions
4. **Policy Debugging**: Diagnose permissions issues conversationally
5. **Secure Collaboration**: Grant temporary access with precise scope

## Documentation

For detailed documentation, run:

```bash
make docs-serve
```

## Contributing

See [Contributing Guidelines](CONTRIBUTING.md) for more information.

## License

Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
