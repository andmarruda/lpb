#!/bin/bash

set -e
mongod --fork --logpath /var/log/mongodb.log --dbpath /data/db --noauth

echo "Waiting for MongoDB to start..."
sleep 3

cd /app/laravel-test

php artisan test
