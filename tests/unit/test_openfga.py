import datetime
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
import pytest_asyncio
from openfga_sdk import OpenFgaClient
from openfga_sdk.client.configuration import ClientConfiguration
from openfga_sdk.exceptions import ApiException
from openfga_sdk.models import CreateStoreResponse, Store

from src.openfga_mcp.openfga import OpenFga


@pytest.fixture
def mock_env_vars(monkeypatch):
    """Mocks environment variables for OpenFGA configuration."""
    monkeypatch.setenv("FGA_API_SCHEME", "http")
    monkeypatch.setenv("FGA_API_HOST", "testhost:8080")
    monkeypatch.setenv("FGA_STORE_ID", "test_store_id_env")
    monkeypatch.setenv("FGA_AUTHORIZATION_MODEL_ID", "test_model_id_env")
    monkeypatch.delenv("FGA_STORE_NAME", raising=False)


@pytest.fixture
def mock_env_vars_name(monkeypatch):
    """Mocks environment variables using FGA_STORE_NAME."""
    monkeypatch.setenv("FGA_API_SCHEME", "https")
    monkeypatch.setenv("FGA_API_HOST", "testhost.name:8081")
    monkeypatch.setenv("FGA_STORE_NAME", "test_store_name_env")
    monkeypatch.delenv("FGA_STORE_ID", raising=False)


@pytest_asyncio.fixture
async def mock_openfga_client():
    """Provides a mock OpenFgaClient instance that supports async context management."""
    mock_client = MagicMock(spec=OpenFgaClient)

    mock_client.__aenter__ = AsyncMock(return_value=mock_client)
    mock_client.__aexit__ = AsyncMock(return_value=None)

    mock_client.close = AsyncMock()
    mock_client.list_stores = AsyncMock()
    mock_client.create_store = AsyncMock()
    return mock_client


@pytest.fixture
def openfga_instance():
    """Provides a fresh OpenFga instance for each test."""
    return OpenFga()


def test_get_config_success(mock_env_vars, openfga_instance):
    """Tests successful configuration loading from environment variables."""
    config = openfga_instance.get_config()
    assert isinstance(config, ClientConfiguration)
    assert config.api_scheme == "http"
    assert config.api_host == "testhost:8080"
    assert config.store_id == "test_store_id_env"
    assert config.authorization_model_id == "test_model_id_env"


def test_get_config_success_with_name(mock_env_vars_name, openfga_instance):
    """Tests successful configuration loading using FGA_STORE_NAME."""
    config = openfga_instance.get_config()
    assert isinstance(config, ClientConfiguration)
    assert config.api_scheme == "https"
    assert config.api_host == "testhost.name:8081"
    assert config.store_id is None
    assert config.authorization_model_id is None
    assert getattr(config, "store_name_for_lookup") == "test_store_name_env"


def test_get_config_missing_host(monkeypatch, openfga_instance):
    """Tests ValueError when FGA_API_HOST is missing."""
    monkeypatch.delenv("FGA_API_HOST", raising=False)
    monkeypatch.setenv("FGA_STORE_ID", "some_id")
    with pytest.raises(
        ValueError, match="OpenFGA API URL must be provided via FGA_API_HOST environment variable or --openfga_url"
    ):
        openfga_instance.get_config()


@pytest.mark.asyncio
async def test_ensure_store_id_already_set(openfga_instance):
    """Tests that _ensure_store_id does nothing if store_id is already set."""
    config = ClientConfiguration(api_scheme="http", api_host="host", store_id="existing_id")
    await openfga_instance._ensure_store_id(config)
    assert config.store_id == "existing_id"


@pytest.mark.asyncio
async def test_ensure_store_id_lookup_success(openfga_instance, mock_openfga_client):
    """Tests successful store ID lookup by name."""
    store_name = "find_me"
    found_store_id = "found_store_123"
    config = ClientConfiguration(api_scheme="http", api_host="host")
    setattr(config, "store_name_for_lookup", store_name)

    now = datetime.datetime.now(datetime.UTC)
    mock_response = MagicMock()
    mock_response.stores = [
        Store(id="other_id", name="other_name", created_at=now, updated_at=now, deleted_at=None),
        Store(id=found_store_id, name=store_name, created_at=now, updated_at=now, deleted_at=None),
    ]
    mock_openfga_client.list_stores.return_value = mock_response

    with patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client):
        await openfga_instance._ensure_store_id(config)

    assert config.store_id == found_store_id
    assert not hasattr(config, "store_name_for_lookup")
    mock_openfga_client.list_stores.assert_awaited_once()
    mock_openfga_client.create_store.assert_not_awaited()


@pytest.mark.asyncio
async def test_ensure_store_id_api_error_lookup(openfga_instance, mock_openfga_client):
    """Tests handling of ApiException during store lookup."""
    store_name = "api_error_lookup"
    config = ClientConfiguration(api_scheme="http", api_host="host")
    setattr(config, "store_name_for_lookup", store_name)

    mock_openfga_client.list_stores.side_effect = ApiException("Lookup failed")

    now = datetime.datetime.now(datetime.UTC)
    mock_create_response = CreateStoreResponse(id="temp_id", name=store_name, created_at=now, updated_at=now)
    mock_openfga_client.create_store.return_value = mock_create_response

    with patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client):
        with pytest.raises(ValueError, match=f"Failed to find or create store '{store_name}'"):
            await openfga_instance._ensure_store_id(config)

    assert config.store_id is None
    mock_openfga_client.list_stores.assert_awaited_once()
    mock_openfga_client.create_store.assert_not_awaited()
    assert hasattr(config, "store_name_for_lookup")


