# Use PHP 8.2 with Apache
FROM php:8.2-apache

# 1. Install system dependencies (Includes libpq-dev for Postgres)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \

# 2. Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Install PHP extensions (Postgres + Others)
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# 4. Enable Apache mod_rewrite
RUN a2enmod rewrite headers


# 5. Set working directory
WORKDIR /var/www/html

# 6. Copy application code
COPY . /var/www/html

# Overwrite the default Apache configuration with our custom one
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# This must run AFTER the COPY commands
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 7. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 8. Install dependencies
# CRITICAL FIX: Added --ignore-platform-reqs to prevent build failure
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 9. Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 10. Configure Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN sed -i "s/80/${PORT:-8080}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 11. Expose port
EXPOSE 8080
