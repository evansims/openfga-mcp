import sys
import importlib.metadata

try:
    __version__ = importlib.metadata.version("openfga-mcp")
except importlib.metadata.PackageNotFoundError:
    raise RuntimeError("openfga-mcp is not installed")

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "version":
        print(__version__)
        sys.exit(0)

    from openfga_mcp.server import run

    run()
