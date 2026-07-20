FROM php:8.2.32-fpm-bookworm@sha256:baed99aec14419f3d4413b3735f0723a0d1d754b9149f46762b662f3e3156284 AS php-runtime

WORKDIR /var/www/html

ENV PICTURES_ROOT=/pictures

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" exif gd intl zip \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-picture-browser.ini
COPY docker/phpinfo.php /var/www/html/phpinfo.php
COPY subfolder/ /var/www/html/hed/

RUN mkdir -p /pictures

FROM httpd:2.4.65-bookworm@sha256:fbc12199ccad031d8047e9c789d65aceee2d14f99ba90664cd3a3996867a5582 AS apache

COPY subfolder/ /var/www/html/hed/
COPY docker/phpinfo.php /var/www/html/phpinfo.php
COPY docker/apache-vhost.conf /usr/local/apache2/conf/extra/picture-browser.conf

RUN sed -i \
        -e 's/^#\(LoadModule proxy_module modules\/mod_proxy.so\)/\1/' \
        -e 's/^#\(LoadModule proxy_fcgi_module modules\/mod_proxy_fcgi.so\)/\1/' \
        -e 's/^#\(LoadModule rewrite_module modules\/mod_rewrite.so\)/\1/' \
        /usr/local/apache2/conf/httpd.conf \
    && printf '\nInclude /usr/local/apache2/conf/extra/picture-browser.conf\n' \
        >> /usr/local/apache2/conf/httpd.conf
