# Contributing to OpenFGA MCP

Thank you for your interest in contributing to OpenFGA MCP! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

- Check if the bug has already been reported in the [Issues](https://github.com/evansims/openfga-mcp/issues)
- If not, create a new issue using the bug report template
- Include as much detail as possible to help us reproduce the issue

### Suggesting Features

- Check if the feature has already been suggested in the [Issues](https://github.com/evansims/openfga-mcp/issues)
- If not, create a new issue using the feature request template
- Describe the feature in detail and explain why it would be valuable

### Pull Requests

1. Fork the repository
2. Create a new branch for your changes
3. Make your changes
4. Add or update tests as needed
5. Ensure all tests pass
6. Submit a pull request

## Development Setup

### Using Make (Recommended)

We provide a Makefile with common development tasks to simplify the setup process:

```bash
# Clone the repository
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Set up the development environment (creates venv and installs dependencies)
make setup

# Activate the virtual environment
source activate_venv.sh
```

Run `make help` to see all available commands.

### Virtual Environment Management

The project includes a convenient activation script for the virtual environment:

```bash
# Activate the virtual environment
source activate_venv.sh

# Run a command within the virtual environment without activating it
make in-venv CMD="python -m openfga_mcp version"

# Start an interactive shell within the virtual environment
make shell

# Start a Python REPL within the virtual environment
make repl

# Start an IPython REPL within the virtual environment
make ipython
```

Most Makefile commands automatically use the virtual environment, so you don't need to activate it manually for common tasks like `make test`, `make lint`, etc.

### Manual Setup

If you prefer not to use Make, you can set up manually:

```bash
# Clone and setup
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Choose your package manager:
# uv (recommended for speed)
uv venv .venv && source .venv/bin/activate && uv pip install -e ".[dev]"

# pip
python -m venv .venv && source .venv/bin/activate && pip install -e ".[dev]"

# Poetry
poetry install --with dev && poetry shell

# Set up pre-commit hooks
pre-commit install
pre-commit install --hook-type commit-msg
```

## Package Management

This project uses [uv](https://github.com/astral-sh/uv) as the preferred package manager:

- **Speed**: uv is significantly faster than pip for dependency installation
- **Reliability**: Better dependency resolution and fewer conflicts
- **Caching**: Efficient caching for faster CI/CD pipelines

While pip and Poetry are still supported, we recommend using uv for the best development experience.

## Development Workflow

The Makefile provides shortcuts for common development tasks:

```bash
# Run tests (automatically uses the virtual environment)
make test

# Run linting (automatically uses the virtual environment)
make lint

# Run type checking (automatically uses the virtual environment)
make type-check

# Format code (automatically uses the virtual environment)
make format

# Build the package (automatically uses the virtual environment)
make build

# Clean build artifacts
make clean

# Update dependencies (automatically uses the virtual environment)
make update

# Run the server locally (automatically uses the virtual environment)
make run
```

## Testing

```bash
# Using Make (automatically uses the virtual environment)
make test
make test-cov  # Run tests with coverage

# Manually (requires activated virtual environment)
pytest
pytest --cov=src/openfga-mcp --cov-report=xml --cov-report=term

# Or using in-venv without activating the environment
make in-venv CMD="pytest"
```

## Style Guide

- Follow [PEP 8](https://peps.python.org/pep-0008/) for Python code
- Use descriptive variable names
- Write docstrings for all functions, classes, and modules
- Keep lines under 100 characters when possible

## Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification for commit messages. This leads to more readable messages that are easy to follow when looking through the project history.

### Format

Each commit message consists of a **header**, a **body**, and a **footer**:

```
<type>(<scope>): <subject>

<body>

<footer>
```

The **header** is mandatory and must conform to the following format:

- **type**: What kind of change is this commit making? (required)
- **scope**: What part of the code is this commit changing? (optional)
- **subject**: A short description of the change (required)

### Types

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation only changes
- **style**: Changes that do not affect the meaning of the code (white-space, formatting, etc)
- **refactor**: A code change that neither fixes a bug nor adds a feature
- **perf**: A code change that improves performance
- **test**: Adding missing tests or correcting existing tests
- **build**: Changes that affect the build system or external dependencies
- **ci**: Changes to our CI configuration files and scripts
- **chore**: Other changes that don't modify src or test files
- **revert**: Reverts a previous commit
- **security**: Changes related to security vulnerabilities or improvements

### Examples

```
feat(auth): add ability to specify custom store ID

This commit adds the ability to specify a custom store ID when connecting to OpenFGA.
The store ID can be specified using the --store flag or the OPENFGA_STORE_ID environment variable.

Closes #123
```

```
fix: correct typo in README.md
```

```
docs(api): update API documentation with new endpoints
```

Our CI system will automatically check that your commit messages follow this format.

## License

By contributing to OpenFGA MCP, you agree that your contributions will be licensed under the project's [Apache License 2.0](LICENSE).
