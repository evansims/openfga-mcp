# Docker-based Development for OpenFGA MCP

This guide explains how to run all Makefile tooling inside Docker instead of locally.

## Prerequisites

- Docker installed on your system
- Docker Compose (optional, for running services)

## Getting Started

1. Build the development Docker image:

```bash
make -f Makefile.docker dev-image
```

2. Set up the development environment in Docker:

```bash
make -f Makefile.docker docker-setup
```

## Available Commands

All commands from the original Makefile are available with a `docker-` prefix:

```bash
# Run tests in Docker
make -f Makefile.docker docker-test

# Format code in Docker
make -f Makefile.docker docker-format

# Run the server in Docker
make -f Makefile.docker docker-run
```

See the full list of available commands:

```bash
make -f Makefile.docker help
```

## Running Arbitrary Make Commands

You can run any command from the original Makefile in Docker:

```bash
make -f Makefile.docker docker-make CMD="your-command"
```

For example:

```bash
make -f Makefile.docker docker-make CMD="test PYTEST_ARGS='-xvs tests/test_specific.py'"
```

## Getting a Shell in the Docker Container

To get an interactive shell in the Docker container:

```bash
make -f Makefile.docker docker-shell
```

## Using Docker Compose

The project includes a `docker-compose.yml` file for running services:

```bash
# Start services
make docker-compose-up

# Stop services
make docker-compose-down

# View logs
make docker-compose-logs
```

## Benefits of Docker-based Development

1. **Consistent Environment**: Everyone uses the same development environment
2. **No Local Dependencies**: No need to install Python, uv, or other tools locally
3. **Isolation**: Development work is isolated from your local system
4. **CI/CD Parity**: Development environment matches CI/CD environment

## Troubleshooting

### Permissions Issues

If you encounter permissions issues with mounted volumes:

```bash
# Add this to your docker-make command
docker run --rm -it \
    -v $(PWD):/app \
    -w /app \
    -u $(id -u):$(id -g) \
    $(DOCKER_FULL_DEV_NAME) \
    make $(CMD)
```

### Performance on macOS/Windows

For better performance on macOS/Windows, consider using Docker volume caching:

```bash
docker run --rm -it \
    -v $(PWD):/app:cached \
    -w /app \
    $(DOCKER_FULL_DEV_NAME) \
    make $(CMD)
```
