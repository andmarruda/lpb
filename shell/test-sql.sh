#!/bin/bash

set -e

echo "Building SQL Integration Test..."
docker build -f ../config/docker/Dockerfile.sql.test -t lpb-sql-test .

echo ""
echo "Running SQL Integration Test..."
docker run --rm lpb-sql-test

echo ""
echo "SQL Integration Test Completed Successfully!"
