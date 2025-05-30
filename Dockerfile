# Generated by https://smithery.ai. See: https://smithery.ai/docs/config#dockerfile
FROM python:3.12-slim

WORKDIR /app

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends gcc build-essential \
    && rm -rf /var/lib/apt/lists/*

# Copy the project files
COPY . .

# Install the package using pip
RUN pip install --no-cache-dir .

# Expose any necessary ports if applicable (not strictly required for stdio based MCP)

# Set the default command. The command here uses dummy arguments; the actual parameters will be provided via Smithery config at runtime.
CMD ["openfga-mcp", "--openfga_url", "http://127.0.0.1:8080", "--openfga_store", "store-id", "--openfga_model", "model-id"]
