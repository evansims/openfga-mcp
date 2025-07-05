<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP</h1>

  <p>Stop writing authorization logic. Start asking questions.</p>
</div>

<p><br /></p>

**A [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that unlocks authorization for agentic AI, and fine-grained vibe coding✨ for humans. Query and administer [OpenFGA](https://openfga.dev) and Auth0 FGA stores.

## Requirements

- PHP 8.3+
- An [OpenFGA server](https://openfga.dev/)

## Features

### Tools

#### Store Management

- `create_store`: Creates a new store. ([create-store](https://openfga.dev/api/service#/Stores/CreateStore))
- `list_stores`: List all stores. ([list-stores](https://openfga.dev/api/service#/Stores/ListStores))
- `get_store_by_id`: Get a store's details by it's ID. ([get-store](https://openfga.dev/api/service#/Stores/GetStore))
- `get_store_by_name`: Get a store's details by it's name. ([get-store](https://openfga.dev/api/service#/Stores/GetStore))
- `get_store_id_by_name`: Get a store's ID by it's name. ([get-store](https://openfga.dev/api/service#/Stores/GetStore))
- `delete_store_by_id`: Delete a store by it's ID. ([delete-store](https://openfga.dev/api/service#/Stores/DeleteStore))

#### Authorization Model Management

- `create_model_using_dsl`: Write an authorization model use OpenFGA's [DSL](https://openfga.dev/docs/configuration-language) syntax. ([write-authorization-model](https://openfga.dev/api/service#/Authorization%20Models/WriteAuthorizationModel))
- `list_models`: List authorization models. ([read-authorization-models](https://openfga.dev/api/service#/Authorization%20Models/ReadAuthorizationModels))
- `get_model`: Get an authorization model. ([get-authorization-model](https://openfga.dev/api/service#/Authorization%20Models/ReadAuthorizationModel))

#### Relationship Tuples Management

- `check`: Check if a something has a relation to an object. ([check](https://openfga.dev/api/service#/Assertions/Check))
- `grant`: Grant permission to a something on an object.
- `revoke`: Revoke permission from something on an object.

- `list_users`: List users that have a given relationship with a given object. ([list-users](https://openfga.dev/api/service#/Assertions/ListUsers))
- `list_objects`: List objects of a type that something has a relation to. ([list-objects](https://openfga.dev/api/service#/Assertions/ListObjects))
- `list_relations`: List relations of a type that something has a relation to. ([list-relations](https://openfga.dev/api/service#/Assertions/ListRelations))

### Resources

### Prompts

## Usage

TODO: Create Docker-based workflow so end users can easily install and configure the OpenFGA MCP Server in their tools with one string. Docker will help us avoid complex installation worries about local PHP versions, cloning repositories, and managing dependencies.

### Configuration

The server accepts the following arguments:

- `--openfga_url`: URL of your OpenFGA server
- `--openfga_store`: ID of the OpenFGA store the MCP server will use
- `--openfga_model`: ID of the OpenFGA authorization model the MCP server will use

For API token authentication:

- `--openfga_token`: API token for use with your OpenFGA server

For Client Credentials authentication:

- `--openfga_client_id`: Client ID for use with your OpenFGA server
- `--openfga_client_secret`: Client secret for use with your OpenFGA server
- `--openfga_api_issuer`: API issuer for use with your OpenFGA server
- `--openfga_api_audience`: API audience for use with your OpenFGA server

### Using with Claude Desktop

TODO

### Using with Cursor

TODO

### Using with Windsurf

TODO

## Contributing

Contributions are welcome—have a look at our [contributing guidelines](.github/CONTRIBUTING.md).
