FROM php:8.2-fpm-alpine AS base

# A.14: Install required extensions
RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_pgsql \
        zip \
    && apk del --no-cache icu-dev libpq-dev libzip-dev

# A.14: Non-root execution
RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app

WORKDIR /var/www/html

COPY --chown=app:app . .

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader --no-interaction

# A.14: Switch to non-root user
USER app

EXPOSE 9000

CMD ["php-fpm"]
