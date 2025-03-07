# OpenFGA MCP Makefile
# Provides commands for development, testing, building, and deployment

# ===== Default Goal =====
.DEFAULT_GOAL := help

# ===== Include Optional Local Configuration =====
-include Makefile.local

# ===== Variables =====
SHELL := /bin/bash
PYTHON := python
UV := uv
PYTEST := pytest
RUFF := ruff
PYRIGHT := pyright
MKDOCS := mkdocs
CZ := cz

# Project settings
PROJECT_NAME := openfga-mcp
PACKAGE_DIR := src/openfga_mcp
TESTS_DIR := tests

# Docker settings
DOCKER_IMAGE_NAME ?= openfga-mcp-server
DOCKER_TAG ?= latest
DOCKER_REGISTRY ?= ghcr.io/evansims
DOCKER_FULL_NAME := $(DOCKER_REGISTRY)/$(DOCKER_IMAGE_NAME):$(DOCKER_TAG)

# Environment settings
VENV_DIR := .venv
VENV_ACTIVATE := $(VENV_DIR)/bin/activate
PYTHON_VERSION ?= 3.10

# Build settings
JOBS ?= $(shell nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 1)
# Check if pytest supports parallel execution
PYTEST_SUPPORTS_PARALLEL := $(shell $(PYTEST) --help | grep -q -- "-j, --jobs" && echo 1 || echo 0)
PYTEST_PARALLEL_FLAG := $(if $(filter 1,$(PYTEST_SUPPORTS_PARALLEL)),-j$(JOBS),)

# Verbosity settings
V ?= 0
VERBOSE_FLAG := $(if $(filter 1,$(V)),,@)
SILENT_FLAG := $(if $(filter 0,$(V)),@,)

# Colors for terminal output
BOLD := $(shell tput bold)
GREEN := $(shell tput setaf 2)
YELLOW := $(shell tput setaf 3)
RED := $(shell tput setaf 1)
RESET := $(shell tput sgr0)

# ===== PHONY Targets =====
.PHONY: help setup dev test test-cov lint type-check format clean build publish docs docs-serve \
        release release-ci update run security check all venv in-venv shell repl ipython version \
        docker-build docker-run docker-push docker-compose-up docker-compose-down docker-compose-logs

