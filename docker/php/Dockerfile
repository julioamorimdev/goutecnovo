FROM php:8.3-apache

RUN a2enmod rewrite headers \
  && apt-get update \
  && apt-get install -y --no-install-recommends \
    libzip-dev \
  && docker-php-ext-install pdo_mysql mysqli \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf


