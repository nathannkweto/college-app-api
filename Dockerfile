# Use PHP 8.2 with Apache
FROM php:8.2-apache

# 1. Install system dependencies
# Added libzip-dev for Excel/CSV support
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
# Added 'zip' (crucial for Excel/CSV uploads)
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# 3. Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# 4. Set working directory
WORKDIR /var/www/html

# 5. Copy application code
COPY . /var/www/html

# Overwrite the default Apache configuration
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# 6. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Install dependencies
# We keep --no-scripts to avoid DB errors during build,
# BUT we must run package:discover manually right after.
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 8. CRITICAL: Register packages (Cloud Tasks) & Cache Config
# This fixes "No connector for [google-cloud-tasks]"
RUN php artisan package:discover
RUN php artisan config:clear

# 9. Set permissions (Consolidated step)
# Doing this AFTER composer install ensures vendor files are also owned by www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# 10. Configure Apache Port
# Cloud Run injects the PORT env var, defaulting to 8080 if missing
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i "s/80/${PORT:-8080}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 11. Expose port
EXPOSE 8080
