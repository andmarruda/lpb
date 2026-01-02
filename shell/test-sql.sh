#!/bin/bash

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

echo "Building SQL Integration Test..."
docker build -f config/docker/Dockerfile.sql.test -t lpb-sql-test .

echo ""
echo "Running SQL Integration Test..."
docker run --rm lpb-sql-test

echo ""
echo "SQL Integration Test Completed Successfully!"
