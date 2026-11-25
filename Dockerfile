# syntax=docker/dockerfile:1

# RYADA application image (issue #36, PR 1: the application stack only).
# The queue worker, Horizon, the scheduler and trusted proxies are PR 2 and PR 3 and
# are deliberately absent here.
#
# `composer install` runs inside the PHP 8.4 stage, which has every extension declared in
# composer.lock. It therefore works WITHOUT the
# `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` flags that the Windows
# host needs for Horizon: pcntl and posix only exist on Linux.

# Supplies the Composer binary only. The dependencies are installed in our own PHP 8.4
# stage so the platform requirements are checked against the real runtime (composer:2
# currently ships PHP 8.5 on Alpine and has neither intl nor bcmath).
FROM composer:2 AS composer

# ---------------------------------------------------------------------------
# php-base: PHP 8.4 FPM plus every extension the application, Filament and the test
# suite need. Shared by the vendor and app stages so the compiles happen once.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS php-base

ENV DEBIAN_FRONTEND=noninteractive

# unzip        -> Composer dist installs
# libfcgi-bin  -> cgi-fcgi, used by the app healthcheck to ping PHP-FPM
# tzdata       -> kept up to date at build time (Africa/Cairo observes DST again, issue #32)
# *-dev        -> headers for intl, zip and gd
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip \
        libfcgi-bin \
        tzdata \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

# The official image already enables: ctype, dom, fileinfo, filter, iconv, json, libxml,
# mbstring, openssl, OPcache, pdo_sqlite, posix, session, simplexml, sqlite3, tokenizer,
# xml, xmlreader, xmlwriter, zlib. OPcache is enabled here; the ini below tunes it.
# pdo_sqlite/sqlite3 are what `php artisan test` uses (phpunit.xml forces :memory:).

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-ryada.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-ryada.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# vendor: only composer.json/composer.lock are in this layer, so editing application code
# never re-downloads dependencies. Dev dependencies stay installed on purpose: this image
# also runs `php artisan test`, Pint and Larastan (PR 3 / CI). A production target without
# dev dependencies is a possible follow-up and is not built here.
# ---------------------------------------------------------------------------
FROM php-base AS vendor

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-scripts \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader

# ---------------------------------------------------------------------------
# app: PHP-FPM runtime, non-root.
# ---------------------------------------------------------------------------
FROM php-base AS app

RUN groupadd --gid 1000 ryada \
    && useradd --uid 1000 --gid ryada --create-home --shell /bin/bash ryada

COPY --chown=ryada:ryada --from=vendor /app/vendor /var/www/html/vendor
COPY --chown=ryada:ryada . /var/www/html

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0755 docker/entrypoint.sh /entrypoint

# Rebuild the optimized autoloader against the final file layout. The Composer scripts
# also run package:discover and filament:upgrade, which publishes the Filament assets into
# public/ (the nginx image copies that public/ later). Everything runs as root here and
# ownership is handed to ryada afterwards.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-interaction \
    && chown -R ryada:ryada storage bootstrap/cache public \
    && chmod -R ug+rwX storage bootstrap/cache

USER ryada

EXPOSE 9000

ENTRYPOINT ["/entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# nginx: the official nginx image plus the static files from the app stage. The storage
# named volume is mounted at /var/www/html/storage at runtime, so the relative
# public/storage symlink created here resolves to the uploaded logos.
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
RUN ln -s ../storage/app/public /var/www/html/public/storage
