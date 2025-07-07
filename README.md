<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP Server</h1>

  <p>Stop writing authorization logic. Start asking questions.</p>
</div>

<p><br /></p>

**Manage and query your OpenFGA server using AI agents and tooling.** Unlock the power of [OpenFGA](https://openfga.dev/) and [Auth0 FGA](https://auth0.com/fine-grained-authorization) inside agentic tooling and intelligent workflows.

## Features

### Tools

#### Store Management

- `create_store`: Creates a new store.
- `list_stores`: List all stores.
- `get_store`: Get a store's details by its ID.
- `delete_store`: Delete a store by its ID.

#### Authorization Model Management

- `create_model`: Use OpenFGA's [DSL](https://openfga.dev/docs/configuration-language) to create an authorization model.
- `list_models`: List authorization models.
- `get_model`: Get an authorization model's details by its ID.
- `verify_model`: Verify a DSL representation of an authorization model.
- `get_model_dsl`: Get the DSL from a specific authorization model from a particular store.

#### Relationship Tuples Management

- `check_permission`: Check if something has a relation to an object. This answers, can (user) do (relation) on (object)?
- `grant_permission`: Grant permission to something on an object by creating a relationship tuple.
- `revoke_permission`: Revoke permission from something on an object by deleting a relationship tuple.

- `list_users`: Return a list of users that have a given relationship with a given object.
- `list_objects`: Return a list of objects of a type that something has a relation to.

## Configuration

The server **requires** the following configuration options:

| Environment Variable  | Default                 | Description                |
| --------------------- | ----------------------- | -------------------------- |
| `OPENFGA_MCP_API_URL` | `http://127.0.0.1:8080` | URL of your OpenFGA server |

The server accepts the following optional configuration options:

| Environment Variable         | Default     | Description                                                                       |
| ---------------------------- | ----------- | --------------------------------------------------------------------------------- |
| `OPENFGA_MCP_TRANSPORT`      | `stdio`     | Transport to use for communication with the MCP server (`stdio` or `http`)        |
| `OPENFGA_MCP_TRANSPORT_HOST` | `127.0.0.1` | The host to bind the MCP server to (only affects HTTP transport)                  |
| `OPENFGA_MCP_TRANSPORT_PORT` | `8080`      | The port to bind the MCP server to (only affects HTTP transport)                  |
| `OPENFGA_MCP_TRANSPORT_JSON` | `false`     | Whether the MCP server should use JSON responses (only affects HTTP transport)    |
| `OPENFGA_MCP_API_READONLY`   | `false`     | Whether the MCP server should be read-only                                        |
| `OPENFGA_MCP_API_RESTRICT`   | `false`     | Whether the MCP server should be restricted to the configured store and model IDs |
| `OPENFGA_MCP_API_STORE`      | `null`      | OpenFGA Store ID the MCP server should use by default                             |
| `OPENFGA_MCP_API_MODEL`      | `null`      | OpenFGA Model ID the MCP server should use by default                             |

### Authentication

By default, the server will try to connect to the OpenFGA server without using authentication.

To use pre-shared key (token) authentication, the server accepts the following configuration options:

| Environment Variable    | Default | Description                                |
| ----------------------- | ------- | ------------------------------------------ |
| `OPENFGA_MCP_API_TOKEN` | `null`  | API token for use with your OpenFGA server |

To use Client Credentials authentication, the server accepts the following configuration options:

| Environment Variable            | Default | Description                                    |
| ------------------------------- | ------- | ---------------------------------------------- |
| `OPENFGA_MCP_API_CLIENT_ID`     | `null`  | Client ID for use with your OpenFGA server     |
| `OPENFGA_MCP_API_CLIENT_SECRET` | `null`  | Client secret for use with your OpenFGA server |
| `OPENFGA_MCP_API_ISSUER`        | `null`  | API issuer for use with your OpenFGA server    |
| `OPENFGA_MCP_API_AUDIENCE`      | `null`  | API audience for use with your OpenFGA server  |

## Installation

### Docker (Recommended)

```bash
docker pull evansims/openfga-mcp:latest
```

### Composer

```bash
composer global require evansims/openfga-mcp
```

## Usage

### Claude Desktop

Using Docker:

```json
{
  "mcpServers": {
    "OpenFGA": {
      "command": "docker",
      "args": [
        "run",
        "--rm",
        "-i",
        "-e",
        "OPENFGA_MCP_API_URL=http://localhost:8080",
        "evansims/openfga-mcp:latest"
      ]
    }
  }
}
```

Using PHP:

```json
{
  "mcpServers": {
    "OpenFGA": {
      "command": "php",
      "args": ["/path/to/vendor/bin/openfga-mcp"],
      "env": {
        "OPENFGA_MCP_API_URL": "http://localhost:8080"
      }
    }
  }
}
```

### Claude Code

### Cursor

### Windsurf

### Warp

### Raycast

## Contributing

Contributions are welcome! Please ensure all tests pass and linters are satisfied before submitting a pull request.
