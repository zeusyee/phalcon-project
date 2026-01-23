FROM mileschou/phalcon:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev

# Install additional PHP extensions
RUN docker-php-ext-install pdo_mysql gd

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock* /var/www/html/

# Install dependencies
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application files
COPY . /var/www/html

# Run composer again to ensure autoload is updated
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
