# SABITECH WEB · backend (contenedor `back`)
# Etapas:
#   base    PHP 8.4-FPM con las extensiones que necesita Laravel 13
#   vendor  dependencias de Composer sin las de desarrollo
#   dev     base + Composer; el código se monta desde la carpeta del equipo
#   prod    imagen final: código + vendor + Supervisor (PHP-FPM, cola y planificador)

FROM php:8.4-fpm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip supervisor \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath opcache pcntl zip \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-sabitech.ini
WORKDIR /var/www/html

# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
# Las extensiones las tiene la imagen final (base); aquí solo se descargan paquetes.
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --ignore-platform-reqs

# ---------------------------------------------------------------------------
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# En desarrollo se usa el servidor de Laravel (compose.override.yaml monta el código).
CMD ["sh", "-c", "composer install --no-interaction && php artisan serve --host=0.0.0.0 --port=8000"]

# ---------------------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=production APP_DEBUG=false

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY docker/supervisord.conf /etc/supervisor/conf.d/sabitech.conf
COPY docker/entrypoint.sh /usr/local/bin/sabitech-entrypoint

RUN chmod +x /usr/local/bin/sabitech-entrypoint \
    && rm -f .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["sabitech-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
