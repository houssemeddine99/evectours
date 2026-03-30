# Use PHP 8.4 with Apache
FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev libicu-dev libpq-dev zip unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd intl zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

# 1. Install dependencies first
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 2. Copy the rest of the application
COPY . .
COPY .env.example .env

# 3. Directory setup (no cache:warmup here)
RUN mkdir -p var/cache var/log

# 4. Permissions
RUN chown -R www-data:www-data var/

# 5. Apache config
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

# 6. Startup script - cache:warmup runs at runtime with env vars available
CMD php bin/console cache:clear --env=prod && \
    php bin/console cache:warmup --env=prod && \
    php bin/console doctrine:migrations:migrate --no-interaction && \
    apache2-foreground