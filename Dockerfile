FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite expires deflate headers \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri 's!Listen 80!Listen 127.0.0.1:18086!g' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost 127.0.0.1:18086>!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/^User .*/User daemon/' /etc/apache2/apache2.conf \
    && sed -ri 's/^Group .*/Group daemon/' /etc/apache2/apache2.conf

COPY apache-local.conf /etc/apache2/conf-enabled/local-app.conf
COPY php-local.ini /usr/local/etc/php/conf.d/php-local.ini
WORKDIR /var/www/html
