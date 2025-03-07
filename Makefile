.PHONY: help setup dev test lint type-check format clean build docs release

# Default target
help:
	@echo "OpenFGA MCP Development Commands"
	@echo "================================="
	@echo "setup      - Set up development environment (create venv, install dependencies)"
	@echo "dev        - Install package in development mode"
	@echo "test       - Run tests"
	@echo "lint       - Run linting checks"
	@echo "type-check - Run type checking"
	@echo "format     - Format code"
	@echo "clean      - Remove build artifacts and cache directories"
	@echo "build      - Build package distributions"
	@echo "docs       - Build documentation"
	@echo "release    - Prepare a release (bump version, update changelog)"
	@echo "update-lockfile - Update the uv.lock file"

# Set up development environment
setup:
	@echo "Setting up development environment..."
	uv venv .venv
	@echo "Activating virtual environment..."
	@echo "Run 'source .venv/bin/activate' to activate the virtual environment"
	@echo "Installing dependencies..."
	uv pip install -e ".[dev,test,docs]"
	pre-commit install
	pre-commit install --hook-type commit-msg

# Install in development mode
dev:
	uv pip install -e "."

# Run tests
test:
	pytest

# Run tests with coverage
test-cov:
	pytest --cov=src/openfga-mcp --cov-report=xml --cov-report=term

# Run linting
lint:
	ruff check .

# Run type checking
type-check:
	pyright

# Format code
format:
	ruff format .
	ruff check --fix .

# Clean build artifacts and cache directories
clean:
	rm -rf build/
	rm -rf dist/
	rm -rf *.egg-info/
	rm -rf .pytest_cache/
	rm -rf .ruff_cache/
	rm -rf .coverage
	rm -rf coverage.xml
	rm -rf htmlcov/
	find . -type d -name __pycache__ -exec rm -rf {} +

# Build package distributions
build:
	uv build

# Build documentation
docs:
	mkdocs build

# Serve documentation locally
docs-serve:
	mkdocs serve

# Prepare a release (bump version, update changelog)
release:
	@echo "Preparing release..."
	@echo "Available version types: patch, minor, major"
	@read -p "Enter version type: " version_type; \
	cz bump --$$version_type --yes

# Update the uv.lock file
update-lockfile:
	uv pip sync --lockfile uv.lock

# Run the server locally
run:
	uv run openfga-mcp-server --verbose

# Run security checks
security:
	uv pip install safety
	safety check