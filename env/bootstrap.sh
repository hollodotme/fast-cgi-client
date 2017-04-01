#!/usr/bin/env bash

sudo touch /var/run/php-uds.sock && sudo chown www-data:www-data /var/run/php-uds.sock

ln -sf /vagrant/env/php-fpm/7.1/network-socket.pool.conf /etc/php/7.1/fpm/pool.d/network-pool.conf
ln -sf /vagrant/env/php-fpm/7.1/unix-domain-socket.pool.conf /etc/php/7.1/fpm/pool.d/unix-domain-socket-pool.conf
ln -sf /vagrant/env/php-fpm/7.1/restricted-unix-domain-socket.pool.conf /etc/php/7.1/fpm/pool.d/restricted-unix-domain-socket-pool.conf

sudo service php7.1-fpm restart
