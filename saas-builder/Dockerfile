# SaaS Builder for Render (or any Docker host). Build from the repo root, because the builder
# installs Smart Core from ../smart-core:  docker build -f saas-builder/Dockerfile .
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY smart-core ./smart-core
COPY saas-builder ./saas-builder

WORKDIR /app/saas-builder
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/app/generated storage/framework/cache storage/framework/sessions storage/framework/views database \
    && chmod +x docker-start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/saas-builder/database/database.sqlite

EXPOSE 8000
CMD ["./docker-start.sh"]
