update: dcbuild dcpull composer-update
.PHONY: update

dcbuild:
	docker-compose build --pull --parallel
.PHONY: dcbuild

dcpull:
	docker-compose pull
.PHONY: dcpull

dcdown:
	docker-compose down
.PHONY: dcdown

composer-validate:
	docker-compose run --rm composer validate
.PHONY: composer-validate

composer-update:
	docker-compose run --rm composer
.PHONY: composer-update

tests: composer-validate test-php-7.1 test-php-7.2 test-php-7.3 test-php-7.4 test-php-8.0 phpstan
.PHONY: test

INTEGRATION_WORKER_DIR = ./tests/Integration/Workers

make-integration-workers-accessible:
	chmod -R 0755 $(INTEGRATION_WORKER_DIR)/*
.PHONY: make-integration-workers-accessible


PHP_OPTIONS = -d error_reporting=-1 -dmemory_limit=-1 -d xdebug.mode=coverage -d auto_prepend_file=build/xdebug-filter.php

test-php-7.1: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 7 on PHP 7.1\033[0m\n"
	docker-compose up -d --force-recreate php71
	docker-compose exec -T php71 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php71 php $(PHP_OPTIONS) vendor/bin/phpunit7.phar -c build/phpunit7.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.1

test-php-7.2: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 8 on PHP 7.2\033[0m\n"
	docker-compose up -d --force-recreate php72
	docker-compose exec -T php72 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php72 php $(PHP_OPTIONS) vendor/bin/phpunit8.phar -c build/phpunit8.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.2

test-php-7.3: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.3\033[0m\n"
	docker-compose up -d --force-recreate php73
	docker-compose exec -T php73 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php73 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.3

test-php-7.4: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.4\033[0m\n"
	docker-compose up -d --force-recreate php74
	docker-compose exec -T php74 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php74 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.4

test-php-8.0: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.0\033[0m\n"
	docker-compose up -d --force-recreate php80
	docker-compose exec -T php80 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	docker-compose exec -T php80 php $(PHP_OPTIONS) vendor/bin/phpunit9.phar -c build/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.0

phpstan:
	docker-compose run --rm phpstan
.PHONY: phpstan