FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      git \
      unzip \
      libfreetype6-dev \
      libicu-dev \
      libjpeg62-turbo-dev \
      libpng-dev \
      libwebp-dev \
      libxml2-dev \
      libzip-dev \
      mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
      bcmath \
      gd \
      intl \
      opcache \
      pdo_mysql \
      zip \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY .docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/rook.ini

WORKDIR /var/www/html
