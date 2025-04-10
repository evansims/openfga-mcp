import os
import signal
import subprocess
import time
from collections.abc import Generator
from urllib.parse import urljoin

import httpx
import pytest

# Define server host/port for tests
TEST_SERVER_HOST = "127.0.0.1"
TEST_SERVER_PORT = 8999  # Use a distinct port for testing
TEST_SERVER_URL = f"http://{TEST_SERVER_HOST}:{TEST_SERVER_PORT}"


@pytest.fixture(scope="session", autouse=True)
def start_services() -> Generator[str, None, None]:
    """
    Manages Docker container, seeding, and the MCP server process for the session.
    Yields the base URL of the MCP server.
    """
    print("\n--- Setting up Integration Test Environment ---")

    # Generate unique name for container
    unique_suffix = f"{int(time.time())}_{os.urandom(3).hex()}"
    fga_container_name = f"openfga_test_{unique_suffix}"
    print(f"Using FGA container name: {fga_container_name}")

    # 1. Docker & Seeding
    print("Starting OpenFGA container...")
    setup_script = os.path.join(os.path.dirname(__file__), "setup_fga.sh")

    subprocess.run(["chmod", "+x", setup_script], check=True)
    # Ensure cleanup uses the unique name even if previous runs failed
    subprocess.run(["docker", "stop", fga_container_name], capture_output=True)
    subprocess.run(["docker", "rm", fga_container_name], capture_output=True)

    # Set env var for setup script
    setup_env = os.environ.copy()
    setup_env["FGA_CONTAINER_NAME"] = fga_container_name

    subprocess.run([setup_script], check=True, capture_output=True, text=True, env=setup_env)
    print("OpenFGA container started.")

    print("Running seed script...")
    seed_env = os.environ.copy()
    seed_env["FGA_API_SCHEME"] = "http"
    seed_env["FGA_API_HOST"] = "localhost:8080"

    print("Seed script finished.")

    # 2. Start MCP Server Process
    print(f"Starting MCP server on {TEST_SERVER_URL}...")
    server_env = os.environ.copy()
    server_env["FGA_API_SCHEME"] = "http"
    server_env["FGA_API_HOST"] = "localhost:8080"
    server_env["FGA_STORE_NAME"] = "test_store"
    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
    server_env["PYTHONPATH"] = project_root + os.pathsep + server_env.get("PYTHONPATH", "")

    server_command = [
        "uvicorn",
        "src.openfga_mcp.server:starlette_app",
        "--host",
        TEST_SERVER_HOST,
        "--port",
        str(TEST_SERVER_PORT),
    ]
    # Redirect server stdout/stderr to /dev/null to keep test output clean unless debugging
    # Use files if you need to capture logs: log_file = open("mcp_server.log", "w")
    # stdout=log_file, stderr=subprocess.STDOUT
    mcp_server_process = subprocess.Popen(
        server_command, env=server_env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
    )

    # Wait for server to be ready
    max_wait = 10
    start_time = time.time()
    server_ready = False
    readiness_url = urljoin(TEST_SERVER_URL, "/healthz")
    while time.time() - start_time < max_wait:
        try:
            response = httpx.get(readiness_url)
            if response.status_code == 200 and response.text == "OK":
                # print(f"MCP server is ready on {readiness_url}.") # Keep output clean
                server_ready = True
                break
            # else:
            # print(f"MCP server health check failed..., retrying...") # Keep output clean
            # time.sleep(0.5)
        except httpx.RequestError:
            # print(f"MCP server not ready yet..., retrying...") # Keep output clean
            time.sleep(0.5)
        except Exception as e:
            print(f"Unexpected error checking server readiness: {e}")
            break

    if not server_ready:
        mcp_server_process.terminate()
        # Add minimal error output on failure
        stdout, stderr = mcp_server_process.communicate(timeout=2)
        print(f"MCP Server failed start stdout: {stdout}")
        print(f"MCP Server failed start stderr: {stderr}")
        pytest.fail(f"MCP server did not become ready at {readiness_url} within {max_wait} seconds.")

    print("MCP server started.")
    # --- Yield to tests ---
    yield TEST_SERVER_URL

    # --- Teardown ---
    print("\n--- Tearing down Integration Test Environment ---")

    # 3. Stop MCP Server Process
    print("Stopping MCP server...")
    if mcp_server_process.poll() is None:
        mcp_server_process.send_signal(signal.SIGINT)
        try:
            # Simplified teardown logging
            mcp_server_process.communicate(timeout=10)
            # print(f"MCP Server stopped stdout:\n{stdout}")
            # print(f"MCP Server stopped stderr:\n{stderr}")
        except subprocess.TimeoutExpired:
            print("MCP server did not terminate gracefully, killing.")
            mcp_server_process.kill()
            mcp_server_process.communicate()
        except Exception as e:
            print(f"Error during MCP server shutdown: {e}")

    # 4. Stop Docker Container
    print("Stopping OpenFGA container...")
    # Use the unique name variable for teardown
    subprocess.run(["docker", "stop", fga_container_name], check=False, capture_output=True)
    subprocess.run(["docker", "rm", fga_container_name], check=False, capture_output=True)
    print("OpenFGA container stopped and removed.")


# --- Test Cases ---


