<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP</h1>

  <p>Stop writing authorization logic. Start asking questions.</p>
</div>

<p><br /></p>

**Manage and query your [OpenFGA](https://openfga.dev/) server using AI agents and tooling.** An MCP server unlocking the power of [OpenFGA](https://openfga.dev/) and [Auth0 FGA](https://auth0.com/fine-grained-authorization) inside your intelligent IDEs, terminals, and workflows.

- **Eloquent Integration** - Authorization methods on your models
- **Middleware Protection** - Secure routes with permission checks
- **Blade Directives** - Show/hide UI based on permissions
- **Testing Utilities** - Fake permissions in your tests
- **Performance Optimized** - Built-in caching and batch operations
- **Queue Support** - Async permission operations
- **Multi-tenancy Ready** - Multiple stores and connections
- **Type Safe** - PHP 8.3+ with strict typing and comprehensive generics
- **Developer Friendly** - Enhanced IDE support with detailed PHPDoc annotations

## Features

### Tools

#### Store Management

- `create_store`: Creates a new store. ([create-store](https://openfga.dev/api/service#/Stores/CreateStore))
- `list_stores`: List all stores. ([list-stores](https://openfga.dev/api/service#/Stores/ListStores))
- `get_store`: Get a store's details by it's ID. ([get-store](https://openfga.dev/api/service#/Stores/GetStore))
- `delete_store`: Delete a store by it's ID. ([delete-store](https://openfga.dev/api/service#/Stores/DeleteStore))

#### Authorization Model Management

- `create_model`: Write an authorization model use OpenFGA's [DSL](https://openfga.dev/docs/configuration-language) syntax. ([write-authorization-model](https://openfga.dev/api/service#/Authorization%20Models/WriteAuthorizationModel))
- `verify_model`: Verify a DSL representation of an authorization model.
- `list_models`: List authorization models. ([read-authorization-models](https://openfga.dev/api/service#/Authorization%20Models/ReadAuthorizationModels))
- `get_model`: Get an authorization model. ([get-authorization-model](https://openfga.dev/api/service#/Authorization%20Models/ReadAuthorizationModel))
- `get_dsl_from_model`: Get the DSL from an authorization model. ([get-authorization-model](https://openfga.dev/api/service#/Authorization%20Models/ReadAuthorizationModel))

#### Relationship Tuples Management

- `check_permission`: Check if something has a relation to an object. ([check](https://openfga.dev/api/service#/Assertions/Check))
- `grant_permission`: Grant permission to something on an object.
- `revoke_permission`: Revoke permission from something on an object.

- `list_users`: List users that have a given relationship with a given object. ([list-users](https://openfga.dev/api/service#/Assertions/ListUsers))
- `list_objects`: List objects of a type that something has a relation to. ([list-objects](https://openfga.dev/api/service#/Assertions/ListObjects))

### Resources

### Prompts

### Sampling

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

## Usage

### Composer

```json
{
  "mcpServers": {
    "OpenFGA": {
      "command": "php",
      "args": ["/absolute/path/to/your/server.php"],
      "env": {
        "OPENFGA_MCP_TRANSPORT": "stdio",
        "OPENFGA_MCP_API_URL": "http://localhost:8080"
      }
    }
  }
}
```

### Claude Code Usage

### Claude Desktop Usage

```json
{
  "mcpServers": {
    "OpenFGA": {
      "type": "stdio",
      "command": "php",
      "args": ["-y", "openfga-mcp"],
      "env": {
        "OPENFGA_MCP_API_URL": "http://localhost:8080",
        "OPENFGA_MCP_API_CLIENT_ID": "your-openfga-client-id",
        "OPENFGA_MCP_API_CLIENT_SECRET": "your-openfga-client-secret"
      }
    }
  }
}
```

### Cursor Usage

TODO

### Windsurf Usage

TODO

### Warp Usage

TODO

## Contributing

Contributions are welcome—have a look at our [contributing guidelines](.github/CONTRIBUTING.md).
