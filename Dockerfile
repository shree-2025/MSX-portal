# Use the official PHP 8.1 Apache image
FROM php:8.1-apache

# Set environment variables for non-interactive installation
ENV DEBIAN_FRONTEND=noninteractive

# Function to retry failed commands
RUN echo 'retry() { \
    local n=0; \
    local max=3; \
    local delay=5; \
    while true; do \
        "$@" && break || { \
            if [[ $n -lt $max ]]; then \
                ((n++)); \
                echo "Command failed. Attempt $n/$max:"; \
                sleep $delay; \
            else \
                echo "The command has failed after $n attempts." >&2; \
                return 1; \
            fi; \
        }; \
    done; \
}' > /usr/local/bin/retry && \
    chmod +x /usr/local/bin/retry

# Update package list and install basic dependencies
RUN retry apt-get update && retry apt-get install -y --no-install-recommends \
    ca-certificates \
    gnupg \
    apt-transport-https \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# Install system dependencies in smaller batches
RUN retry apt-get update && retry apt-get install -y --no-install-recommends \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN retry apt-get update && retry apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

RUN retry apt-get update && retry apt-get install -y --no-install-recommends \
    zip \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install build dependencies first
RUN retry apt-get update && retry apt-get install -y --no-install-recommends \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl \
    && docker-php-source delete

# Install and enable PHP-FPM
RUN apt-get install -y php8.1-fpm \
    && a2enmod proxy_fcgi setenvif \
    && a2enconf php8.1-fpm

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Generate application key and optimize
RUN php artisan key:generate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Update Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
