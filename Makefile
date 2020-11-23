update: dcbuild dcpull dccomposer
.PHONY: update

dcbuild:
	docker-compose build --pull --parallel
.PHONY: dcbuild

dcpull:
	docker-compose pull
.PHONY: dcpull

dccomposer:
	docker-compose run --rm composer
.PHONY: dccomposer

test: test-php-71 test-php-72 test-php-73 test-php-74 test-php-80 dcphpstan
.PHONY: test

INTEGRATION_WORKER_DIR = ./tests/Integration/Workers

make-integration-workers-accessible:
	chmod -R 0755 $(INTEGRATION_WORKER_DIR)/*
.PHONY: make-integration-workers-accessible

dcdown:
	docker-compose down
.PHONY: dcdown

PHP_OPTIONS = -d error_reporting=-1 -d xdebug.mode=coverage -d auto_prepend_file=build/xdebug-filter.php

test-php-71: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 7 on PHP 7.1\033[0m\n"
	docker-compose up -d --force-recreate php71
	docker-compose exec php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml ${PHPUNIT_OPTIONS}
.PHONY: test-php-71

test-php-72: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 8 on PHP 7.2\033[0m\n"
	docker-compose up -d --force-recreate php72
	docker-compose exec php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml ${PHPUNIT_OPTIONS}
.PHONY: test-php-72

test-php-73: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.3\033[0m\n"
	docker-compose up -d --force-recreate php73
	docker-compose exec php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml ${PHPUNIT_OPTIONS}
.PHONY: test-php-73

test-php-74: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.4\033[0m\n"
	docker-compose up -d --force-recreate php74
	docker-compose exec php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml ${PHPUNIT_OPTIONS}
.PHONY: test-php-74

test-php-80: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.0\033[0m\n"
	docker-compose up -d --force-recreate php80
	docker-compose exec php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml ${PHPUNIT_OPTIONS}
.PHONY: test-php-80

dcphpstan:
	docker-compose run --rm phpstan
.PHONY: dcphpstan