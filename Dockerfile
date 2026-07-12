# syntax=docker/dockerfile:1

# ---- Stage 1: install & optimize composer dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- Stage 2: runtime image ----
FROM php:8.3-fpm-alpine AS base

# Build deps ($PHPIZE_DEPS) dipakai cuma untuk compile redis via PECL,
# lalu dihapus lagi di layer yang sama supaya tidak membengkakkan image final.
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Bawa source + vendor yang sudah di-optimize dari stage sebelumnya (satu layer, bukan dua COPY terpisah)
COPY --from=vendor /app ./

COPY docker/zz-www-tuning.conf /usr/local/etc/php-fpm.d/zz-www-tuning.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

# Healthcheck ini cuma cek FPM master process listening di port-nya,
# BUKAN cek /up route aplikasi (FPM bicara FastCGI, bukan HTTP — tidak bisa di-curl langsung).
# App-level health (route /up) dicek lewat Nginx, bukan di layer container ini.
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD nc -z 127.0.0.1 9000 || exit 1

CMD ["php-fpm"]