FROM php:8.3-fpm-alpine

# Update package cache
RUN apk update

# Install build dependencies (needed for PHP extensions)
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make \
    postgresql-dev

# Install system dependencies
RUN apk add --no-cache \
    libpq \
    nginx \
    supervisor \
    git \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql

# Remove build dependencies to reduce image size
RUN apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf

# Configure PHP-FPM
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Create required directories
RUN mkdir -p /var/www/html/logs /run/nginx

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
