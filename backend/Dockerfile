FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && echo "ServerName localhost" > /etc/apache2/conf-enabled/servername.conf \
    && echo "date.timezone=UTC" > /usr/local/etc/php/conf.d/timezone.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-dev --optimize-autoloader || true

COPY . .

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && php artisan package:discover --ansi || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
