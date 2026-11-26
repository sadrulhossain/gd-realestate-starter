# Production PHP image: code and vendor baked in (no bind mounts), opcache without timestamp checks, no dev packages.
# Build context: the repository root (docker-compose.prod.yml).
ARG PHP_VERSION
ARG COMPOSER_VERSION
FROM composer:${COMPOSER_VERSION} AS composer

# opcache is built into PHP 8.5 (no extension to install); docker/prod/opcache.ini tunes it.
FROM php:${PHP_VERSION}-fpm-alpine
ARG PHPREDIS_VERSION
ARG UPLOADS_CONTAINER_PATH=/var/www/uploads

# libxml2 fix (CVE-2026-86144) that the base image does not ship yet; see docs/SECURITY.md.
RUN apk upgrade --no-cache libxml2

RUN apk add --no-cache libpq unzip libwebp libjpeg-turbo libpng \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS postgresql-dev libwebp-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-webp --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql pcntl \
 && printf '\n\n\n\n\n\n\n' | pecl install redis-${PHPREDIS_VERSION} \
 && docker-php-ext-enable redis \
 && apk del .build

COPY docker/php/security.ini $PHP_INI_DIR/conf.d/zz-security.ini
COPY docker/prod/opcache.ini $PHP_INI_DIR/conf.d/zz-opcache.ini
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY api/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
 && rm -f /usr/bin/composer \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs ${UPLOADS_CONTAINER_PATH} \
 && chown -R www-data:www-data storage bootstrap/cache ${UPLOADS_CONTAINER_PATH}

COPY docker/prod/php-start.sh /usr/local/bin/php-start.sh
RUN chmod +x /usr/local/bin/php-start.sh
CMD ["php-start.sh"]
