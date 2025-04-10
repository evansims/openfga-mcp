#!/bin/bash

# Read container name from env var, fallback to default
CONTAINER_NAME=${FGA_CONTAINER_NAME:-openfga_test_fallback}

docker pull openfga/openfga
# Use standard quoting for the variable in the command
docker run -d -p 8080:8080 -p 8081:8081 -p 3000:3000 --name "${CONTAINER_NAME}" openfga/openfga run
