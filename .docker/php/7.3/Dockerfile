FROM php:7.3-fpm-alpine
ENV XDEBUG_VERSION=2.7.2
ENV PHP_CONF_DIR=/usr/local/etc
RUN apk update && apk upgrade && apk add --no-cache ${PHPIZE_DEPS} \
    && pecl install xdebug-${XDEBUG_VERSION} \
    && docker-php-ext-enable xdebug \
    && apk del ${PHPIZE_DEPS} \
    && rm -rf /var/cache/apk/*
COPY network-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/network-socket.pool.conf
COPY restricted-unix-domain-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/restricted-unix-domain-socket.pool.conf
COPY unix-domain-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/unix-domain-socket.pool.conf
WORKDIR /repo