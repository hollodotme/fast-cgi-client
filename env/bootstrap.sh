#!/usr/bin/env bash

ln -sf /vagrant/env/php-fpm/7.0/network-socket.pool.conf /etc/php/7.0/fpm/pool.d/network-pool.conf
ln -sf /vagrant/env/php-fpm/7.1/network-socket.pool.conf /etc/php/7.1/fpm/pool.d/network-pool.conf

sudo service php7.0-fpm restart
sudo service php7.1-fpm restart
