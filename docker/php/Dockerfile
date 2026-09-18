FROM php:8.4-cli-alpine

ARG UID=1000
ARG GID=1000

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache git unzip \
    && install-php-extensions pdo_pgsql pgsql intl zip bcmath pcntl opcache pcov @composer

RUN addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app \
    && mkdir -p /app /home/app/.composer \
    && chown -R app:app /app /home/app

ENV COMPOSER_HOME=/home/app/.composer \
    PATH="/app/vendor/bin:${PATH}"

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

USER app
WORKDIR /app

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
