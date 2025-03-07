# Release Process

## Overview

The release process is automated using GitHub Actions:

1. Quality checks (tests, linting, type checking)
2. Version bumping based on Conventional Commits
3. Changelog generation
4. GitHub release creation
5. PyPI package publishing

## Prerequisites

- Maintainer access to the GitHub repository
- Repository configured for PyPI trusted publishing

## Performing a Release

1. Go to GitHub repository → Actions tab
2. Select "Release" workflow
3. Click "Run workflow"
4. Select version increment type (patch, minor, major)
5. Choose whether this is a prerelease
6. Optionally specify Python version
7. Click "Run workflow"

## Version Determination

- **Patch**: Bug fixes (`fix:` commits)
- **Minor**: New features (`feat:` commits)
- **Major**: Breaking changes (`BREAKING CHANGE:` in footer)

## Troubleshooting

If the release workflow fails, check:

1. Quality checks (tests, linting, type checking)
2. Version consistency in `pyproject.toml` and `src/openfga-mcp/__init__.py`
3. PyPI trusted publishing configuration
4. Configuration in `.github/config.yml`
5. uv installation and lockfile
