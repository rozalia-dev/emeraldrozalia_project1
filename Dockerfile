FROM php:8.4-fpm-alpine

RUN apk add --no-cache git curl unzip icu-dev libzip-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev libxml2-dev libpq-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql intl zip bcmath gd opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/php/uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

WORKDIR /var/www/html

COPY . .
COPY deploy/docker-entrypoint.sh /usr/local/bin/emerald-rozalia-entrypoint

RUN find . -type d -exec chmod 755 {} + \
 && find . -type f -exec chmod a+r {} + \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chmod 755 /usr/local/bin/emerald-rozalia-entrypoint \
 && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && ln -s /var/www/html/storage/app/public public/storage \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php-fpm -t >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/usr/local/bin/emerald-rozalia-entrypoint"]
CMD ["php-fpm"]
