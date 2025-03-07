# Release Process

This document describes the release process for the OpenFGA MCP project.

## Overview

The release process is fully automated using GitHub Actions. It follows these steps:

1. Quality checks (tests, linting, type checking) using the project's existing workflows
2. Version bumping based on Conventional Commits
3. Changelog generation
4. GitHub release creation
5. PyPI package publishing
6. Notification

## Prerequisites

To perform a release, you need:

1. Maintainer access to the GitHub repository
2. The repository must be configured for PyPI trusted publishing

## Performing a Release

1. Go to the GitHub repository
2. Navigate to the "Actions" tab
3. Select the "Release" workflow
4. Click "Run workflow"
5. Select the version increment type (patch, minor, major)
6. Choose whether this is a prerelease
7. Optionally, specify a Python version (defaults to the version in `.github/config.yml`)
8. Click "Run workflow"

The workflow will automatically:

- Run all tests, linting, and type checking using the project's existing workflows
- Bump the version in `pyproject.toml` and `src/openfga-mcp/__init__.py`
- Generate a changelog based on commit messages
- Create a GitHub release with the changelog as release notes
- Build and publish the package to PyPI
- Output a notification with links to the GitHub release and PyPI package

## Workflow Architecture

The release workflow is designed to be modular and reuse existing workflows:

1. **Configuration**: Uses the reusable `load-config` workflow to load shared configuration
2. **Test Workflow**: Runs the project's test suite
3. **Lint Workflow**: Checks code style and formatting
4. **Type-Check Workflow**: Verifies type annotations
5. **Prepare Release**: Bumps version, generates changelog, and creates GitHub release
6. **Publish**: Builds and publishes the package to PyPI
7. **Notify**: Uses the reusable `notify` workflow to send notifications

This architecture ensures consistency between regular CI checks and release checks, and makes the workflow more maintainable.

## Optimized Build Process with uv

The project uses [uv](https://github.com/astral-sh/uv) for package management and building:

1. **Performance**: uv is significantly faster than pip for package installation and building
2. **Deterministic Builds**: Uses uv.lock file for reproducible builds
3. **Efficient Caching**: Optimized caching of dependencies across workflow runs
4. **Consistency**: Ensures consistent dependency resolution

Key optimizations in our build process:

1. **Lockfile-Based Installs**: Dependencies are installed using the uv.lock file when available
2. **Direct uv Build**: Uses `uv build` instead of `python -m build` for faster builds
3. **Automatic Lockfile Updates**: A dedicated workflow updates the lockfile when dependencies change
4. **Optimized Caching**: Cache keys include both pyproject.toml and uv.lock for precise caching

All workflows use the Python setup composite action, which:

- Installs uv automatically
- Sets up appropriate caching for uv
- Uses uv for all package installations
- Detects and uses the lockfile when available

## Reusable Workflows and Actions

The project uses several reusable components to reduce code duplication:

1. **load-config**: Loads configuration from `.github/config.yml` and provides it to other workflows

   - Accepts an optional Python version override
   - Outputs Python version, package name, and default branch
   - Used by both CI and release workflows

2. **notify**: Sends notifications with customizable messages and details

   - Accepts a main message, additional details, and success status
   - Used for release notifications and CI completion notifications

3. **python-setup**: A composite action for setting up Python environments
   - Handles Python installation, uv installation, and dependency caching
   - Provides consistent Python and uv setup across workflows
   - Installs dependencies using uv for better performance
   - Supports lockfile-based installations for deterministic builds

This approach ensures that all workflows use consistent logic and reduces maintenance overhead.

## Centralized Configuration

The project uses a centralized configuration approach:

1. Shared configuration values are stored in `.github/config.yml`
2. This includes the Python version, package name, and other settings
3. Workflows read from this configuration to ensure consistency
4. The Python version can be overridden at runtime if needed

To change the default Python version for all workflows, simply update the value in `.github/config.yml`.

## Version Determination

The version increment is determined by:

- **Patch**: For bug fixes (`fix:` commits)
- **Minor**: For new features (`feat:` commits)
- **Major**: For breaking changes (commits with `BREAKING CHANGE:` in the footer)

You can also manually select the version increment type when triggering the workflow.

## Troubleshooting

If the release workflow fails, check:

1. **Quality Checks**: Ensure all tests, linting, and type checking pass
2. **Version Mismatch**: Verify that the version in `pyproject.toml` matches the version in `src/openfga-mcp/__init__.py`
3. **PyPI Publishing**: Check that the repository is properly configured for PyPI trusted publishing
4. **Configuration**: Verify that `.github/config.yml` exists and contains the expected values
5. **uv Installation**: Check if there were any issues with installing or using uv
6. **Lockfile**: Verify that the uv.lock file is up-to-date and valid

## PyPI Trusted Publishing Setup

To set up PyPI trusted publishing:

1. Create a PyPI API token with the "Trusted Publisher" role
2. Add the token to the GitHub repository secrets as `PYPI_API_TOKEN`
3. Configure the PyPI project to allow publishing from the GitHub repository

For more details, see the [PyPI documentation on trusted publishing](https://docs.pypi.org/trusted-publishers/).
