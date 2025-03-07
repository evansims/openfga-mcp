# Installation

## Requirements

- Python 3.10+
- OpenFGA

## Package Installation

```bash
# Using pip/uv
uv pip install openfga-mcp
pip install openfga-mcp

# Using Poetry
poetry add openfga-mcp
```

## From Source

```bash
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
make setup
source activate_venv.sh
```

## Docker

```bash
# Pull image
docker pull ghcr.io/evansims/openfga-mcp-server:latest

# Or build locally
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
docker build -t openfga-mcp-server .
```

## Development Setup

```bash
# Clone and setup with Make (recommended)
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
make setup
source activate_venv.sh

# Or manually with uv/pip
uv venv .venv && source .venv/bin/activate && uv pip install -e ".[dev,test,docs]"
pre-commit install && pre-commit install --hook-type commit-msg
```

## Verify Installation

```bash
openfga-mcp-server --help
```
