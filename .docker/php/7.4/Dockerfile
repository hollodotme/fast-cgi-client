FROM php:7.4-fpm-alpine
ENV PHP_CONF_DIR=/usr/local/etc/php-fpm.d

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN set -ex && install-php-extensions xdebug

COPY network-socket.pool.conf ${PHP_CONF_DIR}/network-socket.pool.conf
COPY restricted-unix-domain-socket.pool.conf ${PHP_CONF_DIR}/restricted-unix-domain-socket.pool.conf
COPY unix-domain-socket.pool.conf ${PHP_CONF_DIR}/unix-domain-socket.pool.conf

WORKDIR /repo