# Installation

This guide will help you install and set up the OpenFGA MCP server.

## Requirements

- Python 3.10+
- OpenFGA

## Installation Methods

### Using pip (recommended)

```bash
# Using uv (recommended)
uv pip install openfga-mcp

# Using pip
pip install openfga-mcp

# Using Poetry
poetry add openfga-mcp
```

### From source

```bash
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Using uv (recommended)
uv venv .venv && source .venv/bin/activate && uv pip install -e .

# Using pip
python -m venv .venv && source .venv/bin/activate && pip install -e .

# Using Poetry
poetry install && poetry shell
```

### Using Docker

```bash
# Pull the image
docker pull ghcr.io/evansims/openfga-mcp-server:latest

# Or build locally
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
docker build -t openfga-mcp-server .
```

## Development Setup

For development, you'll want to install the package with development dependencies:

```bash
# Clone and setup
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Using Makefile (recommended)
make setup
source .venv/bin/activate

# Or choose your package manager:
# uv (recommended for speed)
uv venv .venv && source .venv/bin/activate && uv pip install -e ".[dev,test,docs]"

# pip
python -m venv .venv && source .venv/bin/activate && pip install -e ".[dev,test,docs]"

# Poetry
poetry install --with dev && poetry shell

# Set up pre-commit hooks (recommended)
pre-commit install
pre-commit install --hook-type commit-msg
```

## Verifying Installation

To verify that the installation was successful, run:

```bash
openfga-mcp-server --help
```

You should see the help message for the OpenFGA MCP server.
