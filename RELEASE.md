# Release Process

## Bumping Dependencies

1. Change dependency version in `pyproject.toml`
2. Upgrade lock with `uv lock --resolution lowest-direct`

## Major or Minor Release

1. Create a Pull Request with a release branch name of `release/vX.Y.Z` where `X.Y.Z` is the version.
2. Include a summary of the changes in the PR description.
3. Once approved and merged, the release will automatically be created.
