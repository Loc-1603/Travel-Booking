#!/usr/bin/env bash
set -e

echo "Deploying backend..."

composer install --no-interaction --prefer-dist --optimize-autoloader
cp .env.example .env || true
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deploy complete."
