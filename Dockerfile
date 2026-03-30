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

# 1. Install dependencies first (Fast caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 2. Copy the rest of the application
COPY . .

# 3. ENVIRONMENT & DIRECTORY SETUP
RUN echo "APP_ENV=prod" > .env && composer dump-env prod \
    && mkdir -p var/cache var/log

# 4. LIGHTWEIGHT PERMISSIONS
# Instead of chown-ing the whole folder (slow), we only chown what Symfony NEEDS to write to.
RUN chown -R www-data:www-data var/

# 5. CACHE WARMUP
# We do this as root so it has full permissions, then fix owner once more.
RUN php bin/console cache:warmup --env=prod \
    && chown -R www-data:www-data var/

# 6. APACHE CONFIG
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

# 7. STARTUP SCRIPT
# This runs migrations and then starts Apache.
CMD php bin/console doctrine:migrations:migrate --no-interaction && apache2-foreground