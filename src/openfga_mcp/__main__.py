import sys
from importlib.metadata import version

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--version":
        print(version("openfga_mcp"))
        sys.exit(0)

    from openfga_mcp.server import run

    run()
