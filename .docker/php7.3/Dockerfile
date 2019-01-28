FROM php:7.3-fpm
ENV PHP_CONF_DIR=/usr/local/etc
RUN pecl install xdebug-2.7.0beta1
COPY xdebug.ini $PHP_INI_DIR/conf.d/xdebug.ini
COPY restricted-unix-domain-socket.pool.conf $PHP_CONF_DIR/php-fpm.d/restricted-unix-domain-socket.pool.conf
COPY unix-domain-socket.pool.conf $PHP_CONF_DIR/php-fpm.d/unix-domain-socket.pool.conf
WORKDIR /repo