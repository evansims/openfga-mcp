<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP Server</h1>

  <p>
    <a href="https://codecov.io/gh/evansims/openfga-mcp" target="_blank"><img src="https://codecov.io/gh/evansims/openfga-mcp/graph/badge.svg?token=DG6KWF1EG6" alt="codecov" /></a>
    <a href="https://shepherd.dev/github/evansims/openfga-mcp" target="_blank"><img src="https://shepherd.dev/github/evansims/openfga-mcp/coverage.svg" alt="Psalm Type Coverage" /></a>
    <a href="https://www.bestpractices.dev/projects/10666"><img src="https://www.bestpractices.dev/projects/10666/badge"></a>
  </p>

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

### Resources

#### Store Resources

- `openfga://stores`: List all available OpenFGA stores.
- `openfga://store/{storeId}`: Get detailed information about a specific store.
- `openfga://store/{storeId}/models`: List all authorization models in a store.

#### Model Resources

- `openfga://store/{storeId}/model/{modelId}`: Get detailed information about a specific authorization model.
- `openfga://store/{storeId}/model/latest`: Get the latest authorization model in a store.

#### Relationship Resources

- `openfga://store/{storeId}/users`: List all users in a store (extracted from relationship tuples).
- `openfga://store/{storeId}/objects`: List all objects in a store (extracted from relationship tuples).
- `openfga://store/{storeId}/relationships`: List all relationship tuples in a store.

### Resource Templates

#### Permission Checking

- `openfga://store/{storeId}/check?user={user}&relation={relation}&object={object}`: Check if a user has a specific permission on an object.
- `openfga://store/{storeId}/expand?object={object}&relation={relation}`: Expand all users who have a specific relation to an object.

## Usage

Configure your MCP client to use the server's Docker image:

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

The server should work with any MCP client, but has been tested with Claude Desktop, Claude Code, Cursor, Windsurf, Warp and Raycast.

## Configuration

The server supports the following configuration options:

| Environment Variable         | Default                 | Description                                                                                           |
| ---------------------------- | ----------------------- | ----------------------------------------------------------------------------------------------------- |
| `OPENFGA_MCP_API_URL`        | `http://127.0.0.1:8080` | URL of your OpenFGA server. Note: the Docker image defaults to `http://host.docker.internal:8080`     |
| `OPENFGA_MCP_TRANSPORT`      | `stdio`                 | Transport to use for communication with the MCP server (`stdio` or `http`)                            |
| `OPENFGA_MCP_TRANSPORT_HOST` | `127.0.0.1`             | The host to bind the MCP server to (only affects HTTP transport)                                      |
| `OPENFGA_MCP_TRANSPORT_PORT` | `8080`                  | The port to bind the MCP server to (only affects HTTP transport)                                      |
| `OPENFGA_MCP_TRANSPORT_JSON` | `false`                 | Enables JSON responses (only affects HTTP transport)                                                  |
| `OPENFGA_MCP_API_READONLY`   | `false`                 | Disable write operations (create, update, delete)                                                     |
| `OPENFGA_MCP_API_RESTRICT`   | `false`                 | Restrict the MCP server to ONLY use the configured OPENFGA_MCP_API_STORE and/or OPENFGA_MCP_API_MODEL |
| `OPENFGA_MCP_API_STORE`      | `null`                  | OpenFGA Store ID the MCP server should use by default                                                 |
| `OPENFGA_MCP_API_MODEL`      | `null`                  | OpenFGA Model ID the MCP server should use by default                                                 |

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

## Future Development Goals

- [x] Add MCP resources<br />
      Read-only data sources the AI can access for information. They provide content rather than perform actions. A resource might be a file, database query result, or API response that gives the AI context or data to work with.
- [x] Add MCP resource templates<br />
      Parameterized blueprints for generating resources dynamically. Instead of static resources, templates let you create resources on-demand based on input parameters. For example, a template might generate a user profile resource when given a user ID, or create a report resource based on date ranges.
- [ ] Add MCP prompts<br />
      Reusable prompt templates that can be invoked with parameters to generate structured prompts for the AI. They're essentially parameterized instructions or context that help shape how the AI approaches specific tasks.
- [ ] Add MCP completion provider<br />
      Enable MCP clients to offer auto-completion suggestions in their user interfaces. They are specifically designed for Resource Templates and Prompts to help users discover available options for dynamic parts like template variables or prompt arguments.

## Contributing

Contributions are welcome! Please ensure all tests pass and linters are satisfied before submitting a pull request.
