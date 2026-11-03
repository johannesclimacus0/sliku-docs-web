FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN groupadd -g "${GID}" appgroup \
    && useradd -u "${UID}" -g appgroup -m -s /bin/sh appuser

WORKDIR /app
