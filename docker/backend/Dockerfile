FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        libpq \
        postgresql-client \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpq-dev \
        linux-headers \
    && docker-php-ext-install pcntl pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
