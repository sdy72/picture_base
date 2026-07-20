FROM composer:2.9.5@sha256:698d3801b2a622ace460c4743c781282fcbcb733a4cbf8b31c44731e846585e8 AS composer

FROM php:8.5.4-apache-bookworm@sha256:621abbc4602fc34ddc78144cd4c672729bb547d466ef56276248a78bd537576d

WORKDIR /var/www/html

ENV PICTURES_ROOT=/pictures

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
COPY src/ src/

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --classmap-authoritative

COPY public/ public/
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite \
    && mkdir -p /pictures
