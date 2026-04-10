FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get Composer (pinned version)
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copy composer files first for better layer caching
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install dependencies (scripts enabled so package:discover runs)
RUN composer install --optimize-autoloader --no-dev

# Copy application files with proper ownership
COPY --chown=www-data:www-data . /var/www/html

# Publish Sanctum configuration
RUN php artisan vendor:publish --tag=sanctum-config --no-interaction || true

# Copy entrypoint script for Docker secrets support (ensure it's executable)
RUN chmod +x /var/www/html/docker/app/entrypoint.sh

# Set permissions on storage and bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear compiled files and optimize
RUN composer clear-compiled

# Create psysh directory for tinker
RUN mkdir -p /var/www/.config/psysh && chown -R www-data:www-data /var/www/.config

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000
EXPOSE 9000

# Entrypoint handles Docker secrets, then runs php-fpm
ENTRYPOINT ["/var/www/html/docker/app/entrypoint.sh"]

# Switch to www-data user and start PHP-FPM
USER www-data
CMD ["php-fpm"]
