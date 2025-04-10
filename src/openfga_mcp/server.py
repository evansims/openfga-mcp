from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from dataclasses import dataclass
from typing import Any

import uvicorn
from mcp.server.fastmcp import Context, FastMCP
from mcp.server.sse import SseServerTransport
from openfga_sdk import FgaObject, OpenFgaClient
from openfga_sdk.client.client import ClientListObjectsRequest, ClientListRelationsRequest, ClientListUsersRequest
from openfga_sdk.client.models.check_request import ClientCheckRequest
from openfga_sdk.models.create_store_request import CreateStoreRequest
from openfga_sdk.models.write_authorization_model_request import WriteAuthorizationModelRequest
from starlette.applications import Starlette
from starlette.requests import Request
from starlette.responses import JSONResponse, PlainTextResponse
from starlette.routing import Mount, Route

from openfga_mcp.openfga import OpenFga


@dataclass
class ServerContext:
    openfga: OpenFga


# Type alias for improved readability
type JsonDict = dict[str, Any]


def _write_to_log(message: Any, log_file: str = "openfga_mcp.log") -> None:
    """
    Helper method to log messages to a local file.

    Args:
        message: The message to log (can be any type, will be converted to string)
        log_file: Path to the log file (defaults to openfga_mcp.log in the current directory)
    """
    try:
        import datetime
        import json
        import os

        # Create directory if it doesn't exist
        log_dir = os.path.dirname(log_file)
        if log_dir and not os.path.exists(log_dir):
            os.makedirs(log_dir)

        # Get current timestamp
        timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        # Convert message to string
        if isinstance(message, dict) or isinstance(message, list):
            message_str = json.dumps(message)
        else:
            message_str = str(message)

        # Write to log file with timestamp
        with open(log_file, "a") as f:
            f.write(f"[{timestamp}] {message_str}\n")
    except Exception as e:
        # Silent failure - don't interrupt main flow if logging fails
        print(f"Error writing to log file: {e}")


@asynccontextmanager
async def openfga_sse_lifespan(app: Starlette) -> AsyncIterator[JsonDict]:
    """Get OpenFga instance and store it in app state."""
    _write_to_log("openfga_sse_lifespan")

    openfga = OpenFga()
    server_context = ServerContext(openfga)
    app.state.server_context = server_context

    _write_to_log(app)

    try:
        yield {"server_context": server_context}
    finally:
        await openfga.close()


@asynccontextmanager
async def openfga_mcp_lifespan(server: FastMCP) -> AsyncIterator[JsonDict]:
    """Get OpenFga instance and store it in app state."""
    _write_to_log("openfga_mcp_lifespan")
    _write_to_log(server)

    openfga = OpenFga()
    server_context = ServerContext(openfga)

    _write_to_log(openfga)

    try:
        yield {"server_context": server_context}
    finally:
        await openfga.close()


mcp = FastMCP("openfga-mcp", lifespan=openfga_mcp_lifespan)


async def health_check(request: Request) -> PlainTextResponse:
    return PlainTextResponse("OK")


mcp_server_instance = mcp._mcp_server
starlette_app = Starlette(debug=True, lifespan=openfga_sse_lifespan)


