# syntax=docker/dockerfile:1

# --- Dependencias de Composer ------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist \
        --ignore-platform-req=ext-gd --ignore-platform-req=ext-pdo_mysql
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# --- Aplicación (PHP-FPM) ----------------------------------------------------
FROM php:8.5-fpm-alpine AS app

RUN apk add --no-cache libpng libjpeg-turbo libwebp freetype tzdata \
    && apk add --no-cache --virtual .deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql pcntl \
    && (php -m | grep -qi '^Zend OPcache$' || docker-php-ext-install opcache) \
    && apk del .deps \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -f /usr/local/etc/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/www.conf.default

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-teatro.ini"
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/teatro-entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN chmod 0755 /usr/local/bin/teatro-entrypoint \
    && rm -rf .env tests phpunit.xml storage/logs/*.log bootstrap/cache/*.php \
    && mkdir -p storage/app/public/obras storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

USER www-data
EXPOSE 9000
ENTRYPOINT ["teatro-entrypoint"]
CMD ["app"]

# --- Pruebas automatizadas ----------------------------------------------------
FROM app AS test
USER root
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=www-data:www-data composer.json composer.lock phpunit.xml ./
COPY --chown=www-data:www-data tests ./tests
RUN composer install --no-interaction --no-progress --prefer-dist \
    && touch .env && chown -R www-data:www-data vendor .env
USER www-data
ENTRYPOINT []
CMD ["php", "artisan", "test"]

# --- Servidor web (Nginx sin privilegios) ------------------------------------
FROM nginxinc/nginx-unprivileged:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/cabeceras.conf /etc/nginx/cabeceras.conf
COPY --from=vendor /app/public /var/www/html/public
EXPOSE 8080
