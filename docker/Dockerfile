# Image aplikasi Luwes untuk PC server lokal (offline).
# Dipakai oleh docker-compose.yml: service "app" (web) dan "scheduler" (cron Laravel, termasuk sinkron DB).

# ---- 1. Build asset Vite (CSS/JS) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# --legacy-peer-deps: package-lock.json dibuat dengan opsi ini (vite 4 + @vitejs/plugin-react 2)
RUN npm ci --no-audit --no-fund --legacy-peer-deps
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build

# ---- 2. PHP 8.2 + Apache ----
# PHP 8.2: beberapa paket di composer.lock belum mendukung PHP 8.3.
FROM php:8.2-apache

ENV TZ=Asia/Jakarta \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip curl tzdata \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-luwes.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN echo "ServerName localhost" > /etc/apache2/conf-enabled/servername.conf

WORKDIR /var/www/html

# Dependency PHP dulu (layer ter-cache selama composer.lock tidak berubah)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/luwes-entrypoint
RUN chmod +x /usr/local/bin/luwes-entrypoint

ENTRYPOINT ["luwes-entrypoint"]
CMD ["apache2-foreground"]
