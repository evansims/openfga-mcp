<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP Server</h1>

  <p>
    <a href="https://codecov.io/gh/evansims/openfga-mcp" target="_blank"><img src="https://codecov.io/gh/evansims/openfga-mcp/graph/badge.svg?token=DG6KWF1EG6" alt="codecov" /></a>
    <a href="https://shepherd.dev/github/evansims/openfga-mcp" target="_blank"><img src="https://shepherd.dev/github/evansims/openfga-mcp/coverage.svg" alt="Psalm Type Coverage" /></a>
    <a href="https://www.bestpractices.dev/projects/10901"><img src="https://www.bestpractices.dev/projects/10901/badge"></a>
  </p>

  <p>AI-powered authorization management for OpenFGA</p>
</div>

<p><br /></p>

Connect [OpenFGA](https://openfga.dev/) and [Auth0 FGA](https://auth0.com/fine-grained-authorization) to AI agents via the Model Context Protocol.

## Use Cases

- **Plan & Design** - Design efficient authorization model using best practice patterns
- **Generate Code** - Generate accurate SDK integrations with comprehensive documentation context
- **Manage Instances** - Query and control live OpenFGA servers through AI agents

## Quick Start

### Offline Mode (Default)

Design models and generate code without a server:

```json
{
  "mcpServers": {
    "OpenFGA": {
      "command": "docker",
      "args": [
        "run",
        "--rm",
        "-i",
        "--pull=always",
        "evansims/openfga-mcp:latest"
      ]
    }
  }
}
```

### Online Mode

Connect to OpenFGA for full management capabilities:

```json
{
  "mcpServers": {
    "OpenFGA": {
      "command": "docker",
      "args": [
        "run",
        "--rm",
        "-i",
        "--pull=always",
        "-e",
        "OPENFGA_MCP_API_URL=http://host.docker.internal:8080",
        "evansims/openfga-mcp:latest"
      ]
    }
  }
}
```

> **Safety:** Write operations are disabled by default. Set `OPENFGA_MCP_API_WRITEABLE=true` to enable.

> **Docker Networking:** For your `OPENFGA_MCP_API_URL` use `host.docker.internal` when running OpenFGA on your local machine, container names for Docker networks, or full URLs for remote instances.

Works with [Claude Desktop](https://claude.ai/download), [Claude Code](https://www.anthropic.com/claude-code), [Cursor](https://cursor.sh), [Windsurf](https://windsurf.com), [Zed](https://zed.dev), and other MCP clients.

## Configuration

### MCP Transport

| Variable                          | Default     | Description                                                                     |
| --------------------------------- | ----------- | ------------------------------------------------------------------------------- |
| `OPENFGA_MCP_TRANSPORT`           | `stdio`     | Supports `stdio` or `http` (Streamable HTTP.)                                   |
| `OPENFGA_MCP_TRANSPORT_HOST`      | `127.0.0.1` | IP to listen for connections on. Only applicable when using `http` transport.   |
| `OPENFGA_MCP_TRANSPORT_PORT`      | `9090`      | Port to listen for connections on. Only applicable when using `http` transport. |
| `OPENFGA_MCP_TRANSPORT_SSE`       | `true`      | Enables Server-Sent Events (SSE) streams for responses.                         |
| `OPENFGA_MCP_TRANSPORT_STATELESS` | `false`     | Enables stateless mode for session-less clients.                                |

### OpenFGA

| Variable                    | Default | Description                                         |
| --------------------------- | ------- | --------------------------------------------------- |
| `OPENFGA_MCP_API_URL`       |         | OpenFGA server URL                                  |
| `OPENFGA_MCP_API_WRITEABLE` | `false` | Enables write operations                            |
| `OPENFGA_MCP_API_STORE`     |         | Default requests to a specific store ID             |
| `OPENFGA_MCP_API_MODEL`     |         | Default requests to a specific model ID             |
| `OPENFGA_MCP_API_RESTRICT`  | `false` | Restrict requests to configured default store/model |

### OpenFGA Authentication

| Authentication     | Variable                        | Default | Description   |
| ------------------ | ------------------------------- | ------- | ------------- |
| Pre-Shared Keys    | `OPENFGA_MCP_API_TOKEN`         |         | API Token     |
| Client Credentials | `OPENFGA_MCP_API_CLIENT_ID`     |         | Client ID     |
|                    | `OPENFGA_MCP_API_CLIENT_SECRET` |         | Client Secret |
|                    | `OPENFGA_MCP_API_ISSUER`        |         | Token Issuer  |
|                    | `OPENFGA_MCP_API_AUDIENCE`      |         | API Audience  |

See [`docker-compose.example.yml`](docker-compose.example.yml) for complete examples.

## Features

### Management Tools

- **Stores**: Create, list, get, delete stores
- **Models**: Create models with [DSL](https://openfga.dev/docs/configuration-language), list, get, verify
- **Permissions**: Check, grant, revoke permissions; query users and objects

### SDK Documentation

Comprehensive documentation for accurate code generation:

- All OpenFGA SDKs (PHP, Go, Python, Java, .NET, JavaScript, Laravel)
- Class and method documentation with code examples
- Advanced search with language filtering

### AI Prompts

**Design & Planning**

- Domain-specific model design
- RBAC to ReBAC migration
- Hierarchical relationships
- Performance optimization

**Implementation**

- Step-by-step model creation
- Relationship patterns
- Test generation
- Security patterns

**Troubleshooting**

- Permission debugging
- Security audits
- Least privilege implementation

### Resources & URIs

- `openfga://stores` - List stores
- `openfga://store/{id}/model/{modelId}` - Model details
- `openfga://docs/{sdk}/class/{className}` - SDK documentation
- `openfga://docs/search/{query}` - Search documentation

### Smart Completions

Auto-completion for store IDs, model IDs, relations, users, and objects when connected.

---

- [Contributing](./.github/CONTRIBUTING.md) | [Apache 2.0 License](./LICENSE)