async def handle_mcp_post(request: Request) -> JSONResponse:
    """Handles direct POST requests for MCP tools."""
    try:
        if not hasattr(request.app.state, "server_context"):
            return JSONResponse({"error": "Server context not available"}, status_code=500)

        body = await request.json()
        # Extract tool_name and args from the body
        if not isinstance(body, dict):
            return JSONResponse({"error": "Request body must be a JSON object"}, status_code=400)

        tool_name = body.get("tool")
        if not tool_name:
            return JSONResponse({"error": "Missing 'tool' in request body"}, status_code=400)

        args = body.get("args", {})

        _write_to_log("handle_mcp_post")
        _write_to_log(args)
        _write_to_log(request)

        try:
            server_context = request.app.state.server_context
            client = await server_context.openfga.client()
        except Exception as client_error:
            return JSONResponse({"error": f"Failed to obtain OpenFGA client: {str(client_error)}"}, status_code=500)

        match tool_name:
            case "check":
                if not all(k in args for k in ["user", "relation", "object"]):
                    return JSONResponse({"error": "Missing required args for check"}, status_code=400)
                result = await _check_impl(client, **args)
            case "list_objects":
                if not all(k in args for k in ["user", "relation", "type"]):
                    return JSONResponse({"error": "Missing required args for list_objects"}, status_code=400)
                result = await _list_objects_impl(client, **args)
            case "list_relations":
                if not all(k in args for k in ["user", "relations", "object"]):
                    return JSONResponse({"error": "Missing required args for list_relations"}, status_code=400)
                result = await _list_relations_impl(client, **args)
            case "list_users":
                if not all(k in args for k in ["object", "type", "relation"]):
                    return JSONResponse({"error": "Missing required args for list_users"}, status_code=400)
                result = await _list_users_impl(client, **args)
            case "list_stores":
                result = await _list_stores_impl(client)
            case "get_store_id_by_name":
                if "name" not in args:
                    return JSONResponse(
                        {"error": "Missing required arg 'name' for get_store_id_by_name"}, status_code=400
                    )
                result = await get_store_id_by_name(client, **args)
            case "get_store":
                if "store_id" not in args:
                    return JSONResponse({"error": "Missing required arg 'store_id' for get_store"}, status_code=400)
                result = await _get_store_impl(client, **args)
            case "delete_store":
                if "store_id" not in args:
                    return JSONResponse({"error": "Missing required arg 'store_id' for delete_store"}, status_code=400)
                result = await _delete_store_impl(client, **args)
            case "write_authorization_model":
                if "store_id" not in args:
                    return JSONResponse(
                        {"error": "Missing required arg 'store_id' for write_authorization_model"}, status_code=400
                    )
                if "auth_model_data" not in args:
                    return JSONResponse(
                        {"error": "Missing required arg 'auth_model_data' for write_authorization_model"},
                        status_code=400,
                    )
                result = await _write_authorization_model_impl(client, **args)
            case "create_store":
                if "name" not in args:
                    return JSONResponse({"error": "Missing required arg 'name' for create_store"}, status_code=400)
                result = await _create_store_impl(client, **args)
            case _:
                return JSONResponse({"error": f"Unsupported tool: {tool_name}"}, status_code=400)

        return JSONResponse({"result": result})

    except Exception as e:
        return JSONResponse({"error": f"Internal server error: {e!s}"}, status_code=500)


sse = SseServerTransport("/messages/")


async def handle_sse(request: Request) -> None:
    async with sse.connect_sse(
        request.scope,
        request.receive,
        request._send,
    ) as (read_stream, write_stream):
        await mcp_server_instance.run(
            read_stream,
            write_stream,
            mcp_server_instance.create_initialization_options(),
        )


starlette_app.routes.extend(
    [
        Route("/healthz", endpoint=health_check),
        Route("/call", endpoint=handle_mcp_post, methods=["POST"]),
        Route("/sse", endpoint=handle_sse),
        Mount("/messages/", app=sse.handle_post_message),
    ]
)


async def _check_impl(client: OpenFgaClient, user: str, relation: str, object: str) -> str:
    try:
        body = ClientCheckRequest(user=user, relation=relation, object=object)
        response = await client.check(body)

        # Extract allowed state safely, handling both object and dict responses
        allowed = False
        if hasattr(response, "allowed"):
            allowed = response.allowed
        elif isinstance(response, dict) and "allowed" in response:
            allowed = response["allowed"]

        return (
            f"{user} has the relation {relation} to {object}"
            if allowed
            else f"{user} does not have the relation {relation} to {object}"
        )
    except Exception as e:
        return f"Error checking relation: {e!s}"


async def _list_objects_impl(client: OpenFgaClient, user: str, relation: str, type: str) -> str:
    try:
        body = ClientListObjectsRequest(user=user, relation=relation, type=type)
        response = await client.list_objects(body)

        # Extract objects safely from various response formats
        objects = []
        if hasattr(response, "objects"):
            objects = response.objects or []
        elif isinstance(response, dict) and "objects" in response:
            objects = response["objects"] or []

        object_list_str = ", ".join(objects)

        # Always use the same format to maintain test compatibility
        return f"{user} has a {relation} relationship with {object_list_str}"
    except Exception as e:
        return f"Error listing related objects: {e!s}"


