<div align="center">
  <p><a href="https://openfga.dev"><img src=".github/openfga.png" width="100" /></a></p>

  <h1>OpenFGA MCP Server</h1>

  <p>
    <a href="https://codecov.io/gh/evansims/openfga-mcp" target="_blank"><img src="https://codecov.io/gh/evansims/openfga-mcp/graph/badge.svg?token=DG6KWF1EG6" alt="codecov" /></a>
    <a href="https://shepherd.dev/github/evansims/openfga-mcp" target="_blank"><img src="https://shepherd.dev/github/evansims/openfga-mcp/coverage.svg" alt="Psalm Type Coverage" /></a>
    <a href="https://www.bestpractices.dev/projects/10901"><img src="https://www.bestpractices.dev/projects/10901/badge"></a>
  </p>

  <p>Stop writing authorization logic. Start asking questions.</p>
</div>

<p><br /></p>

**Manage and query your OpenFGA server using AI agents and tooling.** Unlock the power of [OpenFGA](https://openfga.dev/) and [Auth0 FGA](https://auth0.com/fine-grained-authorization) inside agentic tooling and intelligent workflows.

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

The server should work with any MCP client, but has been tested with [Visual Studio Code](https://code.visualstudio.com), [Docker](https://www.docker.com), [Claude Desktop](https://claude.ai/download), [Claude Code](https://www.anthropic.com/claude-code), [Cursor](https://cursor.sh), [Windsurf](https://windsurf.com), [Warp](https://warp.dev) and [Raycast](https://raycast.com).

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

- `openfga://stores`: List all available OpenFGA stores.

### Resource Templates

#### Store Templates

- `openfga://store/{storeId}`: Get detailed information about a specific store.
- `openfga://store/{storeId}/models`: List all authorization models in a store.

#### Model Templates

- `openfga://store/{storeId}/model/{modelId}`: Get detailed information about a specific authorization model.
- `openfga://store/{storeId}/model/latest`: Get the latest authorization model in a store.

#### Relationship Templates

- `openfga://store/{storeId}/users`: List all users in a store (extracted from relationship tuples).
- `openfga://store/{storeId}/objects`: List all objects in a store (extracted from relationship tuples).
- `openfga://store/{storeId}/relationships`: List all relationship tuples in a store.
- `openfga://store/{storeId}/check?user={user}&relation={relation}&object={object}`: Check if a user has a specific permission on an object.
- `openfga://store/{storeId}/expand?object={object}&relation={relation}`: Expand all users who have a specific relation to an object.

### Prompts

#### Authorization Model Design

- `design_model_for_domain`: Generate guidance for designing OpenFGA authorization models for specific domains (e.g., document management, e-commerce, healthcare).
- `convert_rbac_to_rebac`: Provide step-by-step guidance for converting traditional RBAC systems to OpenFGA's ReBAC patterns.
- `model_hierarchical_relationships`: Help design complex hierarchical relationships and inheritance patterns.
- `optimize_model_structure`: Analyze and optimize existing authorization models for performance and maintainability.

#### Relationship Troubleshooting

- `debug_permission_denial`: Generate systematic debugging approaches for permission denials and access issues.
- `analyze_permission_inheritance`: Provide analysis of permission inheritance paths and relationship chains.
- `troubleshoot_unexpected_access`: Help investigate unexpected permission grants and security issues.
- `optimize_relationship_queries`: Offer guidance for optimizing relationship query performance.

#### Security Guidance

- `security_review_model`: Conduct comprehensive security reviews of authorization models for compliance.
- `implement_least_privilege`: Provide guidance for implementing principle of least privilege in authorization design.
- `secure_delegation_patterns`: Help design secure delegation patterns for temporary and permanent access.
- `audit_friendly_patterns`: Generate audit-friendly authorization patterns for regulatory compliance.

### Completion Providers

The server provides intelligent auto-completion suggestions for resource template parameters and prompt arguments. This helps MCP clients offer a better user experience by suggesting valid options as users type.

#### Dynamic Completion Providers

- `StoreIdCompletionProvider`: Provides auto-completion for OpenFGA store IDs by fetching available stores from your OpenFGA server.
- `ModelIdCompletionProvider`: Suggests model IDs from a specific store, including the 'latest' option for accessing the most recent model.
- `RelationCompletionProvider`: Offers relation names extracted from authorization models (e.g., 'viewer', 'editor', 'owner').
- `UserCompletionProvider`: Suggests user identifiers extracted from existing relationship tuples.
- `ObjectCompletionProvider`: Provides object identifiers extracted from existing relationship tuples.

#### Static Completion Providers

The server also includes enum-based completion providers for common parameters:

- **Security & Compliance**: Security levels (low, medium, high, critical), compliance frameworks (SOC2, HIPAA, PCI-DSS, GDPR), audit frequencies.
- **System Classification**: System types (microservices, monolith, hybrid), criticality levels, access patterns.
- **Authorization Patterns**: Delegation types (temporary, permanent, conditional), complexity levels, query types, risk levels.

---

- Want to help? Get started with our [contributors guide](./.github/CONTRIBUTING.md).
- Licensed under the [Apache 2.0 License](./LICENSE).
