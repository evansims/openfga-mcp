import logging
import sys

import typer
from rich.console import Console

from .server import serve

__version__ = "0.0.1"

app = typer.Typer(
    help="OpenFGA MCP Server - Use LLMs to read, search, and manipulate OpenFGA stores",
    add_completion=True,
    rich_markup_mode="rich",
)

console = Console()


@app.command()
def main(
    url: str | None = typer.Option(
        "https://localhost:8000",
        "-u",
        "--url",
        help="OpenFGA server to connect to",
        envvar="OPENFGA_API_URL",
    ),
    store: str | None = typer.Option(
        None, "-s", "--store", help="OpenFGA store ID to use", envvar="OPENFGA_STORE_ID"
    ),
    verbose: int = typer.Option(
        0, "-v", "--verbose", count=True, help="Increase verbosity", show_default=False
    ),
) -> None:
    """
    Start the OpenFGA MCP server.

    This server provides a bridge between Large Language Models and OpenFGA,
    allowing LLMs to read, search, and manipulate OpenFGA stores.
    """
    import asyncio

    # Configure logging based on verbosity
    logging_level = logging.WARN
    if verbose == 1:
        logging_level = logging.INFO
    elif verbose >= 2:
        logging_level = logging.DEBUG

    logging.basicConfig(level=logging_level, stream=sys.stderr)

    # Display startup information
    console.print(f"[bold green]Starting OpenFGA MCP Server v{__version__}[/bold green]")
    console.print(f"OpenFGA API URL: [blue]{url or 'default'}[/blue]")
    console.print(f"OpenFGA Store ID: [blue]{store or 'default'}[/blue]")
    console.print(f"Log Level: [blue]{logging.getLevelName(logging_level)}[/blue]")

    # Call the serve function from server.py
    try:
        asyncio.run(serve(url, store))
        console.print("[bold green]MCP server started successfully![/bold green]")
    except Exception as e:
        console.print(f"[bold red]Error starting MCP server: {e}[/bold red]")
        raise typer.Exit(code=1)


@app.command()
def version():
    """Display the version of OpenFGA MCP."""
    console.print(f"OpenFGA MCP v{__version__}")


if __name__ == "__main__":
    app()
