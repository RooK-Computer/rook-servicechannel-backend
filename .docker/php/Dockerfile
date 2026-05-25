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
COPY .docker/php/docker-entrypoint.sh /usr/local/bin/rook-docker-entrypoint

RUN chmod +x /usr/local/bin/rook-docker-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["rook-docker-entrypoint"]
CMD ["apache2-foreground"]
