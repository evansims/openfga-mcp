# Use a Python image with uv pre-installed
FROM ghcr.io/astral-sh/uv:python3.12-bookworm-slim AS builder

# Install the project into `/app`
WORKDIR /app

# Enable bytecode compilation
ENV UV_COMPILE_BYTECODE=1

# Copy from the cache instead of linking since it's a mounted volume
ENV UV_LINK_MODE=copy

# Copy Makefile and dependency files first for better layer caching
COPY Makefile pyproject.toml uv.lock ./

# Copy source code
COPY src/ ./src/

# Install the project's dependencies using the Makefile and lockfile
RUN --mount=type=cache,target=/root/.cache/uv \
    make update-lockfile && \
    uv pip install -e "." --no-dev

# Final image
FROM python:3.12-slim-bookworm

# Add metadata labels
LABEL org.opencontainers.image.title="OpenFGA MCP Server"
LABEL org.opencontainers.image.description="Model Context Protocol server for OpenFGA"
LABEL org.opencontainers.image.source="https://github.com/evansims/openfga-mcp"
LABEL org.opencontainers.image.licenses="Apache-2.0"

# Install only the necessary dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends git curl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Create a non-root user to run the application
RUN useradd -m app

WORKDIR /app

# Copy from builder stage
COPY --from=builder /root/.local /root/.local
COPY --from=builder /app /app

# Set environment variables
ENV PATH="/app/.venv/bin:$PATH" \
    PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1

# Default configuration (can be overridden at runtime)
ENV OPENFGA_API_URL="" \
    OPENFGA_STORE_ID=""

# Switch to non-root user
USER app

# Expose the default MCP server port
EXPOSE 8000

# Health check to verify the server is running
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Use the correct entry point from pyproject.toml
ENTRYPOINT ["openfga-mcp-server"]
