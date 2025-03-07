# Usage Guide

This guide explains how to use the OpenFGA MCP server.

## Running the MCP Server

### Command Line

```bash
openfga-mcp-server \
  --url "https://localhost:8000" \
  --store "your-store-id"
```

### Additional CLI Options

```bash
# Get help on available options
openfga-mcp-server --help

# Enable verbose logging
openfga-mcp-server --verbose
```

### Using Docker

```bash
# Using the Docker image
docker run -p 8000:8000 \
  -e OPENFGA_API_URL="https://localhost:8000" \
  -e OPENFGA_STORE_ID="your-store-id" \
  ghcr.io/evansims/openfga-mcp-server:latest

# Using Docker Compose
OPENFGA_API_URL="https://localhost:8000" OPENFGA_STORE_ID="your-store-id" docker-compose up
```

## Connecting LLMs to the MCP Server

Connect your LLM application to the MCP server endpoint (default: http://localhost:8090).

### Example with LangChain

```python
from langchain.agents import AgentExecutor, create_openai_tools_agent
from langchain_openai import ChatOpenAI
from langchain.tools import MlcContextToolkit

# Initialize the MCP toolkit
mcp_toolkit = MlcContextToolkit(
    endpoint="http://localhost:8090"
)

# Get the tools from the toolkit
tools = mcp_toolkit.get_tools()

# Create an OpenAI agent with the MCP tools
llm = ChatOpenAI(model="gpt-4-turbo")
agent = create_openai_tools_agent(llm, tools, prompt)
agent_executor = AgentExecutor(agent=agent, tools=tools)

# Run the agent
response = agent_executor.invoke({"input": "Can user123 access document456?"})
print(response["output"])
```

## Available MCP Tools

The OpenFGA MCP server provides the following tools for LLMs:

1. **Check Access**: Determine if a user has a specific permission on a resource
2. **List Relationships**: List all relationships for a user or resource
3. **Create Relationship**: Create a new relationship between a user and a resource
4. **Delete Relationship**: Remove a relationship between a user and a resource
5. **Explain Access**: Get an explanation for why access was granted or denied

## Example Queries

Here are some example queries you can ask an LLM connected to the OpenFGA MCP server:

- "Does Alice have viewer access to the marketing document?"
- "List all documents that Bob can edit."
- "Give Charlie editor access to the sales presentation."
- "Remove Dave's access to the financial report."
- "Why can't Eve access the HR document?"
- "What roles does Frank have in the engineering project?"

## Advanced Configuration

For advanced configuration options, see the [Configuration Reference](configuration.md).
