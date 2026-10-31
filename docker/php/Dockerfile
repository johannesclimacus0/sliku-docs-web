FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install \
        pcntl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install redis-6.3.0 \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000

RUN groupadd -g "${GID}" appgroup \
    && useradd -u "${UID}" -g appgroup -m -s /bin/sh appuser

WORKDIR /app
