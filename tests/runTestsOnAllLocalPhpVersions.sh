#!/usr/bin/env bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )")"
cd "$(dirname "${DIR}" )" >/dev/null 2>&1

docker-compose up -d php71 php72 php73

echo -e "\n\033[43mRun PHPUnit\033[0m\n"
echo -e "\n\033[33mOn PHP 7.1\033[0m\n"
docker-compose exec php71 vendor/bin/phpunit7.phar -c build
echo -e "\n\033[33mOn PHP 7.2\033[0m\n"
docker-compose exec php72 vendor/bin/phpunit8.phar -c build
echo -e "\n\033[33mOn PHP 7.3\033[0m\n"
docker-compose exec php73 vendor/bin/phpunit8.phar -c build

echo -e "\n\033[43mRun phpstan\033[0m\n"
docker-compose run --rm phpstan