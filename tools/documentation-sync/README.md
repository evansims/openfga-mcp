# OpenFGA Documentation Sync Tool

A standalone PHP tool for fetching and compiling OpenFGA documentation from various GitHub repositories into consolidated markdown files for LLM consumption.

## Features

- Fetches documentation from multiple OpenFGA SDK repositories
- Compiles scattered documentation into single markdown files
- Preserves heading hierarchy and source traceability
- Fixes relative links and image URLs
- Supports GitHub API authentication to avoid rate limits
- Can be run manually or automated via CI/CD

## Installation

```bash
cd tools/documentation-sync
composer install
```

## Usage

### Basic Usage

Sync all documentation to the default `docs/` directory:

```bash
php sync.php -v
```

### Sync Specific Sources

Sync only specific documentation sources:

```bash
php sync.php -s OPENFGA_DOCS,PHP_SDK -v
```

### Custom Output Directory

```bash
php sync.php -o /path/to/output -v
```

### With GitHub Token

To avoid rate limiting, use a GitHub personal access token:

```bash
php sync.php -t ghp_xxxxxxxxxxxx -v

# Or via environment variable
GITHUB_TOKEN=ghp_xxxxxxxxxxxx php sync.php -v
```

### Using Composer Scripts

```bash
composer sync           # Basic sync
composer sync:verbose   # Verbose output
composer sync:all      # Sync all to ../../docs with verbose output
```

## Source Mapping

| Source | Repository | Output File |
|--------|------------|-------------|
| OPENFGA_DOCS | openfga/openfga.dev/docs/content/** | docs/OPENFGA_DOCS.md |
| PYTHON_SDK | openfga/python-sdk/README.md | docs/PYTHON_SDK.md |
| JAVA_SDK | openfga/java-sdk/README.md | docs/JAVA_SDK.md |
| JS_SDK | openfga/js-sdk/README.md | docs/JS_SDK.md |
| DOTNET_SDK | openfga/dotnet-sdk/README.md | docs/DOTNET_SDK.md |
| GO_SDK | openfga/go-sdk/README.md | docs/GO_SDK.md |
| PHP_SDK | evansims/openfga-php/docs/** + README.md | docs/PHP_SDK.md |
| LARAVEL_SDK | evansims/openfga-laravel/docs/** + README.md | docs/LARAVEL_SDK.md |

## Automation

### GitHub Actions Example

```yaml
name: Sync OpenFGA Documentation

on:
  schedule:
    - cron: '0 0 * * 0'  # Weekly on Sunday
  workflow_dispatch:

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Install dependencies
        working-directory: tools/documentation-sync
        run: composer install
      
      - name: Sync documentation
        working-directory: tools/documentation-sync
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: php sync.php -v -o ../../docs
      
      - name: Commit changes
        run: |
          git config --local user.email "action@github.com"
          git config --local user.name "GitHub Action"
          git add docs/
          git diff --staged --quiet || git commit -m "chore: update OpenFGA documentation"
          git push
```

### Cron Job Example

```bash
# Add to crontab for weekly sync
0 0 * * 0 cd /path/to/openfga-mcp/tools/documentation-sync && GITHUB_TOKEN=ghp_xxx php sync.php -o ../../docs
```

## Requirements

- PHP 8.1 or higher
- Composer
- Internet connection for fetching from GitHub

## Rate Limiting

GitHub API has rate limits:
- **Without authentication**: 60 requests per hour
- **With authentication**: 5,000 requests per hour

Always use a GitHub token when syncing large documentation sets or running frequently.

## Output Format

Each compiled documentation file includes:
- Header with source repository and generation timestamp
- Source file paths as HTML comments for traceability
- Adjusted heading levels for proper hierarchy
- Fixed relative links pointing to GitHub
- Fixed image URLs using raw.githubusercontent.com

## Troubleshooting

### Rate Limit Exceeded

If you see rate limit errors, provide a GitHub token:

```bash
GITHUB_TOKEN=your_token_here php sync.php -v
```

### SSL Certificate Issues

If you encounter SSL errors, ensure your system's CA certificates are up to date:

```bash
# On Ubuntu/Debian
sudo apt-get update && sudo apt-get install ca-certificates

# On macOS
brew install ca-certificates
```

### Memory Issues

For large documentation sets, increase PHP memory limit:

```bash
php -d memory_limit=512M sync.php -v
```