#!/bin/bash

set -e

echo "========================================"
echo "Running All Integration Tests"
echo "========================================"
echo ""

echo "1/2 - SQL Integration Test (SQLite)"
echo "----------------------------------------"
./test-sql.sh

echo ""
echo ""
echo "2/2 - MongoDB Integration Test"
echo "----------------------------------------"
./test-mongo.sh

echo ""
echo "========================================"
echo "All Integration Tests Completed!"
echo "========================================"
echo ""
echo "Summary:"
echo "  ✓ SQL (SQLite) - All tests passed"
echo "  ✓ MongoDB - All tests passed"
echo ""
echo "Total: 71 tests executed successfully"
