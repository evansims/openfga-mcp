from typing import Any

import httpx
import uvicorn
from mcp.server import Server
from mcp.server.fastmcp import FastMCP
from mcp.server.sse import SseServerTransport
from starlette.applications import Starlette
from starlette.requests import Request
from starlette.routing import Mount, Route

# Initialize FastMCP server for Weather tools (SSE)
mcp = FastMCP("weather")

# Constants for NWS (National Weather Service) API
NWS_API_BASE = "https://api.weather.gov"
USER_AGENT = "weather-app/1.0"


async def make_nws_request(url: str) -> dict[str, Any] | None:
    """Make a request to the NWS API with proper error handling.

    This function handles the HTTP request to the NWS API, setting appropriate
    headers and handling potential errors during the request.

    Args:
        url: The complete URL for the NWS API endpoint

    Returns:
        A dictionary containing the JSON response if successful, None otherwise
    """
    # Set required headers for the NWS API
    headers = {
        "User-Agent": USER_AGENT,  # NWS API requires a user agent
        "Accept": "application/geo+json",  # Request GeoJSON format
    }

    # Create an async HTTP client
    async with httpx.AsyncClient() as client:
        try:
            # Make the GET request with timeout
            response = await client.get(url, headers=headers, timeout=30.0)
            response.raise_for_status()  # Raise exception for 4XX/5XX responses
            return response.json()  # Parse and return JSON response
        except Exception:
            # Return None if any error occurs (connection, timeout, parsing, etc.)
            return None


def format_alert(feature: dict) -> str:
    """Format an alert feature into a readable string.

    Extracts relevant information from a weather alert feature and formats it
    into a human-readable string.

    Args:
        feature: A dictionary containing a single weather alert feature

    Returns:
        A formatted string with key alert information
    """
    # Extract properties from the feature
    props = feature["properties"]

    # Format the alert with important details
    return f"""
Event: {props.get("event", "Unknown")}
Area: {props.get("areaDesc", "Unknown")}
Severity: {props.get("severity", "Unknown")}
Description: {props.get("description", "No description available")}
Instructions: {props.get("instruction", "No specific instructions provided")}
"""


@mcp.tool()
async def get_alerts(state: str) -> str:
    """Get weather alerts for a US state.

    Fetches active weather alerts from the NWS API for a specified US state.

    Args:
        state: Two-letter US state code (e.g. CA, NY)

    Returns:
        A formatted string containing all active alerts for the state,
        or a message indicating no alerts or an error
    """
    # Construct the URL for the state's active alerts
    url = f"{NWS_API_BASE}/alerts/active/area/{state}"

    # Make the API request
    data = await make_nws_request(url)

    # Check if the response is valid
    if not data or "features" not in data:
        return "Unable to fetch alerts or no alerts found."

    # Check if there are any active alerts
    if not data["features"]:
        return "No active alerts for this state."

    # Format each alert and join them with separators
    alerts = [format_alert(feature) for feature in data["features"]]
    return "\n---\n".join(alerts)


@mcp.tool()
async def get_forecast(latitude: float, longitude: float) -> str:
    """Get weather forecast for a location.

    Fetches the weather forecast from the NWS API for a specified location
    using latitude and longitude coordinates.

    Args:
        latitude: Latitude of the location
        longitude: Longitude of the location

    Returns:
        A formatted string containing the forecast for the next 5 periods,
        or an error message if the forecast couldn't be retrieved
    """
    # First get the forecast grid endpoint using the coordinates
    points_url = f"{NWS_API_BASE}/points/{latitude},{longitude}"
    points_data = await make_nws_request(points_url)

    # Check if we received valid point data
    if not points_data:
        return "Unable to fetch forecast data for this location."

    # Extract the forecast URL from the points response
    # NWS API requires this two-step process to get the forecast
    forecast_url = points_data["properties"]["forecast"]
    forecast_data = await make_nws_request(forecast_url)

    # Check if we received valid forecast data
    if not forecast_data:
        return "Unable to fetch detailed forecast."

    # Extract and format the forecast periods
    periods = forecast_data["properties"]["periods"]
    forecasts = []
    for period in periods[:5]:  # Only show next 5 periods
        forecast = f"""
{period["name"]}:
Temperature: {period["temperature"]}°{period["temperatureUnit"]}
Wind: {period["windSpeed"]} {period["windDirection"]}
Forecast: {period["detailedForecast"]}
"""
        forecasts.append(forecast)

    # Join all forecast periods with separators
    return "\n---\n".join(forecasts)


def create_starlette_app(mcp_server: Server, *, debug: bool = False) -> Starlette:
    """Create a Starlette application that can serve the provided MCP server with SSE.

    Sets up a Starlette web application with routes for SSE (Server-Sent Events)
    communication with the MCP server.

    Args:
        mcp_server: The MCP server instance to connect
        debug: Whether to enable debug mode for the Starlette app

    Returns:
        A configured Starlette application
    """
    # Create an SSE transport with a base path for messages
    sse = SseServerTransport("/messages/")

    async def handle_sse(request: Request) -> None:
        """Handler for SSE connections.

        Establishes an SSE connection and connects it to the MCP server.

        Args:
            request: The incoming HTTP request
        """
        # Connect the SSE transport to the request
        async with sse.connect_sse(
            request.scope,
            request.receive,
            request._send,
        ) as (read_stream, write_stream):
            # Run the MCP server with the SSE streams
            await mcp_server.run(
                read_stream,
                write_stream,
                mcp_server.create_initialization_options(),
            )

    # Create and return the Starlette application with routes
    return Starlette(
        debug=debug,
        routes=[
            Route("/sse", endpoint=handle_sse),  # Endpoint for SSE connections
            Mount("/messages/", app=sse.handle_post_message),  # Endpoint for posting messages
        ],
    )


def run() -> None:
    """Run the MCP server with the specified transport mode."""
    import argparse

    # Set up command-line argument parsing
    parser = argparse.ArgumentParser(description="Run MCP server with configurable transport")

    # Allow choosing between stdio and SSE transport modes
    parser.add_argument("--transport", choices=["stdio", "sse"], default="stdio", help="Transport mode (stdio or sse)")

    # Host configuration for SSE mode
    parser.add_argument("--host", default="0.0.0.0", help="Host to bind to (for SSE mode)")

    # Port configuration for SSE mode
    parser.add_argument("--port", type=int, default=8080, help="Port to listen on (for SSE mode)")

    # OpenFGA configuration
    parser.add_argument("--openfga_env", type=bool, default=False, help="Fallback to using environment variables")

    parser.add_argument("--openfga_url", type=str, help="URL of your OpenFGA server")

    parser.add_argument("--openfga_store", type=str, help="ID of the store the MCP server will use")

    parser.add_argument("--openfga_model", type=str, help="ID of the authorization model the MCP server will use")

    args = parser.parse_args()

    # Launch the server with the selected transport mode
    if args.transport == "stdio":
        print(mcp)
        # Run with stdio transport (default)
        mcp.run(transport="stdio")
    else:
        # Get the underlying MCP server from the FastMCP instance
        mcp_server = mcp._mcp_server

        # Run with SSE transport (web-based)
        starlette_app = create_starlette_app(mcp_server, debug=True)
        uvicorn.run(starlette_app, host=args.host, port=args.port)


if __name__ == "__main__":
    run()
