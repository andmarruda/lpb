#!/bin/bash

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

echo "Building MongoDB Integration Test..."
docker build -f config/docker/Dockerfile.mongo.test -t lpb-mongo-test .

echo ""
echo "Running MongoDB Integration Test..."
docker run --rm lpb-mongo-test

echo ""
echo "MongoDB Integration Test Completed Successfully!"
