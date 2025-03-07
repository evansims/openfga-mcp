from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from dataclasses import dataclass
from importlib.metadata import metadata
from typing import Any

# Simplify imports to avoid unknown symbols
from mcp.server.fastmcp import Context, FastMCP
from openfga_sdk import OpenFgaClient

SERVER_NAME = metadata("openfga-mcp")["name"]

# Create the MCP server
mcp = FastMCP(SERVER_NAME, dependencies=["openfga-sdk"])


@dataclass
class AppContext:
    openfga: OpenFgaClient | None = None


@asynccontextmanager
async def server_lifespan(server: FastMCP) -> AsyncIterator[AppContext]:
    try:
        yield AppContext()

    finally:
        pass


# Resources
@mcp.resource("config://app")
def get_config() -> str:
    """Static configuration data"""
    return "App configuration here"


# Tools
@mcp.tool()
def get_user_permissions(ctx: Context) -> str:
    """Get the permissions for a user"""
    # Replace with actual implementation based on OpenFGA SDK
    # Safely access user_id if available
    user_id = getattr(getattr(ctx, "request_context", None), "user_id", "unknown")
    return f"Permissions for user: {user_id}"


# Prompts
@mcp.prompt()
def review_code(code: str) -> str:
    """Generate a prompt to review code"""
    return f"Please review this code:\n\n{code}"


@mcp.prompt()
def debug_error(error: str) -> list[dict[str, Any]]:
    """Generate a prompt to debug an error"""
    return [
        {"role": "user", "content": "I'm seeing this error:"},
        {"role": "user", "content": error},
        {"role": "assistant", "content": "I'll help debug that. What have you tried so far?"},
    ]


async def serve(url: str | None, store: str | None) -> None:
    """Start the MCP server with the given OpenFGA configuration"""

    configuration = ClientConfiguration(
        api_url=url or "http://localhost:8080",
        store_id=store or "",
    )

    # In a real implementation, we would configure the OpenFGA client here
    # and make it available to the server

    # Start the MCP server using the CLI
    # This function doesn't actually start the server directly
    # The CLI will handle that when the script is run
    pass
