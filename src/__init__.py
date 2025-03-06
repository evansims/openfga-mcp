import click
from pathlib import Path
import logging
import sys
from .server import serve


@click.command()
@click.option("--server", "-server", type=Path, help="OpenFGA server to connect to")
@click.option("-v", "--verbose", count=True)
def main(repository: Path | None, verbose: bool) -> None:
    """MCP OpenFGA Server - OpenFGA functionality for MCP"""
    import asyncio

    logging_level = logging.WARN
    if verbose == 1:
        logging_level = logging.INFO
    elif verbose >= 2:
        logging_level = logging.DEBUG

    logging.basicConfig(level=logging_level, stream=sys.stderr)
    asyncio.run(serve(repository))


if __name__ == "__main__":
    main()
