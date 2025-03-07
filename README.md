# openfga-mcp

[![CI](https://github.com/evansims/openfga-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/evansims/openfga-mcp/actions/workflows/ci.yml)
[![Conventional Commits](https://img.shields.io/badge/Conventional%20Commits-1.0.0-yellow.svg)](https://conventionalcommits.org)
[![PyPI version](https://badge.fury.io/py/openfga-mcp.svg)](https://badge.fury.io/py/openfga-mcp)

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans. Take a look at the [use cases](#use-cases) for some inspiration.

Built using the [OpenFGA Python SDK](https://github.com/openfga/python-sdk) and [MCP Python SDK](https://github.com/modelcontextprotocol/python-sdk).

## Requirements

- Python 3.10+
- OpenFGA

## Quick Start

### Installation

> **Note:** This project is currently in early development. The package is not yet available on PyPI. Please install from source until the first official release.

#### Installing from source (recommended for now)

```bash
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Using pip
python -m venv .venv && source .venv/bin/activate && pip install -e .

# Using uv
uv venv .venv && source .venv/bin/activate && uv pip install -e .

# Using Poetry
poetry install && poetry shell
```

#### Package managers (after official release)

```bash
# Using pip
pip install openfga-mcp

# Using uv
uv pip install openfga-mcp

# Using Poetry
poetry add openfga-mcp
```

### Running the MCP server

```bash
openfga-mcp-server \
  --url "https://localhost:8000" \
  --store "your-store-id"
```

#### Additional CLI Options

```bash
# Get help on available options
openfga-mcp-server --help

# Enable verbose logging
openfga-mcp-server --verbose
```

Connect your LLM application to the MCP server endpoint (default: http://localhost:8090)

### Running with Docker

Alternatively, you can run the MCP server using Docker. The project includes a multi-stage Dockerfile that uses `uv` for efficient dependency management.

```bash
# Build the Docker image
docker build -t openfga-mcp-server .

# Run the container
docker run -p 8090:8090 \
  openfga-mcp-server --url "https://localhost:8000" --store "your-store-id"
```

## Development

```bash
# Clone and setup
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Choose your package manager:
# pip
python -m venv .venv && source .venv/bin/activate && pip install -e ".[dev]"

# uv
uv venv .venv && source .venv/bin/activate && uv pip install -e ".[dev]"

# Poetry
poetry install --with dev && poetry shell

# Set up pre-commit hooks (recommended)
pre-commit install
pre-commit install --hook-type commit-msg

# Testing and quality
pytest
ruff check .
pyright
```

Please see the [CONTRIBUTING.md](CONTRIBUTING.md) file for details on how to contribute to this project.

## Use Cases

### 1. Dynamic and Context-Aware Access Control

LLMs interpret natural language to determine permissions based on context.

> **Example:** User asks "Can Alice access Bob's design document?" → LLM queries OpenFGA and explains the decision.

### 2. Intelligent Policy Management

Create or adjust authorization policies through conversational interfaces.

> **Example:** User requests "Allow team leads to review project files in their department during active projects" → LLM creates the appropriate policy.

### 3. Contextual and Adaptive Security

Identify overly permissive access patterns and recommend improvements.

> **Example:** LLM notices "Sales representatives have write access to financial forecasts" and suggests restricting to read-only.

### 4. Explainable Authorization

Provide clear justifications for access decisions.

> **Example:** When asked "Why can't I view this HR file?", LLM explains "Your Engineering role doesn't have access to HR files."

### 5. Enhanced Productivity and Automation

Automate access provisioning based on roles and needs.

> **Example:** When told "A new employee joined marketing", LLM assigns appropriate permissions.

### 6. Policy Debugging and Troubleshooting

Diagnose permissions issues conversationally.

> **Example:** User asks "Why can marketing contractors edit internal documents?" → LLM identifies the relevant policy rule.

### 7. Secure Collaboration

Grant temporary access with precise scope.

> **Example:** User requests "Share the quarterly report with the finance contractor until Friday" → LLM configures temporary access.

### 8. Compliance Management

Ensure compliance with access-control standards.

> **Example:** LLM reports "Compliance check passed: No unauthorized access to sensitive data in the last 30 days."

### 9. User-Friendly Interfaces

Allow non-technical users to request or query access rights.

> **Example:** User requests "Give me edit access to project Alpha" → LLM handles the permission change.

### 10. Predictive Access and Risk Management

Flag potential authorization vulnerabilities proactively.

> **Example:** LLM alerts "This user has attempted multiple unauthorized accesses" and suggests reviewing permissions.

## API Documentation

MCP Resources, Tools, and Prompts documentation coming soon.

## Troubleshooting

**Connection Errors**: Verify your OpenFGA API URL is correct and accessible.

## Contributing

We welcome contributions from the community! Please see our [Contributing Guidelines](CONTRIBUTING.md) for more information on how to get involved.

Before contributing, please review our [Code of Conduct](CODE_OF_CONDUCT.md).

## Security

If you discover a security vulnerability, please follow our [Security Policy](SECURITY.md) for responsible disclosure.

## License

Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
