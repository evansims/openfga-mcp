import click
import logging
import sys
from .server import serve

__version__ = "0.0.1"


@click.command()
@click.option("-u", "--url", type=str, help="OpenFGA server to connect to")
@click.option("-s", "--store", type=str, help="OpenFGA store ID to use")
@click.option("-v", "--verbose", count=True)
def main(url: str, store: str, verbose: bool) -> None:
    """MCP OpenFGA Server - OpenFGA functionality for MCP"""
    import asyncio

    logging_level = logging.WARN
    if verbose == 1:
        logging_level = logging.INFO
    elif verbose >= 2:
        logging_level = logging.DEBUG

    logging.basicConfig(level=logging_level, stream=sys.stderr)
    asyncio.run(serve(url, store))


if __name__ == "__main__":
    main()
