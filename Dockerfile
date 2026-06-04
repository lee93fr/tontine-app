# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────
# Stage 1 — Build des assets front (Vite / Tailwind)
# ─────────────────────────────────────────────────────────────
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

# ─────────────────────────────────────────────────────────────
# Stage 2 — Image applicative PHP (php-fpm + nginx)
# ─────────────────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm AS app

# Dépendances système + extensions PHP nécessaires :
#   pdo_pgsql / pgsql -> PostgreSQL
#   gmp / bcmath      -> minishlink/web-push (VAPID)
#   mbstring          -> symfony/string, Laravel
#   intl              -> symfony/string
#   zip, opcache, pcntl
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        unzip \
        git \
        curl \
        libpq-dev \
        libonig-dev \
        libgmp-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        gmp \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installer les dépendances PHP (sans dev) en s'appuyant sur le cache Docker
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Copier le code applicatif
COPY . .

# Copier les assets compilés depuis le stage Node
COPY --from=assets /app/public/build ./public/build

# Finaliser l'autoload optimisé
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Configuration nginx / php / supervisor / entrypoint
COPY docker/nginx.conf       /etc/nginx/sites-available/default
COPY docker/opcache.ini      /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