async def _list_relations_impl(client: OpenFgaClient, user: str, relations: str, object: str) -> str:
    try:
        relations_list = relations.split(",")
        body = ClientListRelationsRequest(user=user, relations=relations_list, object=object)
        response = await client.list_relations(body)

        # Extract relations safely from various possible response formats
        relations_result = []

        # Response could be an iterable directly
        if response is not None:
            if hasattr(response, "__iter__"):
                relations_result = list(response)
            elif isinstance(response, dict) and "relations" in response:
                relations_result = response["relations"]

        # Join the relations into a string for the response
        relations_str = ", ".join(str(rel) for rel in relations_result)

        return f"{user} has the {relations_str} relationships with {object}"
    except Exception as e:
        return f"Error listing relations: {e!s}"


async def _list_users_impl(client: OpenFgaClient, object: str, type: str, relation: str) -> str:
    try:
        fga_obj = FgaObject(type=type, id=object)
        from openfga_sdk.models.user_type_filter import UserTypeFilter

        body = ClientListUsersRequest(
            object=fga_obj,
            relation=relation,
            user_filters=[UserTypeFilter(type="user")],
        )
        response = await client.list_users(body)

        # Extract users safely from various response formats
        users = []

        # Handle different possible response structures
        if hasattr(response, "users") and response.users:
            for user in response.users:
                if hasattr(user, "object") and user.object and hasattr(user.object, "id"):
                    users.append(user.object.id)
        elif isinstance(response, dict) and "users" in response:
            for user in response["users"]:
                if isinstance(user, dict) and "object" in user and user["object"] and "id" in user["object"]:
                    users.append(user["object"]["id"])

        if users:
            users_str = ", ".join(users)
            return f"{users_str} have the {relation} relationship with {object}"
        else:
            return f"No users found with the {relation} relationship with {object}"
    except Exception as e:
        return f"Error listing users: {e!s}"


async def _list_stores_impl(client: OpenFgaClient) -> str:
    try:
        response = await client.list_stores()

        # Extract stores safely from various response formats
        stores = []

        # Handle different possible response structures
        if hasattr(response, "stores") and response.stores:
            stores = response.stores
        elif isinstance(response, dict) and "stores" in response:
            stores = response["stores"]

        # Format the response
        if stores:
            stores_info = []
            for store in stores:
                store_id = store.id if hasattr(store, "id") else store.get("id", "unknown")
                store_name = store.name if hasattr(store, "name") else store.get("name", "unknown")
                created_at = store.created_at if hasattr(store, "created_at") else store.get("created_at", "unknown")
                stores_info.append(f"ID: {store_id}, Name: {store_name}, Created: {created_at}")

            stores_str = "\n".join(stores_info)
            result = f"Found stores:\n{stores_str}"

            return result
        else:
            return "No stores found"
    except Exception as e:
        return f"Error listing stores: {e!s}"


async def _create_store_impl(client: OpenFgaClient, name: str) -> str:
    """Creates a new OpenFGA store with the given name.

    Args:
        client: The OpenFGA client
        name: The name of the store to create

    Returns:
        A string with the result of the operation
    """
    try:
        # Create the store request body
        body = CreateStoreRequest(name=name)

        # Call the create_store API
        response = await client.create_store(body)

        # Extract store ID from the response
        store_id = None
        if hasattr(response, "id"):
            store_id = response.id
        elif isinstance(response, dict) and "id" in response:
            store_id = response["id"]

        if store_id:
            return f"Store '{name}' created successfully with ID: {store_id}"
        else:
            return f"Store '{name}' created successfully, but no ID was returned"

    except Exception as e:
        _write_to_log(f"Error creating store: {e!s}")
        return f"Error creating store: {e!s}"


async def _get_client(ctx: Context | None = None, app: Starlette | FastMCP | None = None) -> OpenFgaClient:
    """Retrieves the OpenFgaClient from context or app state."""
    server_ctx: ServerContext | None = None

    _write_to_log("_get_client")
    _write_to_log(ctx)
    _write_to_log(app)

    match (ctx, app):
        case (Context() as c, _) if (
            hasattr(c.request_context, "lifespan_context") and c.request_context.lifespan_context
        ):
            server_ctx = c.request_context.lifespan_context.get("server_context")
        case (_, Starlette() as a) if hasattr(a.state, "server_context"):
            server_ctx = a.state.server_context
        case _:
            # No valid context found, server_ctx remains None
            server_ctx = None

    if isinstance(server_ctx, ServerContext):
        return await server_ctx.openfga.client()

    raise RuntimeError("Could not retrieve OpenFGA client: ServerContext not found.")


