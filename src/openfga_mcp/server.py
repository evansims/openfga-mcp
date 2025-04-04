from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from dataclasses import dataclass

import uvicorn
from mcp.server.fastmcp import Context, FastMCP
from openfga_sdk import FgaObject, OpenFgaClient
from openfga_sdk.client.client import ClientListObjectsRequest, ClientListRelationsRequest, ClientListUsersRequest
from openfga_sdk.client.models.check_request import ClientCheckRequest

from openfga_mcp.openfga import OpenFga
from openfga_mcp.sse import create_starlette_app


@dataclass
class ServerContext:
    openfga: OpenFga


@asynccontextmanager
async def openfga_mcp_lifespan(server: FastMCP) -> AsyncIterator[ServerContext]:  # noqa: ARG001
    """Get OpenFga instance for use in the MCP server."""
    openfga = OpenFga()

    try:
        yield ServerContext(openfga)
    finally:
        await openfga.close()


mcp = FastMCP("openfga-mcp", lifespan=openfga_mcp_lifespan)


@mcp.tool()
async def check(
    ctx: Context,
    user: str,
    relation: str,
    object: str,
) -> str:
    """Check if a user is authorized to access an object.

    Args:
        user: User ID
        relation: Relation
        object: Object ID

    Returns:
        A formatted string containing the result of the authorization check.
    """
    client = await get_client(ctx)

    # Check if the user has the relation to the object
    try:
        body = ClientCheckRequest(
            user=user,
            relation=relation,
            object=object,
        )

        response = await client.check(body)

        if response.allowed:
            return f"{user} has the relation {relation} to {object}"
        else:
            return f"{user} does not have the relation {relation} to {object}"

    except Exception as e:
        return f"Error checking relation: {e!s}"


@mcp.tool()
async def list_objects(
    ctx: Context,
    user: str,
    relation: str,
    type: str,
) -> str:
    """Get all objects of the given type that the user has a relation with.

    Args:
        user: User ID
        relation: Relation
        type: Type

    Returns:
        A formatted string containing the result of the authorization check.
    """
    client = await get_client(ctx)

    # Get all objects of the given type that the user has a relation with
    try:
        body = ClientListObjectsRequest(
            user=user,
            relation=relation,
            type=type,
        )

        response = await client.list_objects(body)
        response = ", ".join(response.objects)

        return f"{user} has a {relation} relationship with {response}"

    except Exception as e:
        return f"Error listing related objects: {e!s}"


@mcp.tool()
async def list_relations(
    ctx: Context,
    user: str,
    relations: str,
    object: str,
) -> str:
    """Get all relations for which the user has a relationship with the object.

    Args:
        user: User ID
        relations: Comma-separated list of relations
        object: Object

    Returns:
        A list of relations for which the specifieduser has a relationship with the object.
    """
    client = await get_client(ctx)

    # Get all relations for which the user has a relationship with the object
    try:
        body = ClientListRelationsRequest(
            user=user,
            relations=relations.split(","),
            object=object,
        )

        response = await client.list_relations(body)
        response = ", ".join(response)

        return f"{user} has the {response} relationships with {object}"

    except Exception as e:
        return f"Error listing relations: {e!s}"


@mcp.tool()
async def list_users(
    ctx: Context,
    object: str,
    type: str,
    relation: str,
) -> str:
    """Get all users that have a given relationship with a given object.

    Args:
        object: Object
        type: Object Type
        relation: Relation

    Returns:
        A list of users that have the given relationship with the given object.
    """
    client = await get_client(ctx)

    # Get all relations for which the user has a relationship with the object
    try:
        body = ClientListUsersRequest(
            object=FgaObject(type=type, id=object),
            relation=relation,
        )

        response = await client.list_users(body)

        if response is not None and response.users is not None:
            response = [user["object"]["id"] for user in response.users]
            response = ", ".join(response)

            return f"{response} have the {relation} relationship with {object}"
        else:
            return f"No users found with the {relation} relationship with {object}"

    except Exception as e:
        return f"Error listing relations: {e!s}"


async def get_client(ctx: Context) -> OpenFgaClient:
    context: ServerContext = ctx.request_context.lifespan_context
    openfga: OpenFga = context.openfga
    return await openfga.client()


def run() -> None:
    """Run the OpenFga MCP server."""
    args = OpenFga().args()

    if args.transport == "stdio":
        mcp.run(transport="stdio")

    else:
        mcp_server = mcp._mcp_server

        starlette_app = create_starlette_app(mcp_server, debug=True)
        uvicorn.run(starlette_app, host=args.host, port=args.port)