# ===== Default Target =====
help:
	$(SILENT_FLAG)echo "$(BOLD)OpenFGA MCP Development Commands$(RESET)"
	$(SILENT_FLAG)echo "================================="
	$(SILENT_FLAG)echo "$(BOLD)Development:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)setup$(RESET)               - Set up development environment"
	$(SILENT_FLAG)echo "  $(GREEN)venv$(RESET)                - Show virtual environment instructions"
	$(SILENT_FLAG)echo "  $(GREEN)in-venv$(RESET)             - Run command in virtual environment (CMD=\"command\")"
	$(SILENT_FLAG)echo "  $(GREEN)shell$(RESET)               - Start shell in virtual environment"
	$(SILENT_FLAG)echo "  $(GREEN)repl$(RESET)                - Start Python REPL"
	$(SILENT_FLAG)echo "  $(GREEN)ipython$(RESET)             - Start IPython REPL"
	$(SILENT_FLAG)echo "  $(GREEN)dev$(RESET)                 - Install package in development mode"
	$(SILENT_FLAG)echo "  $(GREEN)run$(RESET)                 - Run the server locally"
	$(SILENT_FLAG)echo "  $(GREEN)update$(RESET)              - Update dependencies"
	$(SILENT_FLAG)echo ""
	$(SILENT_FLAG)echo "$(BOLD)Quality:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)test$(RESET)                - Run tests"
	$(SILENT_FLAG)echo "  $(GREEN)test-cov$(RESET)            - Run tests with coverage"
	$(SILENT_FLAG)echo "  $(GREEN)lint$(RESET)                - Run linting"
	$(SILENT_FLAG)echo "  $(GREEN)type-check$(RESET)          - Run type checking"
	$(SILENT_FLAG)echo "  $(GREEN)format$(RESET)              - Format code"
	$(SILENT_FLAG)echo "  $(GREEN)security$(RESET)            - Run security checks"
	$(SILENT_FLAG)echo "  $(GREEN)check$(RESET)               - Run tests, lint, type-check"
	$(SILENT_FLAG)echo "  $(GREEN)all$(RESET)                 - Run all checks including security"
	$(SILENT_FLAG)echo ""
	$(SILENT_FLAG)echo "$(BOLD)Build & Release:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)clean$(RESET)               - Remove build artifacts"
	$(SILENT_FLAG)echo "  $(GREEN)build$(RESET)               - Build package"
	$(SILENT_FLAG)echo "  $(GREEN)publish$(RESET)             - Publish package to PyPI"
	$(SILENT_FLAG)echo "  $(GREEN)docs$(RESET)                - Build documentation"
	$(SILENT_FLAG)echo "  $(GREEN)docs-serve$(RESET)          - Serve documentation locally"
	$(SILENT_FLAG)echo "  $(GREEN)version$(RESET)             - Display current version"
	$(SILENT_FLAG)echo "  $(GREEN)release$(RESET)             - Prepare a release"
	$(SILENT_FLAG)echo "  $(GREEN)release-ci$(RESET)          - CI-friendly release (RELEASE_TYPE=type)"
	$(SILENT_FLAG)echo ""
	$(SILENT_FLAG)echo "$(BOLD)Docker:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)docker-build$(RESET)        - Build Docker image"
	$(SILENT_FLAG)echo "  $(GREEN)docker-run$(RESET)          - Run Docker container"
	$(SILENT_FLAG)echo "  $(GREEN)docker-push$(RESET)         - Push Docker image to registry"
	$(SILENT_FLAG)echo "  $(GREEN)docker-compose-up$(RESET)   - Start services with Docker Compose"
	$(SILENT_FLAG)echo "  $(GREEN)docker-compose-down$(RESET) - Stop services with Docker Compose"
	$(SILENT_FLAG)echo "  $(GREEN)docker-compose-logs$(RESET) - View Docker Compose logs"
	$(SILENT_FLAG)echo ""
	$(SILENT_FLAG)echo "$(BOLD)Options:$(RESET)"
	$(SILENT_FLAG)echo "  V=1                    - Verbose output"
	$(SILENT_FLAG)echo "  JOBS=N                 - Parallel execution (N jobs)"

# ===== Development Environment =====
$(VENV_DIR):
	$(SILENT_FLAG)echo "$(BOLD)Creating virtual environment...$(RESET)"
	$(VERBOSE_FLAG)$(UV) venv $@
	$(SILENT_FLAG)echo "$(GREEN)Virtual environment created at $@$(RESET)"

setup: $(VENV_DIR)
	$(SILENT_FLAG)echo "$(BOLD)Setting up development environment...$(RESET)"
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && $(UV) pip install -e ".[dev,test,docs]"
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && pre-commit install && pre-commit install --hook-type commit-msg
	$(SILENT_FLAG)echo "$(GREEN)Setup complete!$(RESET) Activate with: $(GREEN)source activate_venv.sh$(RESET)"
	$(SILENT_FLAG)$(MAKE) -s venv

venv: $(VENV_DIR)
	$(SILENT_FLAG)echo "$(BOLD)Virtual Environment:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)source activate_venv.sh$(RESET)  - Activate"
	$(SILENT_FLAG)echo "  $(GREEN)deactivate$(RESET)               - Deactivate"
	$(SILENT_FLAG)echo "  $(YELLOW)Tip:$(RESET) Add to your shell profile: $(GREEN)alias activate-openfga='source $$(pwd)/activate_venv.sh'$(RESET)"

dev:
	$(SILENT_FLAG)echo "$(BOLD)Installing package in development mode...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) pip install -e ."
	$(SILENT_FLAG)echo "$(GREEN)Package installed in development mode!$(RESET)"

# ===== Testing & Quality =====
test:
	$(SILENT_FLAG)echo "$(BOLD)Running tests...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTEST) $(TESTS_DIR) $(PYTEST_PARALLEL_FLAG)"
	$(SILENT_FLAG)echo "$(GREEN)Tests passed!$(RESET)"

test-cov:
	$(SILENT_FLAG)echo "$(BOLD)Running tests with coverage...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTEST) --cov=$(PACKAGE_DIR) --cov-report=xml --cov-report=term $(PYTEST_PARALLEL_FLAG)"
	$(SILENT_FLAG)echo "$(GREEN)Tests with coverage completed!$(RESET)"

