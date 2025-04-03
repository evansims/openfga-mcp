# OpenFGA MCP Server

An experimental [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server that enables Large Language Models (LLMs) to read, search, and manipulate [OpenFGA](https://openfga.dev) stores. Unlocks authorization for agentic AI, and fine-grained [vibe coding](https://en.wikipedia.org/wiki/Vibe_coding)✨ for humans.

## Requirements

- Python 3.12+
- An [OpenFGA server](https://openfga.dev/)

## Features

### Tools

- `check`: Check if a user has a relation to an object
- `list_objects`: List objects of a type that a user has a relation to
- `write_tuples`: Write tuples to the OpenFGA store
- `read_tuples`: Read tuples from the OpenFGA store
- `get_authorization_model`: Get the current authorization model

### Resources

### Prompts

## Usage

We recommend running the server using [UVX](https://docs.astral.sh/uv/guides/tools/#running-tools):

```bash
uvx openfga-mcp@latest
```

### Configuration

The server accepts the following arguments:

- `--openfga_env`: Fallback to using environment variables
- `--openfga_url`: URL of your OpenFGA server
- `--openfga_store`: ID of the store the MCP server will use
- `--openfga_model`: ID of the authorization model the MCP server will use

For example:

```bash
uvx openfga-mcp@latest \
  --openfga_url="http://127.0.0.1:8000" \
  --openfga_store="your-store-id" \
  --openfga_model="your-model-id"
```

If the `--openfga_env` flag is passed, the server will fallback to using the following environment variables:

- `OPENFGA_API_URL` - The URL of your OpenFGA server
- `OPENFGA_STORE_ID` - The ID of the store you wish to use
- `OPENFGA_MODEL_ID` - The ID of the authorization model you wish to use

### Using with Claude Desktop

To configure Claude to use this server, add the following to your Claude config:

```json
{
    "mcpServers": {
        "openfga-mcp": {
            "command": "uvx",
            "args": [
                "openfga-mcp@latest",
            ]
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
uv sync
```

To run the development server:

```bash
uv run openfga-mcp
```

## License

Apache 2.0
