# OpenFGA MCP Server

[![smithery badge](https://smithery.ai/badge/@evansims/openfga-mcp)](https://smithery.ai/server/@evansims/openfga-mcp)

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans.

## Requirements

- Python 3.12+
- An [OpenFGA server](https://openfga.dev/)

## Features

### Tools

- `check`: Check if a user has a relation to an object
- `list_objects`: List objects of a type that a user has a relation to
- `list_relations`: List relations for which a user has a relation to an object
- `list_users`: List users that have a given relationship with a given object

### Resources

### Prompts

## Usage

We recommend running the server using [UVX](https://docs.astral.sh/uv/guides/tools/#running-tools):

```bash
uvx openfga-mcp@latest
```

### Installing via Smithery

To install OpenFGA MCP Server for Claude Desktop automatically via [Smithery](https://smithery.ai/server/@evansims/openfga-mcp):

```bash
npx -y @smithery/cli install @evansims/openfga-mcp --client claude
```

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

For example:

```bash
uvx openfga-mcp@latest \
  --openfga_url="http://127.0.0.1:8080" \
  --openfga_store="your-store-id" \
  --openfga_model="your-model-id"
```

### Using with Claude Desktop

To configure Claude to use the server, add the following to your Claude config:

```json
{
  "mcpServers": {
    "openfga-mcp": {
      "command": "uvx",
      "args": ["openfga-mcp@latest"]
    }
  }
}
```

- You may need to specify the full path to your `uvx` executable. Use `which uvx` to find it.
- You must restart Claude after updating the configuration.

### Using with Raycast

### Using with Cursor

### Using with Windsurf

## Development

To setup your development environment, run:

```bash
make setup
```

To run the development server:

```bash
make run \
  --openfga_url="http://127.0.0.1:8080" \
  --openfga_store="your-store-id" \
  --openfga_model="your-model-id"
```

To run the development server with the MCP Inspector:

```bash
make dev
```

## License

Apache 2.0