# Helper to make MCP calls via HTTP
async def call_mcp_tool(server_url: str, tool_name: str, args: dict) -> str:
    mcp_endpoint = urljoin(server_url, "/call")  # Use the new direct POST endpoint
    request_body = {"tool": tool_name, "args": args}
    async with httpx.AsyncClient() as client:
        response = await client.post(mcp_endpoint, json=request_body)
        response.raise_for_status()  # Raise exception for bad status codes
        # Assuming the server returns JSON like {"result": "...string..."}
        # Adjust based on actual server response format if different
        response_data = response.json()
        if "result" in response_data:
            return response_data["result"]
        else:
            raise ValueError(f"MCP response did not contain 'result': {response_data}")


@pytest.mark.asyncio
async def test_check_positive(start_services: str):  # Use start_services fixture (server URL)
    """Test successful Check calls based on seeded data."""
    server_url = start_services
    # Anne owns report1
    response_1 = await call_mcp_tool(
        server_url, "check", {"user": "user:anne", "relation": "owner", "object": "document:report1"}
    )
    assert response_1 == "user:anne has the relation owner to document:report1"

    # Bob views report1
    response_2 = await call_mcp_tool(
        server_url, "check", {"user": "user:bob", "relation": "viewer", "object": "document:report1"}
    )
    assert response_2 == "user:bob has the relation viewer to document:report1"


@pytest.mark.asyncio
async def test_check_negative(start_services: str):
    """Test unsuccessful Check calls."""
    server_url = start_services
    # Bob does not own report1
    response_1 = await call_mcp_tool(
        server_url, "check", {"user": "user:bob", "relation": "owner", "object": "document:report1"}
    )
    assert response_1 == "user:bob does not have the relation owner to document:report1"

    # Charlie does not view report1
    response_2 = await call_mcp_tool(
        server_url, "check", {"user": "user:charlie", "relation": "viewer", "object": "document:report1"}
    )
    assert response_2 == "user:charlie does not have the relation viewer to document:report1"


@pytest.mark.asyncio
async def test_list_objects(start_services: str):
    """Test list_objects tool."""
    server_url = start_services
    # What documents does anne own?
    response_anne = await call_mcp_tool(
        server_url, "list_objects", {"user": "user:anne", "relation": "owner", "type": "document"}
    )
    assert "document:report1" in response_anne
    assert "document:report2" in response_anne
    assert response_anne.startswith("user:anne has a owner relationship with ")

    # What documents can bob view?
    response_bob = await call_mcp_tool(
        server_url, "list_objects", {"user": "user:bob", "relation": "viewer", "type": "document"}
    )
    assert response_bob == "user:bob has a viewer relationship with document:report1"

    # What documents can charlie view?
    response_charlie = await call_mcp_tool(
        server_url, "list_objects", {"user": "user:charlie", "relation": "viewer", "type": "document"}
    )
    assert response_charlie == "user:charlie has a viewer relationship with document:report2"


@pytest.mark.asyncio
async def test_list_relations(start_services: str):
    """Test list_relations tool."""
    server_url = start_services
    # What relations does anne have on report1? (Provide possible relations)
    response_anne = await call_mcp_tool(
        server_url, "list_relations", {"user": "user:anne", "object": "document:report1", "relations": "owner,viewer"}
    )
    assert response_anne == "user:anne has the owner relationships with document:report1"

    # What relations does bob have on report1?
    response_bob = await call_mcp_tool(
        server_url, "list_relations", {"user": "user:bob", "object": "document:report1", "relations": "owner,viewer"}
    )
    assert response_bob == "user:bob has the viewer relationships with document:report1"

    # What relations does charlie have on report2?
    response_charlie = await call_mcp_tool(
        server_url,
        "list_relations",
        {"user": "user:charlie", "object": "document:report2", "relations": "owner,viewer"},
    )
    assert response_charlie == "user:charlie has the viewer relationships with document:report2"


@pytest.mark.asyncio
async def test_list_users(start_services: str):
    """Test list_users tool."""
    server_url = start_services
    # Who are the owners of document:report1?
    response_owner1 = await call_mcp_tool(
        server_url, "list_users", {"object": "report1", "type": "document", "relation": "owner"}
    )
    assert response_owner1 == "anne have the owner relationship with report1"

    # Who are the viewers of document:report1?
    response_viewer1 = await call_mcp_tool(
        server_url, "list_users", {"object": "report1", "type": "document", "relation": "viewer"}
    )
    assert response_viewer1 == "bob have the viewer relationship with report1"

    # Who are the owners of document:report2?
    response_owner2 = await call_mcp_tool(
        server_url, "list_users", {"object": "report2", "type": "document", "relation": "owner"}
    )
    assert response_owner2 == "anne have the owner relationship with report2"

    # Who are the viewers of document:report2?
    response_viewer2 = await call_mcp_tool(
        server_url, "list_users", {"object": "report2", "type": "document", "relation": "viewer"}
    )
    assert response_viewer2 == "charlie have the viewer relationship with report2"


@pytest.mark.asyncio
async def test_list_users_no_results(start_services: str):
    """Test list_users when no users match."""
    server_url = start_services
    # Who are the viewers of document:nonexistent?
    response_nonexistent = await call_mcp_tool(
        server_url, "list_users", {"object": "nonexistent", "type": "document", "relation": "viewer"}
    )
    assert response_nonexistent == "No users found with the viewer relationship with nonexistent"
