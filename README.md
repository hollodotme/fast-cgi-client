[![FastCGI Client CI PHP 8.0 - 8.5](https://github.com/hollodotme/fast-cgi-client/actions/workflows/ci.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/hollodotme/fast-cgi-client/v/stable)](https://packagist.org/packages/hollodotme/fast-cgi-client)
[![Total Downloads](https://poser.pugx.org/hollodotme/fast-cgi-client/downloads)](https://packagist.org/packages/hollodotme/fast-cgi-client)
[![Compatible with Go FastCGI server](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-go.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-go.yml)
[![Compatible with Rust FastCGI server](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-rust.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-rust.yml)
[![Compatible with Rust FastCGI server (tokio)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-rust-tokio.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-rust-tokio.yml)
[![Compatible with C# FastCGI server](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-csharp.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-csharp.yml)
[![Compatible with Java FastCGI server](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-java.yml/badge.svg)](https://github.com/hollodotme/fast-cgi-client/actions/workflows/compatibility-java.yml)
[![Documentation](https://github.com/hollodotme/fast-cgi-client/actions/workflows/docs.yml/badge.svg)](https://fast-cgi-client.hollo.me)

# Fast CGI Client

A PHP fast CGI client to send requests (a)synchronously to PHP-FPM using
the [FastCGI Protocol](http://www.mit.edu/~yandros/doc/specs/fcgi-spec.html).

This library is based on the work
of [Pierrick Charron](https://github.com/adoy)'s [PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client/)
and was ported and modernized to latest PHP versions, extended with some features for handling multiple requests (in
loops) and unit and integration tests as well.

## 📖 Documentation

**The full documentation is available at [fast-cgi-client.hollo.me](https://fast-cgi-client.hollo.me).**

* [What it is](https://fast-cgi-client.hollo.me/docs)
* [Installation](https://fast-cgi-client.hollo.me/docs/installation)
* [Getting started](https://fast-cgi-client.hollo.me/docs/getting-started)
* [Use cases & examples](https://fast-cgi-client.hollo.me/docs/category/use-cases--examples) — [connections](https://fast-cgi-client.hollo.me/docs/usage/connections),
  [single requests](https://fast-cgi-client.hollo.me/docs/usage/single-requests), [multiple requests](https://fast-cgi-client.hollo.me/docs/usage/multiple-requests),
  [requests](https://fast-cgi-client.hollo.me/docs/usage/requests), [responses](https://fast-cgi-client.hollo.me/docs/usage/responses),
  [exceptions](https://fast-cgi-client.hollo.me/docs/usage/exceptions), [trouble shooting](https://fast-cgi-client.hollo.me/docs/usage/troubleshooting)
* [API reference](https://fast-cgi-client.hollo.me/api/4.x/index.html)

## Versions

This is version 4.x, which requires PHP >= 8.0.

Please have a look at the [backwards incompatible changes (BC breaks) in the changelog](./CHANGELOG.md), if you
upgrade from a previous version.

| Version | PHP    | Documentation               | Changelog                      |
|---------|--------|-----------------------------|--------------------------------|
| 4.x     | >= 8.0 | [4.x](https://fast-cgi-client.hollo.me/docs)             | [CHANGELOG.md](./CHANGELOG.md) |
| 3.x     | >= 7.1 | [3.x](https://fast-cgi-client.hollo.me/docs/3.x)         | [3.x](./docs/changelog/3.x.md) |
| 2.x     | >= 7.1 | [2.x](https://fast-cgi-client.hollo.me/docs/2.x)         | [2.x](./docs/changelog/2.x.md) |
| 1.x     | >= 7.0 | [1.x](https://fast-cgi-client.hollo.me/docs/1.x)         | [1.x](./docs/changelog/1.x.md) |

Version 3.x still gets bug fixes, but no new features.

## Installation

```bash
composer require hollodotme/fast-cgi-client
```

## Quick example

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );
$request    = new PostRequest( '/path/to/target/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );

$response = $client->sendRequest( $connection, $request );

echo $response->getBody();
```

Sending requests asynchronously, reacting to responses with callbacks, streaming output and much more is described in
the [documentation](https://fast-cgi-client.hollo.me/docs/category/use-cases--examples).

## Contributing

Contributions are welcome, please read the [contribution guide](./.github/CONTRIBUTING.md). It also describes how to
run the tests, the static analysis and the documentation website locally.

## License

The FastCGI Client is released under the [MIT License](./LICENSE).