@pytest.mark.asyncio
async def test_ensure_store_id_api_error_create(openfga_instance, mock_openfga_client):
    """Tests handling of ApiException during store creation."""
    store_name = "api_error_create"
    config = ClientConfiguration(api_scheme="http", api_host="host")
    setattr(config, "store_name_for_lookup", store_name)

    mock_list_response = MagicMock()
    mock_list_response.stores = []
    mock_openfga_client.list_stores.return_value = mock_list_response
    mock_openfga_client.create_store.side_effect = ApiException("Creation failed")

    with patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client):
        with pytest.raises(ValueError, match=f"Store '{store_name}' not found"):
            await openfga_instance._ensure_store_id(config)

    assert config.store_id is None
    mock_openfga_client.list_stores.assert_awaited_once()
    assert hasattr(config, "store_name_for_lookup")


@pytest.mark.asyncio
async def test_client_creation_with_id(mock_env_vars, openfga_instance, mock_openfga_client):
    """Tests client creation when store ID is provided directly."""
    with patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client) as mock_constructor:
        client = await openfga_instance.client()

    assert client is mock_openfga_client
    mock_constructor.assert_called_once()
    call_args, call_kwargs = mock_constructor.call_args
    passed_config: ClientConfiguration = call_kwargs["configuration"]
    assert passed_config.api_scheme == "http"
    assert passed_config.api_host == "testhost:8080"
    assert passed_config.store_id == "test_store_id_env"
    assert passed_config.authorization_model_id == "test_model_id_env"

    mock_openfga_client.list_stores.assert_not_awaited()
    mock_openfga_client.create_store.assert_not_awaited()

    client2 = await openfga_instance.client()
    assert client2 is client
    mock_constructor.assert_called_once()


@pytest.mark.asyncio
async def test_client_creation_with_name(mock_env_vars_name, openfga_instance, mock_openfga_client):
    """Tests client creation involving store name lookup/creation."""
    store_name = "test_store_name_env"
    found_store_id = "found_from_name_id"

    async def mock_ensure(config):
        assert getattr(config, "store_name_for_lookup") == store_name
        config.store_id = found_store_id
        delattr(config, "store_name_for_lookup")

    with (
        patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client) as mock_constructor,
        patch.object(
            openfga_instance, "_ensure_store_id", side_effect=mock_ensure, autospec=True
        ) as mock_ensure_method,
    ):
        client = await openfga_instance.client()

    assert client is mock_openfga_client
    mock_ensure_method.assert_awaited_once()
    mock_constructor.assert_called_once()

    call_args, call_kwargs = mock_constructor.call_args
    passed_config: ClientConfiguration = call_kwargs["configuration"]
    assert passed_config.api_scheme == "https"
    assert passed_config.api_host == "testhost.name:8081"
    assert passed_config.store_id == found_store_id
    assert passed_config.authorization_model_id is None
    assert not hasattr(passed_config, "store_name_for_lookup")

    client2 = await openfga_instance.client()
    assert client2 is client
    mock_constructor.assert_called_once()
    mock_ensure_method.assert_awaited_once()


@pytest.mark.asyncio
async def test_close_no_client(openfga_instance):
    """Tests closing when no client was ever created."""
    await openfga_instance.close()


@pytest.mark.asyncio
async def test_close_with_client(mock_env_vars, openfga_instance, mock_openfga_client):
    """Tests closing an initialized client."""
    with patch("src.openfga_mcp.openfga.OpenFgaClient", return_value=mock_openfga_client):
        await openfga_instance.client()

    assert openfga_instance._client is not None
    await openfga_instance.close()

    mock_openfga_client.close.assert_awaited_once()
    assert openfga_instance._client is None

    mock_openfga_client.close.reset_mock()
    await openfga_instance.close()
    mock_openfga_client.close.assert_not_awaited()


def test_args_defaults(openfga_instance):
    """Tests default arguments from args()."""
    with patch("sys.argv", ["script_name"]):
        args = openfga_instance.args()
    assert args.transport == "sse"
    assert args.host == "127.0.0.1"
    assert args.port == 8000
    assert args.openfga_url is None
    assert args.openfga_store is None


def test_args_custom(openfga_instance):
    """Tests parsing custom arguments."""
    test_args = [
        "script_name",
        "--transport",
        "stdio",
        "--host",
        "0.0.0.0",
        "--port",
        "9999",
        "--openfga_url",
        "http://fga.example.com",
        "--openfga_store",
        "my-store-cli",
        "--unknown_arg",
    ]
    with patch("sys.argv", test_args):
        args = openfga_instance.args()
    assert args.transport == "stdio"
    assert args.host == "0.0.0.0"
    assert args.port == 9999
    assert args.openfga_url == "http://fga.example.com"
    assert args.openfga_store == "my-store-cli"
