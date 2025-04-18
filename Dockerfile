# Use the official PHP 8.3 FPM image
FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    libpq-dev \
    zip \
    libzip-dev \
    procps \
    vim \
    libxml2-dev \
    && echo 'alias sf="php bin/console"' >> ~/.bashrc

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ADD . /var/www/core
WORKDIR /var/www/core

RUN /usr/local/bin/composer install --no-interaction --no-ansi --prefer-dist --optimize-autoloader

RUN echo "Build completed successfully"