#!/bin/bash
set -e

# Install dependencies
composer install --no-interaction --optimize-autoloader --no-dev

# Set permissions
chmod -R 777 storage/
chmod -R 777 bootstrap/cache/

# Generate application key if not exists
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
