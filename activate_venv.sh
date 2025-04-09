#!/bin/bash
# This script activates the virtual environment for the OpenFGA MCP project

# Determine the script path regardless of how it's called
if [ -n "$BASH_SOURCE" ]; then
    script_path="$BASH_SOURCE"
elif [ -n "$ZSH_VERSION" ]; then
    script_path="${(%):-%x}"
else
    echo "Unable to determine script path. Please source the virtual environment directly:"
    echo "source .venv/bin/activate"
    return 1
fi

# Get the directory where this script is located
script_dir="$(cd "$(dirname "$script_path")" && pwd)"

# Activate the virtual environment
source "$script_dir/.venv/bin/activate"

# Print success message with color if available
if command -v tput &> /dev/null; then
    GREEN=$(tput setaf 2)
    YELLOW=$(tput setaf 3)
    RESET=$(tput sgr0)
    echo "${GREEN}Virtual environment activated!${RESET}"
    echo "Run ${YELLOW}deactivate${RESET} to exit the virtual environment"
else
    echo "Virtual environment activated!"
    echo "Run 'deactivate' to exit the virtual environment"
fi

# Add project bin directory to PATH if it exists
if [ -d "$script_dir/.venv/bin" ]; then
    export PATH="$script_dir/.venv/bin:$PATH"
fi
