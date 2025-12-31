#!/bin/bash

set -e

echo "Building MongoDB Integration Test..."
docker build -f ../config/docker/Dockerfile.mongo.test -t lpb-mongo-test .

echo ""
echo "Running MongoDB Integration Test..."
docker run --rm lpb-mongo-test

echo ""
echo "MongoDB Integration Test Completed Successfully!"
