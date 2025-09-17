FROM php:8.2-fpm

# зависимости
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev zip unzip git curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# создаём юзера www (UID совпадает с локальным 1000)
RUN useradd -u 1000 -m www

WORKDIR /var/www/html

USER www
