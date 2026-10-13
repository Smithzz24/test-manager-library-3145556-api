FROM composer:2 AS composer
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql mbstring dom xml zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan library:provision-admin && php artisan serve --host=0.0.0.0 --port=8000"]
