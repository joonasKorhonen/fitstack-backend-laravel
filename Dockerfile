# ── Build stage ───────────────────────────────────────────────────────────────
FROM php:8.3-cli AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip git curl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

COPY . .
RUN composer run-script post-autoload-dump || true

# ── Production stage ──────────────────────────────────────────────────────────
FROM php:8.3-cli AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=builder /app .

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD sh -c "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"
