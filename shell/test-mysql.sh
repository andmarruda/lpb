#!/bin/bash

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

NETWORK=lpb-mysql-test
DATABASE=lpb-mysql-test-db

cleanup() {
    docker rm -f "$DATABASE" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

echo "Building MySQL Integration Test..."
docker build -f config/docker/Dockerfile.sql.test -t lpb-sql-test .
docker build -f config/docker/Dockerfile.mysql.test -t lpb-mysql-test .

echo ""
echo "Starting MySQL 8.4..."
docker network create "$NETWORK" >/dev/null
docker run -d --name "$DATABASE" --network "$NETWORK" \
    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=lpb mysql:8.4 >/dev/null

until docker exec "$DATABASE" mysql -uroot -proot -e 'SELECT 1' lpb >/dev/null 2>&1; do
    sleep 2
done

echo ""
echo "Running MySQL Integration Test..."
docker run --rm --network "$NETWORK" \
    -e DB_CONNECTION=mysql -e LPB_SQL_CONNECTION=mysql \
    -e DB_HOST="$DATABASE" -e DB_DATABASE=lpb -e DB_USERNAME=root -e DB_PASSWORD=root \
    lpb-mysql-test bash -c "php artisan migrate:fresh --force && php artisan test"

echo ""
echo "MySQL Integration Test Completed Successfully!"
