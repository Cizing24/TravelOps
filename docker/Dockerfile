FROM node:24-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
RUN npm run build

FROM php:8.4-cli-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip pdo_mysql bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
COPY --from=assets /src/public/build/app.css public/build/app.css
RUN mkdir -p storage/framework/views storage/framework/sessions storage/framework/cache/data bootstrap/cache /run/travelops \
    && touch /run/travelops/.keep \
    && ln -s /run/travelops/keys /app/.env \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-progress \
    && chown -R www-data:www-data /app /run/travelops
USER www-data
ENTRYPOINT ["sh", "/app/docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080", "--no-reload"]
