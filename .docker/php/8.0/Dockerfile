FROM php:8.0-rc-fpm-alpine
ARG XDEBUG_VERSION="3.0.0RC1"
ARG PHP_CONF_DIR="/usr/local/etc"
RUN set -ex \
	&& apk update && apk upgrade && apk add --no-cache ${PHPIZE_DEPS} \
    && mkdir -p /usr/src/php/ext/xdebug \
    && curl -fsSL https://pecl.php.net/get/xdebug-${XDEBUG_VERSION} | tar xvz -C "/usr/src/php/ext/xdebug" --strip 1 \
    && docker-php-ext-install xdebug \
    && apk del ${PHPIZE_DEPS} \
    && rm -rf /tmp/* /var/cache/apk/*
COPY network-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/network-socket.pool.conf
COPY restricted-unix-domain-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/restricted-unix-domain-socket.pool.conf
COPY unix-domain-socket.pool.conf ${PHP_CONF_DIR}/php-fpm.d/unix-domain-socket.pool.conf
WORKDIR /repo