@mcp.tool()
async def check(ctx: Context, user: str, relation: str, object: str) -> str:
    """Checks if a user has a relation to an object.

    Args:
        ctx: The MCP context
        user: The user to check
        relation: The relationship to check
        object: The object to check

    Returns:
        A string with the result of the operation
    """
    return await _check_impl(await _get_client(ctx), user=user, relation=relation, object=object)


@mcp.tool()
async def list_objects(ctx: Context, user: str, relation: str, type: str) -> str:
    """Lists all objects that have a given relationship with a given user.

    Args:
        ctx: The MCP context
        user: The user to list objects for
        relation: The relationship to list objects for
        type: The type of the objects to list

    Returns:
        A string with the result of the operation
    """
    return await _list_objects_impl(await _get_client(ctx), user=user, relation=relation, type=type)


@mcp.tool()
async def list_relations(ctx: Context, user: str, relations: str, object: str) -> str:
    """Lists all relations for which a user has a relation to an object.

    Args:
        ctx: The MCP context
        user: The user to list relations for
        relations: The relations to list
        object: The object to list relations for

    Returns:
        A string with the result of the operation
    """
    return await _list_relations_impl(await _get_client(ctx), user=user, relations=relations, object=object)


@mcp.tool()
async def list_users(ctx: Context, object: str, type: str, relation: str) -> str:
    """Lists all users that have a given relationship with a given object.

    Args:
        ctx: The MCP context
        object: The object to list users for
        type: The type of the object
        relation: The relationship to list users for

    Returns:
        A string with the result of the operation
    """
    return await _list_users_impl(await _get_client(ctx), object=object, type=type, relation=relation)


@mcp.tool()
async def get_store_id_by_name(ctx: Context, name: str) -> str:
    """Get the ID of a store by it's name.

    Args:
        ctx: The MCP context
        name: The name of the store to get the ID of

    Returns:
        A string with the result of the operation
    """
    openfga = OpenFga()
    store_name = await openfga.get_store_by_name(openfga.get_config(), name)

    if store_name:
        return f"Store '{name}' found with ID: {store_name}"
    else:
        return f"Store '{name}' not found"


@mcp.tool()
async def list_stores(ctx: Context) -> str:
    """Lists all OpenFGA stores.

    Args:
        ctx: The MCP context

    Returns:
        A string with the result of the operation
    """
    _write_to_log("list_stores")
    _write_to_log(ctx)
    return await _list_stores_impl(await _get_client(ctx))


@mcp.tool()
async def create_store(ctx: Context, name: str) -> str:
    """Creates a new OpenFGA store with the given name.

    Args:
        ctx: The MCP context
        name: The name for the new store

    Returns:
        A string with the result of the operation
    """
    _write_to_log(f"create_store: {name}")
    _write_to_log(ctx)
    return await _create_store_impl(await _get_client(ctx), name=name)


async def _get_store_impl(client: OpenFgaClient, store_id: str) -> str:
    """
    Gets information about a specific store by its ID.

    Args:
        client: The OpenFGA client
        store_id: The ID of the store to retrieve

    Returns:
        A string with the store information
    """
    try:
        # Save the current store ID
        current_store_id = client.get_store_id()

        # Set the store ID for this request
        client.set_store_id(store_id)

        # Call the get_store API
        response = await client.get_store()

        # Restore the original store ID
        if current_store_id:
            client.set_store_id(current_store_id)

        # Extract store information from the response
        store_info = []

        # Handle different response formats
        if hasattr(response, "id") and hasattr(response, "name"):
            store_info.append(f"ID: {response.id}")
            store_info.append(f"Name: {response.name}")
            if hasattr(response, "created_at"):
                store_info.append(f"Created: {response.created_at}")
            if hasattr(response, "updated_at"):
                store_info.append(f"Updated: {response.updated_at}")
        elif isinstance(response, dict):
            if "id" in response:
                store_info.append(f"ID: {response['id']}")
            if "name" in response:
                store_info.append(f"Name: {response['name']}")
            if "created_at" in response:
                store_info.append(f"Created: {response['created_at']}")
            if "updated_at" in response:
                store_info.append(f"Updated: {response['updated_at']}")

        if store_info:
            return f"Store details:\n{', '.join(store_info)}"
        else:
            return f"Store with ID '{store_id}' found, but no details were returned"

    except Exception as e:
        return f"Error retrieving store: {e!s}"


