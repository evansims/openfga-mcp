# OpenFGA MCP Makefile
# Provides commands for development, testing, building, and deployment

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
PACKAGE_DIR := src/openfga-mcp
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

# Colors for terminal output
BOLD := $(shell tput bold)
GREEN := $(shell tput setaf 2)
YELLOW := $(shell tput setaf 3)
RED := $(shell tput setaf 1)
RESET := $(shell tput sgr0)

# ===== PHONY Targets =====
.PHONY: help setup dev test test-cov lint type-check format clean build publish docs docs-serve \
        release release-ci update-lockfile run security check all venv-activate \
        docker-build docker-run docker-push docker-compose-up docker-compose-down docker-compose-logs

# ===== Default Target =====
help:
	@echo "$(BOLD)OpenFGA MCP Development Commands$(RESET)"
	@echo "================================="
	@echo "$(BOLD)Development:$(RESET)"
	@echo "  $(GREEN)setup$(RESET)               - Set up development environment (create venv, install dependencies)"
	@echo "  $(GREEN)venv-activate$(RESET)       - Print command to activate virtual environment"
	@echo "  $(GREEN)dev$(RESET)                 - Install package in development mode"
	@echo "  $(GREEN)run$(RESET)                 - Run the server locally"
	@echo "  $(GREEN)update-lockfile$(RESET)     - Update the uv.lock file"
	@echo ""
	@echo "$(BOLD)Quality Checks:$(RESET)"
	@echo "  $(GREEN)test$(RESET)                - Run tests"
	@echo "  $(GREEN)test-cov$(RESET)            - Run tests with coverage"
	@echo "  $(GREEN)lint$(RESET)                - Run linting checks"
	@echo "  $(GREEN)type-check$(RESET)          - Run type checking"
	@echo "  $(GREEN)format$(RESET)              - Format code"
	@echo "  $(GREEN)security$(RESET)            - Run security checks"
	@echo "  $(GREEN)check$(RESET)               - Run all checks (tests, lint, type-check)"
	@echo "  $(GREEN)all$(RESET)                 - Run all checks including security and formatting"
	@echo ""
	@echo "$(BOLD)Build & Release:$(RESET)"
	@echo "  $(GREEN)clean$(RESET)               - Remove build artifacts and cache directories"
	@echo "  $(GREEN)build$(RESET)               - Build package distributions"
	@echo "  $(GREEN)publish$(RESET)             - Publish package to PyPI"
	@echo "  $(GREEN)docs$(RESET)                - Build documentation"
	@echo "  $(GREEN)docs-serve$(RESET)          - Serve documentation locally"
	@echo "  $(GREEN)release$(RESET)             - Prepare a release (bump version, update changelog)"
	@echo "  $(GREEN)release-ci$(RESET)          - CI-friendly version of release (uses env var RELEASE_TYPE)"
	@echo ""
	@echo "$(BOLD)Docker Commands:$(RESET)"
	@echo "  $(GREEN)docker-build$(RESET)        - Build Docker image"
	@echo "  $(GREEN)docker-run$(RESET)          - Run Docker container"
	@echo "  $(GREEN)docker-push$(RESET)         - Push Docker image to registry"
	@echo "  $(GREEN)docker-compose-up$(RESET)   - Start services with Docker Compose"
	@echo "  $(GREEN)docker-compose-down$(RESET) - Stop services with Docker Compose"
	@echo "  $(GREEN)docker-compose-logs$(RESET) - View logs from Docker Compose services"

# ===== Development Environment =====
$(VENV_DIR):
	@echo "$(BOLD)Creating virtual environment...$(RESET)"
	@$(UV) venv $(VENV_DIR)
	@echo "$(GREEN)Virtual environment created at $(VENV_DIR)$(RESET)"

setup: $(VENV_DIR)
	@echo "$(BOLD)Setting up development environment...$(RESET)"
	@echo "$(YELLOW)Activating virtual environment...$(RESET)"
	@echo "Run 'source $(VENV_ACTIVATE)' to activate the virtual environment"
	@echo "$(YELLOW)Installing dependencies...$(RESET)"
	@$(UV) pip install -e ".[dev,test,docs]"
	@pre-commit install
	@pre-commit install --hook-type commit-msg
	@echo "$(GREEN)Development environment setup complete!$(RESET)"
	@echo "$(YELLOW)To activate the virtual environment, run:$(RESET)"
	@echo "  source $(VENV_ACTIVATE)"
	@echo "$(YELLOW)Or use:$(RESET)"
	@echo "  make venv-activate"

venv-activate: $(VENV_DIR)
	@echo "$(BOLD)To activate the virtual environment, run:$(RESET)"
	@echo "$(GREEN)source $(VENV_ACTIVATE)$(RESET)"
	@# This creates a helper script that can be used with "source" command
	@echo '#!/bin/bash' > activate_venv.sh
	@echo 'source $(VENV_ACTIVATE)' >> activate_venv.sh
	@echo 'echo "$(GREEN)Virtual environment activated!$(RESET)"' >> activate_venv.sh
	@chmod +x activate_venv.sh
	@echo "$(YELLOW)Or run:$(RESET)"
	@echo "$(GREEN)source activate_venv.sh$(RESET)"

dev:
	@echo "$(BOLD)Installing package in development mode...$(RESET)"
	@$(UV) pip install -e "."
	@echo "$(GREEN)Package installed in development mode!$(RESET)"

# ===== Testing & Quality =====
test:
	@echo "$(BOLD)Running tests...$(RESET)"
	@$(PYTEST) $(TESTS_DIR)
	@echo "$(GREEN)Tests passed!$(RESET)"

test-cov:
	@echo "$(BOLD)Running tests with coverage...$(RESET)"
	@$(PYTEST) --cov=$(PACKAGE_DIR) --cov-report=xml --cov-report=term
	@echo "$(GREEN)Tests with coverage completed!$(RESET)"

