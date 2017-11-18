# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via pull requests on [GitHub](https://github.com/hollodotme/fast-cgi-client).

## Pull Requests

- **Add tests!** - Your patch will not be accepted if it does not have tests.

- **Document any change in behaviour** - Make sure the documentation in `README.md` is kept up-to-date.

- **Consider our release cycle** - We follow [SemVer v2.0.0](http://semver.org/). Randomly breaking public APIs is not an option.

- **Create topic branches** - Do not ask us to pull from your master branch.

- **One pull request per feature** - If you want to do more than one thing, please send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please squash them before submitting.

## Running tests

 - Add the relevant pools to a working php-fpm service:
   - [for PHP-FPM 7.0](./env/php-fpm/7.0)
   - [for PHP-FPM 7.1](./env/php-fpm/7.1)
 
 - Then run tests:

```bash
$ php vendor/bin/phpunit.phar -c build/
```

This includes a code coverage report in HTML and Clover XML.

## Analyze code

```bash
$ php vendor/bin/phpmetrics.phar --report-html=build/logs/phpmetrics src/
```
