FROM php:8.5-cli-alpine

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip make

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-progress --no-scripts --no-dev --no-autoloader || true

COPY . .
RUN composer install --no-interaction --no-progress --no-dev \
    && chmod +x bin/aidd-level

ENTRYPOINT ["bin/aidd-level"]
CMD ["evaluate"]
