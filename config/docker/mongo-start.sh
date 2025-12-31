#!/bin/bash

set -e

mongod --fork --logpath /var/log/mongodb.log --dbpath /data/db

echo "Waiting for MongoDB to start..."
until mongosh --eval "db.adminCommand('ping')" > /dev/null 2>&1; do
  sleep 1
done

cd /app/laravel-test

php artisan migrate --force

php artisan test
