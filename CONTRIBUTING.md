# Contributing to OpenFGA MCP

## How to Contribute

### Reporting Bugs

- Check existing [Issues](https://github.com/evansims/openfga-mcp/issues)
- Create a new issue with detailed reproduction steps

### Suggesting Features

- Check existing [Issues](https://github.com/evansims/openfga-mcp/issues)
- Create a new issue describing the feature and its value

### Pull Requests

1. Fork the repository
2. Create a branch for your changes
3. Add or update tests
4. Ensure all tests pass
5. Submit a pull request

## Development Setup

### Using Make (Recommended)

```bash
# Clone and setup
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp
make setup
source activate_venv.sh
```

Run `make help` to see all available commands.

### Virtual Environment

```bash
# Activate
source activate_venv.sh

# Run commands without activating
make in-venv CMD="your-command"

# Interactive environments
make shell    # Shell in virtual environment
make repl     # Python REPL
make ipython  # IPython REPL
```

Most Makefile commands automatically use the virtual environment.

### Manual Setup

```bash
# Clone
git clone https://github.com/evansims/openfga-mcp.git
cd openfga-mcp

# Setup with uv (recommended)
uv venv .venv && source .venv/bin/activate && uv pip install -e ".[dev]"

# Or with pip
python -m venv .venv && source .venv/bin/activate && pip install -e ".[dev]"

# Setup pre-commit hooks
pre-commit install
pre-commit install --hook-type commit-msg
```

## Development Workflow

```bash
# Testing
make test       # Run tests
make test-cov   # Run tests with coverage

# Code quality
make lint       # Run linting
make type-check # Run type checking
make format     # Format code
make check      # Run all checks

# Building and docs
make build      # Build package
make docs-serve # Serve documentation locally
```

## Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`, `security`

Examples:

- `feat(auth): add custom store ID support`
- `fix: correct typo in README.md`
- `docs(api): update API documentation`

## License

By contributing, you agree that your contributions will be licensed under the project's [Apache License 2.0](LICENSE).
