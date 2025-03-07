# Usage Guide

## Running the Server

```bash
# Basic usage
openfga-mcp-server --url "https://localhost:8000" --store "your-store-id"

# With options
openfga-mcp-server --verbose

# Help
openfga-mcp-server --help
```

## Docker

```bash
# Docker run
docker run -p 8000:8000 \
  -e OPENFGA_API_URL="https://localhost:8000" \
  -e OPENFGA_STORE_ID="your-store-id" \
  ghcr.io/evansims/openfga-mcp-server:latest

# Docker Compose
OPENFGA_API_URL="https://localhost:8000" OPENFGA_STORE_ID="your-store-id" docker-compose up
```

## LLM Integration

Connect your LLM to the MCP server endpoint (default: http://localhost:8090).

```python
from langchain.agents import AgentExecutor, create_openai_tools_agent
from langchain_openai import ChatOpenAI
from langchain.tools import MlcContextToolkit

# Initialize MCP toolkit
mcp_toolkit = MlcContextToolkit(endpoint="http://localhost:8090")
tools = mcp_toolkit.get_tools()

# Create agent
llm = ChatOpenAI(model="gpt-4-turbo")
agent = create_openai_tools_agent(llm, tools, prompt)
agent_executor = AgentExecutor(agent=agent, tools=tools)

# Run query
response = agent_executor.invoke({"input": "Can user123 access document456?"})
print(response["output"])
```

## Available Tools

1. **Check Access**: Determine if a user has permission on a resource
2. **List Relationships**: List all relationships for a user or resource
3. **Create Relationship**: Create a new relationship
4. **Delete Relationship**: Remove a relationship
5. **Explain Access**: Get an explanation for access decisions

## Example Queries

- "Does Alice have viewer access to the marketing document?"
- "List all documents that Bob can edit."
- "Give Charlie editor access to the sales presentation."
- "Remove Dave's access to the financial report."
- "Why can't Eve access the HR document?"
