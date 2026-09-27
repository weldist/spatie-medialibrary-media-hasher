ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli-bookworm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# GD is used by the tests to generate fixture images; exif is required by spatie/image
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install gd exif

# Imagick is required by PerceptualHasher
RUN pecl install imagick \
    && docker-php-ext-enable imagick

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

RUN git config --global --add safe.directory /app

WORKDIR /app
