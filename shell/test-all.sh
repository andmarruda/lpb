#!/bin/bash

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "========================================"
echo "Running All Integration Tests"
echo "========================================"
echo ""

echo "1/3 - SQL Integration Test (SQLite)"
echo "----------------------------------------"
"$SCRIPT_DIR/test-sql.sh"

echo ""
echo ""
echo "2/3 - SQL Integration Test (MySQL)"
echo "----------------------------------------"
"$SCRIPT_DIR/test-mysql.sh"

echo ""
echo ""
echo "3/3 - MongoDB Integration Test"
echo "----------------------------------------"
"$SCRIPT_DIR/test-mongo.sh"

echo ""
echo "========================================"
echo "All Integration Tests Passed"
echo "========================================"
echo "  ✓ SQL (SQLite)"
echo "  ✓ SQL (MySQL)"
echo "  ✓ MongoDB"
