from contextlib import asynccontextmanager
from dataclasses import dataclass
from importlib.metadata import metadata
from typing import AsyncIterator

from mcp.server.fastmcp import Context, FastMCP, Message, UserMessage, AssistantMessage
from openfga_sdk import Configuration, OpenFgaClient

SERVER_NAME = metadata("openfga-mcp")["name"]


mcp = FastMCP(SERVER_NAME)
mcp = FastMCP(SERVER_NAME, dependencies=["openfga-sdk"])


@dataclass
class AppContext:
    openfga: OpenFgaClient


@asynccontextmanager
async def server_lifespan(server: FastMCP) -> AsyncIterator[AppContext]:
    try:
        openfga = await OpenFgaClient(
            configuration=Configuration(api_url=server),
        )
        yield AppContext(openfga=openfga)

    finally:
        await openfga.close()


mcp = FastMCP(SERVER_NAME, lifespan=server_lifespan)

"""
Resources
Resources are how you expose data to LLMs. They're similar to GET endpoints in a REST API - they provide data but shouldn't perform significant computation or have side effects.
"""


@mcp.resource("config://app")
def get_config() -> str:
    """Static configuration data"""
    return "App configuration here"


# Tools
# Tools let LLMs take actions through your server. Unlike resources, tools are expected to perform computation and have side effects.


@mcp.tool()
def get_user_permissions(ctx: Context) -> str:
    """Get the permissions for a user"""
    openfga: OpenFgaClient = ctx.request_context.lifespan_context["openfga"]
    return openfga.get_user_permissions(ctx.request_context.user_id)


# Prompts
# Prompts are reusable templates that help LLMs interact with your server effectively.


@mcp.prompt()
def review_code(code: str) -> str:
    return f"Please review this code:\n\n{code}"


@mcp.prompt()
def debug_error(error: str) -> list[Message]:
    return [
        UserMessage("I'm seeing this error:"),
        UserMessage(error),
        AssistantMessage("I'll help debug that. What have you tried so far?"),
    ]
