# Build stage
FROM composer:2 AS builder

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies (including dev for autoload optimization)
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy application code
COPY . .

# Optimize autoloader for production
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Remove dev dependencies for smaller image
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Runtime stage
FROM php:8.3-cli-alpine

# Install runtime dependencies and PHP extensions
RUN apk add --no-cache \
    ca-certificates \
    curl \
    $PHPIZE_DEPS \
    && docker-php-ext-install pcntl \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

# Create non-root user
RUN addgroup -g 1000 -S mcp && \
    adduser -u 1000 -S mcp -G mcp

# Copy application from builder
WORKDIR /app
COPY --from=builder --chown=mcp:mcp /app /app

# Switch to non-root user
USER mcp

# Default environment variables
# Use host.docker.internal to connect to host machine's OpenFGA instance
ENV OPENFGA_MCP_API_URL="http://host.docker.internal:8080" \
    OPENFGA_MCP_TRANSPORT="stdio" \
    OPENFGA_MCP_TRANSPORT_HOST="0.0.0.0" \
    OPENFGA_MCP_TRANSPORT_PORT="9090" \
    OPENFGA_MCP_TRANSPORT_JSON="false" \
    OPENFGA_MCP_API_READONLY="false" \
    OPENFGA_MCP_API_RESTRICT="false"

# Expose HTTP port (only used when OPENFGA_MCP_TRANSPORT=http)
EXPOSE 9090

# Health check for HTTP transport
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD if [ "$OPENFGA_MCP_TRANSPORT" = "http" ]; then \
            curl -f http://localhost:${OPENFGA_MCP_TRANSPORT_PORT:-9090}/ || exit 1; \
        else \
            exit 0; \
        fi

# Run the MCP server
ENTRYPOINT ["php", "src/Server.php"]