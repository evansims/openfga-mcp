import argparse
import os
import sys
from dataclasses import dataclass, field

from openfga_sdk import OpenFgaClient
from openfga_sdk.client.configuration import ClientConfiguration
from openfga_sdk.exceptions import ApiException


@dataclass
class OpenFga:
    """OpenFGA configuration and client wrapper."""

    _client: OpenFgaClient | None = field(default=None, init=False)
    _config: ClientConfiguration | None = field(default=None, init=False)

    async def client(self) -> OpenFgaClient:
        """Get or create an initialized OpenFGA client."""
        if self._client is None:
            config = self.get_config()
            # Ensure store_id is set if store_name was used
            await self._ensure_store_id(config)
            self._client = OpenFgaClient(configuration=config)
        return self._client

    def get_config(self) -> ClientConfiguration:
        """Build ClientConfiguration from environment variables."""
        if self._config is None:
            api_scheme = os.getenv("FGA_API_SCHEME", "http")
            api_host = os.getenv("FGA_API_HOST")
            store_id = os.getenv("FGA_STORE_ID")
            store_name = os.getenv("FGA_STORE_NAME")
            auth_model_id = os.getenv("FGA_AUTHORIZATION_MODEL_ID")

            if "unittest" not in sys.modules.keys():
                args = self.args()

                if args.openfga_url:
                    api_scheme = args.openfga_url.split("://")[0]
                    api_host = args.openfga_url.split("://")[1]

                if args.openfga_store:
                    store_id = args.openfga_store
                    store_name = args.openfga_store

                if args.openfga_model:
                    auth_model_id = args.openfga_model

            if not api_host:
                raise ValueError(
                    "OpenFGA API URL must be provided via FGA_API_HOST environment variable or --openfga_url"
                )

            # if not store_id and not store_name:
            #     raise ValueError("Missing required environment variable: FGA_STORE_ID or FGA_STORE_NAME")

            print(
                f"Configuring OpenFGA client: Scheme={api_scheme}, Host={api_host}, "
                f"Store ID={store_id}, Store Name={store_name}, Model ID={auth_model_id}"
            )

            self._config = ClientConfiguration(
                api_scheme=api_scheme,
                api_host=api_host,
                store_id=store_id,
                authorization_model_id=auth_model_id,
                # Add credentials handling here if needed, e.g., from FGA_API_TOKEN env var
            )

            self._config.store_name_for_lookup = store_name if not store_id else None  # type: ignore
        return self._config

    async def get_store_by_name(self, config: ClientConfiguration, store_name: str) -> str | None:
        """Looks up store ID by name."""
        temp_config = ClientConfiguration(
            api_scheme=config.api_scheme,
            api_host=config.api_host,
        )

        async with OpenFgaClient(configuration=temp_config) as temp_client:
            try:
                stores_resp = await temp_client.list_stores()

                stores = []
                found_store = None

                if hasattr(stores_resp, "stores"):
                    stores = stores_resp.stores or []
                elif isinstance(stores_resp, dict) and "stores" in stores_resp:
                    stores = stores_resp["stores"] or []

                # Try to find the store by name
                for store in stores:
                    if isinstance(store, dict) and store.get("name") == store_name:
                        found_store = store
                        break
                    elif hasattr(store, "name") and store.name == store_name:
                        found_store = store
                        break

                if found_store:
                    if isinstance(found_store, dict) and "id" in found_store:
                        return found_store["id"]
                    elif hasattr(found_store, "id"):
                        return found_store.id

            except ApiException as e:
                print(f"API Error looking up store '{store_name}': {e}")
                raise ValueError(
                    f"Failed to find or create store '{store_name}'. Check OpenFGA connection and permissions."
                ) from e
            except Exception as e:
                print(f"Unexpected Error looking up store '{store_name}': {e}")
                raise

            return None

    async def _ensure_store_id(self, config: ClientConfiguration) -> None:
        """Looks up store ID by name if config.store_id is not set."""
        if config.store_id:
            return

        store_name = getattr(config, "store_name_for_lookup", None)

        if not store_name:
            return

        print(f"Store ID not provided, looking up store by name: '{store_name}'")

        store_id = await self.get_store_by_name(config, store_name)

        if store_id:
            config.store_id = store_id
            print(f"Found store '{store_name}' with ID: {store_id}")
        else:
            raise ValueError(f"Store '{store_name}' not found")

        if hasattr(config, "store_name_for_lookup"):
            delattr(config, "store_name_for_lookup")

    async def close(self) -> None:
        """Close the underlying OpenFGA client connection."""
        if self._client:
            await self._client.close()
            self._client = None
            print("OpenFGA client connection closed by OpenFga class.")

    # --- Keep args method for command-line execution, but don't use it for client config --- #
    def args(self) -> argparse.Namespace:
        """Parse command line arguments (primarily for non-test execution)."""
        parser = argparse.ArgumentParser(description="OpenFGA MCP Server")
        parser.add_argument("--transport", choices=["stdio", "sse"], default="sse", help="Transport type")
        parser.add_argument("--host", default="127.0.0.1", help="Host for SSE server")
        parser.add_argument("--port", type=int, default=8000, help="Port for SSE server")

        parser.add_argument("--openfga_url", help="OpenFGA API URL (e.g., http://127.0.0.1:8080)")
        parser.add_argument("--openfga_store", help="OpenFGA Store ID or Name")
        parser.add_argument("--openfga_model", help="OpenFGA Authorization Model ID (optional)")
        parser.add_argument("--openfga_token", help="OpenFGA API Token (optional)")
        parser.add_argument("--openfga_client_id", help="OpenFGA Client ID (optional)")
        parser.add_argument("--openfga_client_secret", help="OpenFGA Client Secret (optional)")
        parser.add_argument("--openfga_api_issuer", help="OpenFGA API Issuer (optional)")

        # Parse known args to avoid conflicts with uvicorn internal args
        parsed_args, _ = parser.parse_known_args()
        return parsed_args