lint:
	@echo "$(BOLD)Running linting checks...$(RESET)"
	@$(RUFF) check .
	@echo "$(GREEN)Linting passed!$(RESET)"

type-check:
	@echo "$(BOLD)Running type checking...$(RESET)"
	@$(PYRIGHT)
	@echo "$(GREEN)Type checking passed!$(RESET)"

format:
	@echo "$(BOLD)Formatting code...$(RESET)"
	@$(RUFF) format .
	@$(RUFF) check --fix .
	@echo "$(GREEN)Code formatting complete!$(RESET)"

security:
	@echo "$(BOLD)Running security checks...$(RESET)"
	@$(UV) pip install safety
	@safety check
	@echo "$(GREEN)Security checks completed!$(RESET)"

check: test lint type-check
	@echo "$(GREEN)All checks passed!$(RESET)"

all: format check security
	@echo "$(GREEN)All checks and formatting completed successfully!$(RESET)"

# ===== Build & Release =====
clean:
	@echo "$(BOLD)Cleaning build artifacts and cache directories...$(RESET)"
	@rm -rf build/
	@rm -rf dist/
	@rm -rf *.egg-info/
	@rm -rf .pytest_cache/
	@rm -rf .ruff_cache/
	@rm -rf .coverage
	@rm -rf coverage.xml
	@rm -rf htmlcov/
	@find . -type d -name __pycache__ -exec rm -rf {} +
	@echo "$(GREEN)Clean completed!$(RESET)"

build: clean
	@echo "$(BOLD)Building package distributions...$(RESET)"
	@$(UV) build
	@echo "$(GREEN)Build completed! Artifacts available in dist/$(RESET)"

publish: build
	@echo "$(BOLD)Publishing package to PyPI...$(RESET)"
	@$(UV) publish
	@echo "$(GREEN)Package published to PyPI!$(RESET)"

docs:
	@echo "$(BOLD)Building documentation...$(RESET)"
	@$(MKDOCS) build
	@echo "$(GREEN)Documentation built successfully!$(RESET)"

docs-serve:
	@echo "$(BOLD)Serving documentation locally...$(RESET)"
	@$(MKDOCS) serve

release:
	@echo "$(BOLD)Preparing release...$(RESET)"
	@echo "Available version types: patch, minor, major"
	@read -p "Enter version type: " version_type; \
	$(CZ) bump --$$version_type --yes
	@echo "$(GREEN)Release prepared successfully!$(RESET)"

release-ci:
	@echo "$(BOLD)Preparing release with type: $(RELEASE_TYPE)...$(RESET)"
	@if [ -z "$(RELEASE_TYPE)" ]; then \
		echo "$(RED)Error: RELEASE_TYPE environment variable is not set$(RESET)"; \
		echo "Usage: RELEASE_TYPE=patch|minor|major make release-ci"; \
		exit 1; \
	fi
	@$(CZ) bump --$(RELEASE_TYPE) --yes
	@echo "$(GREEN)Release prepared successfully!$(RESET)"

# ===== Utility Commands =====
update-lockfile:
	@echo "$(BOLD)Updating uv.lock file...$(RESET)"
	@$(UV) pip sync --lockfile uv.lock
	@echo "$(GREEN)Lockfile updated successfully!$(RESET)"

run:
	@echo "$(BOLD)Running the server locally...$(RESET)"
	@$(UV) run openfga-mcp-server --verbose

# ===== Docker Commands =====
docker-build:
	@echo "$(BOLD)Building Docker image $(DOCKER_IMAGE_NAME):$(DOCKER_TAG)...$(RESET)"
	@docker build -t $(DOCKER_IMAGE_NAME):$(DOCKER_TAG) .
	@echo "$(GREEN)Docker image built successfully!$(RESET)"

docker-run:
	@echo "$(BOLD)Running Docker container...$(RESET)"
	@if [ -z "$(OPENFGA_API_URL)" ] || [ -z "$(OPENFGA_STORE_ID)" ]; then \
		echo "$(YELLOW)Warning: OPENFGA_API_URL or OPENFGA_STORE_ID environment variables not set$(RESET)"; \
	fi
	@docker run -p 8000:8000 \
		-e OPENFGA_API_URL=$(OPENFGA_API_URL) \
		-e OPENFGA_STORE_ID=$(OPENFGA_STORE_ID) \
		$(DOCKER_IMAGE_NAME):$(DOCKER_TAG)

docker-push: docker-build
	@echo "$(BOLD)Pushing Docker image to registry...$(RESET)"
	@docker tag $(DOCKER_IMAGE_NAME):$(DOCKER_TAG) $(DOCKER_FULL_NAME)
	@docker push $(DOCKER_FULL_NAME)
	@echo "$(GREEN)Docker image pushed successfully to $(DOCKER_FULL_NAME)!$(RESET)"

docker-compose-up:
	@echo "$(BOLD)Starting services with Docker Compose...$(RESET)"
	@if [ -z "$(OPENFGA_API_URL)" ] || [ -z "$(OPENFGA_STORE_ID)" ]; then \
		echo "$(YELLOW)Warning: OPENFGA_API_URL or OPENFGA_STORE_ID environment variables not set$(RESET)"; \
	fi
	@docker-compose up -d --build
	@echo "$(GREEN)Services started successfully!$(RESET)"

docker-compose-down:
	@echo "$(BOLD)Stopping services with Docker Compose...$(RESET)"
	@docker-compose down
	@echo "$(GREEN)Services stopped successfully!$(RESET)"

docker-compose-logs:
	@echo "$(BOLD)Viewing logs from Docker Compose services...$(RESET)"
	@docker-compose logs -f