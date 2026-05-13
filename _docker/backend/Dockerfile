FROM php:8.4-fpm

RUN apt-get update && \
 apt-get install -y --no-install-recommends \
 zlib1g-dev \
 g++ \
 git \
 libicu-dev \
 zip \
 libzip-dev \
 unzip && \
 docker-php-ext-install intl pdo pdo_mysql zip && \
 rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV PATH="/var/www/html/bin:/var/www/html/vendor/bin:${PATH}"

CMD ["php-fpm"]
