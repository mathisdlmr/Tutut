FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    zip unzip git curl \
    libicu-dev libxml2-dev \
    libzip-dev zlib1g-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libsqlite3-dev libmariadb-dev \
    g++ make autoconf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        intl pdo pdo_mysql mysqli pdo_sqlite \
        xml zip gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY laravel-setup.sh /usr/local/bin/laravel-setup.sh
RUN chmod +x /usr/local/bin/laravel-setup.sh

RUN addgroup --gid 1000 www \
    && adduser --uid 1000 --ingroup www --shell /bin/bash --disabled-password www \
    && chown -R www:www . \
    && chmod -R 775 storage bootstrap/cache

USER www

ENTRYPOINT ["/usr/local/bin/laravel-setup.sh"]
