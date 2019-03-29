FROM php:7.1-fpm-alpine
ENV PHP_CONF_DIR=/usr/local/etc
RUN apk update && apk upgrade && apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-2.7.0 \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS
COPY network-socket.pool.conf $PHP_CONF_DIR/php-fpm.d/network-socket.pool.conf
COPY restricted-unix-domain-socket.pool.conf $PHP_CONF_DIR/php-fpm.d/restricted-unix-domain-socket.pool.conf
COPY unix-domain-socket.pool.conf $PHP_CONF_DIR/php-fpm.d/unix-domain-socket.pool.conf
WORKDIR /repo