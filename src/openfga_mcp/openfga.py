import argparse
from typing import ClassVar

from openfga_sdk import ClientConfiguration, OpenFgaClient
from openfga_sdk.credentials import CredentialConfiguration
from openfga_sdk.oauth2 import Credentials


class OpenFga:
    _instance: ClassVar["OpenFga | None"] = None
    _client: OpenFgaClient | None = None
    _args: argparse.Namespace | None = None

    async def client(self) -> OpenFgaClient:
        if self._client is not None:
            return self._client

        args = self.args()

        self._client = await self._get_configured_client(
            api_url=args.openfga_url,
            store_id=args.openfga_store,
            model_id=args.openfga_model,
            token=args.openfga_token,
            client_id=args.openfga_client_id,
            client_secret=args.openfga_client_secret,
            api_issuer=args.openfga_api_issuer,
            api_audience=args.openfga_api_audience,
        )

        return self._client

    async def close(self) -> None:
        if self._client is not None:
            await self._client.close()
            self._client = None

    def args(self) -> argparse.Namespace:
        if self._args is not None:
            return self._args

        # Set up command-line argument parsing
        parser = argparse.ArgumentParser(description="Run MCP server with configurable transport")

        # Allow choosing between stdio and SSE transport modes
        parser.add_argument(
            "--transport",
            choices=["stdio", "sse"],
            default="stdio",
            help="Transport mode (stdio or sse)",
        )

        # Host configuration for SSE mode
        parser.add_argument("--host", default="0.0.0.0", help="Host to bind to (for SSE mode)")

        # Port configuration for SSE mode
        parser.add_argument("--port", type=int, default=8080, help="Port to listen on (for SSE mode)")

        # OpenFGA configuration
        parser.add_argument("--openfga_url", type=str, required=True, help="URL of your OpenFGA server")
        parser.add_argument("--openfga_store", type=str, required=True, help="ID of the store the MCP server will use")
        parser.add_argument("--openfga_model", type=str, help="ID of the authorization model the MCP server will use")

        parser.add_argument("--openfga_token", type=str, help="API token for use with your OpenFGA server")

        parser.add_argument("--openfga_client_id", type=str, help="Client ID for use with your OpenFGA server")
        parser.add_argument("--openfga_client_secret", type=str, help="Client secret for use with your OpenFGA server")
        parser.add_argument("--openfga_api_issuer", type=str, help="API issuer for use with your OpenFGA server")
        parser.add_argument("--openfga_api_audience", type=str, help="API audience for use with your OpenFGA server")

        self._args = parser.parse_args()
        return self._args

    async def _get_configured_client(
        self,
        api_url: str,
        store_id: str,
        model_id: str,
        token: str | None = None,
        client_id: str | None = None,
        client_secret: str | None = None,
        api_issuer: str | None = None,
        api_audience: str | None = None,
    ) -> OpenFgaClient:
        configuration = ClientConfiguration(
            api_url=api_url,
            store_id=store_id,
            authorization_model_id=model_id,
        )

        if token:
            configuration.credentials = Credentials(
                method="api_token",
                configuration=CredentialConfiguration(
                    api_token=token,
                ),
            )
        elif client_id and client_secret:
            configuration.credentials = Credentials(
                method="client_credentials",
                configuration=CredentialConfiguration(
                    api_issuer=api_issuer,
                    api_audience=api_audience,
                    client_id=client_id,
                    client_secret=client_secret,
                ),
            )

        async with OpenFgaClient(configuration) as client:
            return client

    def __new__(cls) -> "OpenFga":
        if cls._instance is None:
            cls._instance = super().__new__(cls)

        return cls._instance
