FROM php:8.4-fpm-alpine

RUN apk add --no-cache git curl unzip icu-dev libzip-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev libxml2-dev libpq-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql intl zip bcmath gd opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

CMD ["php-fpm"]
