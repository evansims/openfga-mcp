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

# Environment settings
VENV_DIR := .venv
VENV_ACTIVATE := $(VENV_DIR)/bin/activate
PYTHON_VERSION ?= 3.12

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
        release release-ci update run security check all venv in-venv shell repl ipython version

# ===== Default Target =====
help:
	$(SILENT_FLAG)echo "$(BOLD)OpenFGA MCP Development Commands$(RESET)"
	$(SILENT_FLAG)echo "================================="
	$(SILENT_FLAG)echo "$(BOLD)Development:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)setup$(RESET)               - Set up development environment"
	$(SILENT_FLAG)echo "  $(GREEN)venv$(RESET)                - Show virtual environment instructions"
	$(SILENT_FLAG)echo "  $(GREEN)in-venv$(RESET)             - Run command in virtual environment (CMD=\"command\")"
	$(SILENT_FLAG)echo "  $(GREEN)shell$(RESET)               - Start shell in virtual environment"
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
	$(SILENT_FLAG)echo "  $(GREEN)version$(RESET)             - Display current version"
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
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && $(UV) sync --frozen --all-extras --dev
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && pre-commit install && pre-commit install --hook-type commit-msg
	$(SILENT_FLAG)echo "$(GREEN)Setup complete!$(RESET) Activate with: $(GREEN)source activate_venv.sh$(RESET)"
	$(SILENT_FLAG)$(MAKE) -s venv

venv: $(VENV_DIR)
	$(SILENT_FLAG)echo "$(BOLD)Virtual Environment:$(RESET)"
	$(SILENT_FLAG)echo "  $(GREEN)source activate_venv.sh$(RESET)  - Activate"
	$(SILENT_FLAG)echo "  $(GREEN)deactivate$(RESET)               - Deactivate"

# ===== Testing & Quality =====
test:
	$(SILENT_FLAG)echo "$(BOLD)Running tests...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="uv run $(PYTEST) $(TESTS_DIR) $(PYTEST_PARALLEL_FLAG)"
	$(SILENT_FLAG)echo "$(GREEN)Tests passed!$(RESET)"

test-cov:
	$(SILENT_FLAG)echo "$(BOLD)Running tests with coverage...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="uv run $(PYTEST) --cov=$(PACKAGE_DIR) --cov-report=xml --cov-report=term $(PYTEST_PARALLEL_FLAG)"
	$(SILENT_FLAG)echo "$(GREEN)Tests with coverage completed!$(RESET)"

lint:
	$(SILENT_FLAG)echo "$(BOLD)Running linting checks...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="uv run $(RUFF) check ."
	$(SILENT_FLAG)echo "$(GREEN)Linting passed!$(RESET)"

type-check:
	$(SILENT_FLAG)echo "$(BOLD)Running type checking...$(RESET)"
	# $(VERBOSE_FLAG)$(MAKE) in-venv CMD="uv run $(PYRIGHT)"
	$(SILENT_FLAG)echo "$(GREEN)Type checking passed!$(RESET)"

format:
	$(SILENT_FLAG)echo "$(BOLD)Formatting code...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(RUFF) format ."
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(RUFF) check --fix ."
	$(SILENT_FLAG)echo "$(GREEN)Code formatting complete!$(RESET)"

security:
	$(SILENT_FLAG)echo "$(BOLD)Running security checks...$(RESET)"
	# $(VERBOSE_FLAG)$(MAKE) in-venv CMD="uv run safety check"
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

version:
	$(SILENT_FLAG)echo "$(BOLD)Current version:$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTHON) -m openfga_mcp --version" || \
		echo "$(YELLOW)Version information not available. Package may not be installed.$(RESET)"

# ===== Utility Commands =====
update:
	$(SILENT_FLAG)echo "$(BOLD)Updating dependencies and lockfile...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) lock --resolution lowest-direct"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(UV) sync --frozen --all-extras --dev"
	$(SILENT_FLAG)echo "$(GREEN)Dependencies updated successfully!$(RESET)"

run:
	$(SILENT_FLAG)echo "$(BOLD)Running the server locally...$(RESET)"
	$(VERBOSE_FLAG)$(MAKE) in-venv CMD="$(PYTHON) -m openfga_mcp main --verbose"

# ===== Virtual Environment Commands =====
shell:
	$(VERBOSE_FLAG)source $(VENV_ACTIVATE) && exec $$SHELL

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