lint:
	$(SILENT_FLAG)echo "$(BOLD)Running linting checks...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(RUFF) check ."
	$(SILENT_FLAG)echo "$(GREEN)Linting passed!$(RESET)"

type-check:
	$(SILENT_FLAG)echo "$(BOLD)Running type checking...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYRIGHT)"
	$(SILENT_FLAG)echo "$(GREEN)Type checking passed!$(RESET)"

format:
	$(SILENT_FLAG)echo "$(BOLD)Formatting code...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(RUFF) format ."
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(RUFF) check --fix ."
	$(SILENT_FLAG)echo "$(GREEN)Code formatting complete!$(RESET)"

security:
	$(SILENT_FLAG)echo "$(BOLD)Running security checks...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) pip install safety"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="safety check"
	$(SILENT_FLAG)echo "$(GREEN)Security checks completed!$(RESET)"

check: test lint type-check
	$(SILENT_FLAG)echo "$(GREEN)All checks passed!$(RESET)"

all: format check security
	$(SILENT_FLAG)echo "$(GREEN)All checks and formatting completed successfully!$(RESET)"

# ===== Build & Release =====
clean:
	$(SILENT_FLAG)echo "$(BOLD)Cleaning build artifacts and cache directories...$(RESET)"
	$(VERBOSE_FLAG)rm -rf build/
	$(VERBOSE_FLAG)rm -rf dist/
	$(VERBOSE_FLAG)rm -rf *.egg-info/
	$(VERBOSE_FLAG)rm -rf .pytest_cache/
	$(VERBOSE_FLAG)rm -rf .ruff_cache/
	$(VERBOSE_FLAG)rm -rf .coverage
	$(VERBOSE_FLAG)rm -rf coverage.xml
	$(VERBOSE_FLAG)rm -rf htmlcov/
	$(VERBOSE_FLAG)find . -type d -name __pycache__ -exec rm -rf {} +
	$(SILENT_FLAG)echo "$(GREEN)Clean completed!$(RESET)"

build: clean
	$(SILENT_FLAG)echo "$(BOLD)Building package distributions...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) build"
	$(SILENT_FLAG)echo "$(GREEN)Build completed! Artifacts available in dist/$(RESET)"

publish: build
	$(SILENT_FLAG)echo "$(BOLD)Publishing package to PyPI...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) publish"
	$(SILENT_FLAG)echo "$(GREEN)Package published to PyPI!$(RESET)"

docs:
	$(SILENT_FLAG)echo "$(BOLD)Building documentation...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(MKDOCS) build"
	$(SILENT_FLAG)echo "$(GREEN)Documentation built successfully!$(RESET)"

docs-serve:
	$(SILENT_FLAG)echo "$(BOLD)Serving documentation locally...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(MKDOCS) serve"

version:
	$(SILENT_FLAG)echo "$(BOLD)Current version:$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTHON) -m openfga_mcp version" || \
		echo "$(YELLOW)Version information not available. Package may not be installed.$(RESET)"

release:
	$(SILENT_FLAG)echo "$(BOLD)Preparing release...$(RESET)"
	$(SILENT_FLAG)echo "Available version types: patch, minor, major"
	$(VERBOSE_FLAG)read -p "Enter version type: " version_type; \
	$(MAKE) in-venv CMD="$(CZ) bump --$$version_type --yes"
	$(SILENT_FLAG)echo "$(GREEN)Release prepared successfully!$(RESET)"

release-ci:
	$(SILENT_FLAG)echo "$(BOLD)Preparing release with type: $(RELEASE_TYPE)...$(RESET)"
	$(VERBOSE_FLAG)if [ -z "$(RELEASE_TYPE)" ]; then \
		echo "$(RED)Error: RELEASE_TYPE environment variable is not set$(RESET)"; \
		echo "Usage: RELEASE_TYPE=patch|minor|major make release-ci"; \
		exit 1; \
	fi
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(CZ) bump --$(RELEASE_TYPE) --yes"
	$(SILENT_FLAG)echo "$(GREEN)Release prepared successfully!$(RESET)"

