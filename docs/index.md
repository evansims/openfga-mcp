# OpenFGA MCP

[![CI](https://github.com/evansims/openfga-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/evansims/openfga-mcp/actions/workflows/ci.yml)
[![Conventional Commits](https://img.shields.io/badge/Conventional%20Commits-1.0.0-yellow.svg)](https://conventionalcommits.org)
[![PyPI version](https://badge.fury.io/py/openfga-mcp.svg)](https://badge.fury.io/py/openfga-mcp)
[![uv](https://img.shields.io/badge/uv-package%20manager-blue)](https://github.com/astral-sh/uv)
[![Docker](https://img.shields.io/badge/docker-container-blue)](https://github.com/evansims/openfga-mcp/pkgs/container/openfga-mcp-server)

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans.

Built using the [OpenFGA Python SDK](https://github.com/openfga/python-sdk) and [MCP Python SDK](https://github.com/modelcontextprotocol/python-sdk).

## Overview

OpenFGA MCP provides a bridge between Large Language Models and OpenFGA authorization systems. This allows LLMs to:

1. **Query authorization data**: Check permissions and relationships
2. **Modify authorization rules**: Update access control policies
3. **Explain authorization decisions**: Understand why access was granted or denied
4. **Analyze authorization patterns**: Identify potential security issues

## Features

- **MCP-compatible API**: Follows the Model Context Protocol for seamless LLM integration
- **OpenFGA Integration**: Direct access to OpenFGA stores
- **Secure by Design**: Controlled access to authorization data
- **Extensible**: Easy to add new capabilities and tools

## Getting Started

See the [Installation](installation.md) and [Usage](usage.md) guides to get started.

## API Reference

For detailed API documentation, see the [API Reference](api-reference.md).

## Use Cases

- **Dynamic and Context-Aware Access Control**: LLMs interpret natural language to determine permissions based on context
- **Intelligent Policy Management**: Create or adjust authorization policies through conversational interfaces
- **Contextual and Adaptive Security**: Identify overly permissive access patterns and recommend improvements
- **Explainable Authorization**: Provide clear justifications for access decisions
- **Enhanced Productivity and Automation**: Automate access provisioning based on roles and needs

## Contributing

We welcome contributions! See our [Contributing Guide](contributing.md) for more information.
