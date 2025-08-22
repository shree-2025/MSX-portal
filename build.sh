#!/bin/bash
set -e

# Update and install system dependencies
echo "Updating system packages..."
apt-get update

# Install required system packages
echo "Installing system dependencies..."
apt-get install -y --no-install-recommends \
    build-essential \
    libssl-dev \
    libpcre3 \
    libpcre3-dev \
    zlib1g \
    zlib1g-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    pkg-config \
    libwebp-dev

# Install Composer dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader --no-dev

# Set directory permissions
echo "Setting up permissions..."
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Generate application key if not exists
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Clear and optimize application
echo "Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force
