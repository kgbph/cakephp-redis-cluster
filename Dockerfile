FROM "php:7.4-fpm"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG PHP_MODE

RUN mv "$PHP_INI_DIR/php.ini-$PHP_MODE" "$PHP_INI_DIR/php.ini" && \
    apt-get update -y && \
    apt-get install -y libicu-dev unzip && \
    yes '' | pecl install redis && \
    docker-php-ext-enable redis && \
    docker-php-ext-install intl
