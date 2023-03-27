.SILENT:
.PHONY: help

# Based on https://gist.github.com/prwhite/8168133#comment-1313022

## This help screen
help:
	printf "Available commands\n\n"
	awk '/^[a-zA-Z\-\_0-9]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")-1); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "\033[33m%-40s\033[0m %s\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST)

PROJECT = fast-cgi-client
IMAGE = php80
DOCKER_COMPOSE_OPTIONS = -p $(PROJECT) -f docker-compose.yml
DOCKER_COMPOSE_BASE_COMMAND = docker-compose $(DOCKER_COMPOSE_OPTIONS)
DOCKER_COMPOSE_EXEC_COMMAND = $(DOCKER_COMPOSE_BASE_COMMAND) exec -T
DOCKER_COMPOSE_ISOLATED_RUN_COMMAND = $(DOCKER_COMPOSE_BASE_COMMAND) run --rm --no-deps

## Install/Update whole setup
update: dcbuild dcpull composer-update
.PHONY: update

## Build all custom docker images
dcbuild: pull-extension-installer
	$(DOCKER_COMPOSE_BASE_COMMAND) build --pull --parallel
.PHONY: dcbuild

pull-extension-installer:
	docker pull mlocati/php-extension-installer
.PHONY: pull-extension-installer

## Pull docker images
dcpull:
	$(DOCKER_COMPOSE_BASE_COMMAND) pull
.PHONY: dcpull

## Tear down docker compose setup
dcdown:
	$(DOCKER_COMPOSE_BASE_COMMAND) down
.PHONY: dcdown

## Install composer to .tools
install-composer:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) $(IMAGE) \
	curl -L -o "./.tools/composer.phar" "https://getcomposer.org/download/latest-stable/composer.phar"
	chmod +x "./.tools/composer.phar"
.PHONY: install-composer

## Validate composer config
composer-validate:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) $(IMAGE) \
    php /repo/.tools/composer.phar validate
.PHONY: composer-validate

## Update composer dependencies
composer-update:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) $(IMAGE) \
    php /repo/.tools/composer.phar update -o -v
.PHONY: composer-update

## Run all tests on all PHP versions
tests: composer-validate test-php-8.0 test-php-8.1 test-php-8.2 dcdown
.PHONY: tests

INTEGRATION_WORKER_DIR = ./tests/Integration/Workers

## Make integration workers accessible
make-integration-workers-accessible:
	chmod -R 0755 $(INTEGRATION_WORKER_DIR)/*
.PHONY: make-integration-workers-accessible

PHP_OPTIONS = -d error_reporting=-1 -dmemory_limit=-1 -d xdebug.mode=coverage -d auto_prepend_file=tests/xdebug-filter.php
PHPUNIT_OPTIONS = --testdox

## Run test on PHP 8.0 with PHPUnit 9
test-php-8.0: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.0\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php80
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.0

## Run test on PHP 8.1 with PHPUnit 9
test-php-8.1: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.1\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php81
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.1

## Run test on PHP 8.2 with PHPUnit 9
test-php-8.2: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.2\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php82
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php82 php $(PHP_OPTIONS) vendor/bin/phpunit -c phpunit.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.2

## Run examples
examples: dcdown
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate $(IMAGE)
	$(DOCKER_COMPOSE_EXEC_COMMAND) $(IMAGE) php $(PHP_OPTIONS) bin/examples.php
.PHONY: examples