@mcp.tool()
async def get_store(ctx: Context, store_id: str) -> str:
    """
    Gets information about a specific store by its ID.

    Args:
        ctx: The MCP context
        store_id: The ID of the store to retrieve

    Returns:
        A string with the store information
    """
    _write_to_log(f"get_store: {store_id}")
    _write_to_log(ctx)
    return await _get_store_impl(await _get_client(ctx), store_id=store_id)


async def _delete_store_impl(client: OpenFgaClient, store_id: str) -> str:
    """
    Deletes a store by its ID.

    Args:
        client: The OpenFGA client
        store_id: The ID of the store to delete

    Returns:
        A string with the result of the operation
    """
    try:
        # Save the current store ID
        current_store_id = client.get_store_id()

        # Set the store ID for this request
        client.set_store_id(store_id)

        # Call the delete_store API
        await client.delete_store()

        # Restore the original store ID if it exists and is different
        if current_store_id and current_store_id != store_id:
            client.set_store_id(current_store_id)

        return f"Store with ID '{store_id}' has been successfully deleted"

    except Exception as e:
        _write_to_log(f"Error deleting store: {e!s}")
        return f"Error deleting store: {e!s}"


@mcp.tool()
async def delete_store(ctx: Context, store_id: str) -> str:
    """
    Deletes a store by its ID.

    Args:
        ctx: The MCP context
        store_id: The ID of the store to delete

    Returns:
        A string with the result of the operation
    """
    _write_to_log(f"delete_store: {store_id}")
    _write_to_log(ctx)
    return await _delete_store_impl(await _get_client(ctx), store_id=store_id)


async def _write_authorization_model_impl(client: OpenFgaClient, store_id: str, auth_model_data: dict) -> str:
    """
    Creates a new authorization model for a specific store.

    Args:
        client: The OpenFGA client
        store_id: The ID of the store to add the authorization model to
        auth_model_data: The authorization model data

    Returns:
        A string with the result of the operation
    """
    try:
        # Save the current store ID
        current_store_id = client.get_store_id()

        # Set the store ID for this request
        client.set_store_id(store_id)

        # Convert auth_model_data dict to WriteAuthorizationModelRequest
        model_request = WriteAuthorizationModelRequest(
            schema_version=auth_model_data.get("schema_version", "1.1"),
            type_definitions=auth_model_data.get("type_definitions", []),
            conditions=auth_model_data.get("conditions", {}),
        )

        # Call the write_authorization_model API
        response = await client.write_authorization_model(body=model_request)

        # Restore the original store ID if it exists and is different
        if current_store_id and current_store_id != store_id:
            client.set_store_id(current_store_id)

        # Extract the authorization model ID from the response
        auth_model_id = None
        if hasattr(response, "authorization_model_id"):
            auth_model_id = response.authorization_model_id
        elif isinstance(response, dict) and "authorization_model_id" in response:
            auth_model_id = response["authorization_model_id"]

        if auth_model_id:
            return f"Authorization model successfully created with ID: {auth_model_id}"
        else:
            return "Authorization model successfully created, but no ID was returned"

    except Exception as e:
        _write_to_log(f"Error creating authorization model: {e!s}")
        return f"Error creating authorization model: {e!s}"


@mcp.tool()
async def write_authorization_model(ctx: Context, store_id: str, auth_model_data: dict) -> str:
    """
    Creates a new authorization model for a specific store.

    Args:
        ctx: The MCP context
        store_id: The ID of the store to add the authorization model to
        model_data: The authorization model data (as a dict with schema_version, type_definitions, etc.)

    Returns:
        A string with the result of the operation
    """
    _write_to_log(f"write_authorization_model: {store_id}")
    _write_to_log(ctx)
    return await _write_authorization_model_impl(
        await _get_client(ctx), store_id=store_id, auth_model_data=auth_model_data
    )


def run() -> None:
    """Run the OpenFga MCP server."""
    args = OpenFga().args()

    _write_to_log("run()")
    _write_to_log(args)

    match args.transport:
        case "stdio":
            mcp.run(transport="stdio")
        case _:
            uvicorn.run(starlette_app, host=args.host, port=args.port)
