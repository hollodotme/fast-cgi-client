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

phpUnitKey 		= 4AA394086372C20A
phpStanKey 		= CF1A108D0E7AE720
composerKey 	= CBB3D576F2A0946F
trustedKeys 	= "$(phpUnitKey),$(phpStanKey),$(composerKey)"

## Install/Update whole setup
update: dcbuild dcpull update-tools composer-update
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

## Run install & update of tools via Phive
update-tools:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) phive \
	sh -c "php -dmemory_limit=-1 /usr/local/bin/phive --no-progress install --trust-gpg-keys \"$(trustedKeys)\" && php -dmemory_limit=-1 /usr/local/bin/phive --no-progress update"
	curl -L -o "./.tools/phplint.sh" "https://gist.githubusercontent.com/hollodotme/9c1b805e9a2f946433512563edc4b702/raw/60532cb51f1b7a1550216088943bacbd3d4c9351/phplint.sh"
	chmod +x "./.tools/phplint.sh"
.PHONY: update-tools

## Install PHAR tools
install-tools:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) phive \
	sh -c "php -dmemory_limit=-1 /usr/local/bin/phive --no-progress install --trust-gpg-keys \"$(trustedKeys)\""
	curl -L -o "./.tools/phplint.sh" "https://gist.githubusercontent.com/hollodotme/9c1b805e9a2f946433512563edc4b702/raw/60532cb51f1b7a1550216088943bacbd3d4c9351/phplint.sh"
	chmod +x "./.tools/phplint.sh"
.PHONY: install-tools

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
tests: composer-validate phplint test-php-7.1 test-php-7.2 test-php-7.3 test-php-7.4 test-php-8.0 test-php-8.1 phpstan
.PHONY: tests

INTEGRATION_WORKER_DIR = ./tests/Integration/Workers

## Make integration workers accessible
make-integration-workers-accessible:
	chmod -R 0755 $(INTEGRATION_WORKER_DIR)/*
.PHONY: make-integration-workers-accessible

PHP_OPTIONS = -d error_reporting=-1 -dmemory_limit=-1 -d xdebug.mode=coverage -d auto_prepend_file=tests/xdebug-filter.php
PHPUNIT_OPTIONS = --testdox

## Run PHP linting
phplint:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) $(IMAGE) \
	sh -c "sh /repo/.tools/phplint.sh -p8 -f'*.php' /repo/bin /repo/src /repo/tests"
.PHONY: phplint

## Run test on PHP 7.1 with PHPUnit 7
test-php-7.1: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 7 on PHP 7.1\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php71
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php71 php $(PHP_OPTIONS) .tools/phpunit-7.phar -c tests/phpunit7.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.1

## Run test on PHP 7.2 with PHPUnit 8
test-php-7.2: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 8 on PHP 7.2\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php72
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php72 php $(PHP_OPTIONS) .tools/phpunit-8.phar -c tests/phpunit8.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.2

## Run test on PHP 7.3 with PHPUnit 9
test-php-7.3: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.3\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php73
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php73 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.3

## Run test on PHP 7.4 with PHPUnit 9
test-php-7.4: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 7.4\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php74
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php74 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-7.4

## Run test on PHP 8.0 with PHPUnit 9
test-php-8.0: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.0\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php80
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php80 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.0

## Run test on PHP 8.1 with PHPUnit 9
test-php-8.1: dcdown make-integration-workers-accessible
	printf "\n\033[33mRun PHPUnit 9 on PHP 8.1\033[0m\n"
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate php81
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 sh -c 'find /repo -type f -name "*.php" -print0 | xargs -0 -n1 -P8 php -l -n | (! grep -v "No syntax errors detected" )'
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Async-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=FileUpload-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=NetworkSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=UnixDomainSocket-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Signals-Integration $(PHPUNIT_OPTIONS)
	$(DOCKER_COMPOSE_EXEC_COMMAND) php81 php $(PHP_OPTIONS) .tools/phpunit-9.phar -c tests/phpunit9.xml --testsuite=Unit $(PHPUNIT_OPTIONS)
.PHONY: test-php-8.1

## Run PHPStan
phpstan:
	$(DOCKER_COMPOSE_ISOLATED_RUN_COMMAND) php74 \
	php /repo/.tools/phpstan.phar analyze --memory-limit=-1
.PHONY: phpstan

## Run examples
examples: dcdown
	$(DOCKER_COMPOSE_BASE_COMMAND) up -d --force-recreate $(IMAGE)
	$(DOCKER_COMPOSE_EXEC_COMMAND) $(IMAGE) php $(PHP_OPTIONS) bin/examples.php
.PHONY: examples

