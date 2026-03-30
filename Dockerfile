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

# 2b. Ensure .htaccess exists in public folder for Apache routing
RUN if [ ! -f public/.htaccess ]; then echo '<IfModule mod_rewrite.c>\n\
    RewriteEngine On\n\
    RewriteBase /\n\
    RewriteCond %{HTTP:Authorization} ^(.*)\n\
    RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]\n\
    RewriteCond %{REQUEST_FILENAME} !-f\n\
    RewriteCond %{REQUEST_FILENAME} !-d\n\
    RewriteRule ^(.*)$ index.php [QSA,L]\n\
</IfModule>' > public/.htaccess; fi

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

# 6. Create startup script - cache operations run at runtime with env vars available
RUN echo '#!/bin/bash\n\
set -e\n\
echo "========================================"\n\
echo "Starting Symfony Application"\n\
echo "========================================"\n\
echo ""\n\
echo "[1/4] Clearing cache..."\n\
php bin/console cache:clear --env=prod\n\
echo ""\n\
echo "[2/4] Warming up cache..."\n\
php bin/console cache:warmup --env=prod\n\
echo ""\n\
echo "[3/4] Running migrations..."\n\
php bin/console doctrine:migrations:migrate --no-interaction\n\
echo ""\n\
echo "[4/4] Starting Apache..."\n\
apache2-foreground' > /startup.sh && chmod +x /startup.sh

# Start the application using the startup script
CMD ["/startup.sh"]
