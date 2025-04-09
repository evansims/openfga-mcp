from importlib.metadata import version

from openfga_mcp.server import run

__version__ = version("mcp")

__all__ = [
    "run",
]