# ===== Utility Commands =====
update:
	$(SILENT_FLAG)echo "$(BOLD)Updating dependencies and lockfile...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) pip compile --upgrade pyproject.toml -o uv.lock"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) pip sync --lockfile uv.lock"
	$(SILENT_FLAG)echo "$(GREEN)Dependencies updated successfully!$(RESET)"

run:
	$(SILENT_FLAG)echo "$(BOLD)Running the server locally...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTHON) -m openfga_mcp main --verbose"

# ===== Docker Commands =====
docker-build:
	$(SILENT_FLAG)echo "$(BOLD)Building Docker image $(DOCKER_IMAGE_NAME):$(DOCKER_TAG)...$(RESET)"
	$(VERBOSE_FLAG)docker build -t $(DOCKER_IMAGE_NAME):$(DOCKER_TAG) .
	$(SILENT_FLAG)echo "$(GREEN)Docker image built successfully!$(RESET)"

docker-run:
	$(SILENT_FLAG)echo "$(BOLD)Running Docker container...$(RESET)"
	$(VERBOSE_FLAG)if [ -z "$(OPENFGA_API_URL)" ] || [ -z "$(OPENFGA_STORE_ID)" ]; then \
		echo "$(YELLOW)Warning: OPENFGA_API_URL or OPENFGA_STORE_ID environment variables not set$(RESET)"; \
	fi
	$(VERBOSE_FLAG)docker run -p 8000:8000 \
		-e OPENFGA_API_URL=$(OPENFGA_API_URL) \
		-e OPENFGA_STORE_ID=$(OPENFGA_STORE_ID) \
		$(DOCKER_IMAGE_NAME):$(DOCKER_TAG)

docker-push: docker-build
	$(SILENT_FLAG)echo "$(BOLD)Pushing Docker image to registry...$(RESET)"
	$(VERBOSE_FLAG)docker tag $(DOCKER_IMAGE_NAME):$(DOCKER_TAG) $(DOCKER_FULL_NAME)
	$(VERBOSE_FLAG)docker push $(DOCKER_FULL_NAME)
	$(SILENT_FLAG)echo "$(GREEN)Docker image pushed successfully to $(DOCKER_FULL_NAME)!$(RESET)"

docker-compose-up:
	$(SILENT_FLAG)echo "$(BOLD)Starting services with Docker Compose...$(RESET)"
	$(VERBOSE_FLAG)if [ -z "$(OPENFGA_API_URL)" ] || [ -z "$(OPENFGA_STORE_ID)" ]; then \
		echo "$(YELLOW)Warning: OPENFGA_API_URL or OPENFGA_STORE_ID environment variables not set$(RESET)"; \
	fi
	$(VERBOSE_FLAG)docker-compose up -d --build
	$(SILENT_FLAG)echo "$(GREEN)Services started successfully!$(RESET)"

docker-compose-down:
	$(SILENT_FLAG)echo "$(BOLD)Stopping services with Docker Compose...$(RESET)"
	$(VERBOSE_FLAG)docker-compose down
	$(SILENT_FLAG)echo "$(GREEN)Services stopped successfully!$(RESET)"

docker-compose-logs:
	$(SILENT_FLAG)echo "$(BOLD)Viewing logs from Docker Compose services...$(RESET)"
	$(VERBOSE_FLAG)docker-compose logs -f

# ===== Virtual Environment Commands =====
shell:
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && exec $$SHELL

repl:
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="python"

ipython:
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="python -m pip install ipython && ipython"

in-venv:
	$(SILENT_FLAG)if [ -z "$(CMD)" ]; then \
		echo "$(RED)Error: No command specified$(RESET)"; \
		echo "Usage: make in-venv CMD=\"your command\""; \
		exit 1; \
	fi
	$(VERBOSE_FLAG)if [ -n "$$VIRTUAL_ENV" ] && [ "$$VIRTUAL_ENV" = "$$(pwd)/$(VENV_DIR)" ]; then \
		$(CMD); \
	else \
		source $(VENV_ACTIVATE) && $(CMD); \
	fi

# ===== Pattern Rules =====
%.py: %.py.in
	$(SILENT_FLAG)echo "$(BOLD)Generating $@ from $<...$(RESET)"
	$(VERBOSE_FLAG)sed -e 's/@VERSION@/$(shell $(MAKE) -s version)/g' $< > $@
	$(SILENT_FLAG)echo "$(GREEN)Generated $@$(RESET)"
