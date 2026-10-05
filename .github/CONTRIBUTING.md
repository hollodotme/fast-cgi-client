# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via pull requests on [GitHub](https://github.com/hollodotme/fast-cgi-client).

## Pull Requests

- **Add tests!** - Your patch will not be accepted if it does not have tests.

- **Follow the coding standard** - Run `make phpcs` to check your code and `make phpcbf` to fix violations automatically. The standard is described [below](#check-the-coding-standard).

- **Document any change in behaviour** - Make sure the documentation in `website/docs` (and the short `README.md`, if affected) is kept up-to-date.

- **Consider our release cycle** - We follow [SemVer v2.0.0](http://semver.org/). Randomly breaking public APIs is not an option.

- **Create topic branches** - Do not ask us to pull from your master branch.

- **One pull request per feature** - If you want to do more than one thing, please send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please squash them before submitting.

## Development

All tools run in docker containers, so you only need `docker` with the `docker compose` plugin and `make`.

### Prepare local development environment

```bash
make update
```

### Run examples

```bash
make examples
```

### Run all tests

```bash
make tests
```

This runs the coding standard check, the static analysis and all test suites on PHP 8.0 - 8.5. To run the test suites on a single PHP version use
one of `make test-php-8.0` ... `make test-php-8.5`.

### Run static analysis

```bash
make phpstan
```

This runs PHPStan on and for each PHP version from 8.0 to 8.5. To analyse the code for a single PHP version use one of
`make phpstan-php-8.0` ... `make phpstan-php-8.5`. The PHP version PHPStan analyses for is set in the configuration
files in [.phpstan](https://github.com/hollodotme/fast-cgi-client/tree/4.x-dev/.phpstan), which include the base configuration [phpstan.neon](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/phpstan.neon).

### Run compatibility tests

```bash
make test-compatibility
```

This runs the tests in `tests/Compatibility` against FastCGI servers of other programming languages. Each server
runs a small application in a docker container, see [.docker/compatibility](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/.docker/compatibility/README.md).
To check a single server use `make test-compatibility-<name>`, where `<name>` is a directory in `.docker/compatibility`.

### Check the coding standard

```bash
make phpcs
```

This checks `src`, `bin` and `tests` with [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer)
against the standard configured in [phpcs.xml](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/phpcs.xml). Violations that can be fixed automatically are fixed by

```bash
make phpcbf
```

The standard is [PSR-12](https://www.php-fig.org/psr/psr-12/) with these adjustments:

* Tabs are used for indentation.
* There is one space inside of parentheses: `foo( $bar )`, `if ( $foo )`, `function foo( string $bar )`.
* Opening braces of control structures are on their own line.
* The return type is separated from the parameter list by ` : `.
* `<?php declare(strict_types=1);` is the first line of each file.
* Imports of classes, functions and constants are not separated by blank lines.

The check is part of `make tests`.

### Build the documentation website

The documentation website in `website/` is built with [Docusaurus](https://docusaurus.io), the API reference
with [Doctum](https://github.com/code-lts/doctum).

```bash
make docs-serve
```

This serves the docs with live reload on [http://localhost:3000](http://localhost:3000).

```bash
make docs-build
```

This builds the complete static website including the API reference of all major versions into `website/build`.
Documentation of older major versions lives in `website/versioned_docs`.

### Command line tool (for local debugging only)

**Please note:** `bin/fcgiget` is not included and linked to `vendor/bin` via composer anymore since version `v3.1.2`for
security reasons. [Read more.](https://github.com/hollodotme/fast-cgi-client/pull/58)

Start one of the PHP containers:

```bash
docker compose -p fast-cgi-client up -d php80
```

Run a call through a network socket:

```bash
docker compose -p fast-cgi-client exec php80 php bin/fcgiget localhost:9001/status
```

Run a call through a Unix Domain Socket

```bash
docker compose -p fast-cgi-client exec php80 php bin/fcgiget unix:///var/run/php-uds.sock/status
```

This shows the response of the php-fpm status page.
