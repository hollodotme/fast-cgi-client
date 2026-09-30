# CHANGELOG

All notable changes to this project will be documented in this file. This project adheres
to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

This file covers the 4.x releases. For previous major versions see:
[3.x](./docs/changelog/3.x.md), [2.x](./docs/changelog/2.x.md), [1.x](./docs/changelog/1.x.md)

## [4.0.0] - Unreleased

### Backwards incompatible changes (BC breaks)

* PHP >= 8.0 is required. Support for PHP 7.1 - 7.4 was dropped. Version 3.x keeps supporting these PHP versions
  and still gets bug fixes, but no new features. - [#57]
* The `fileinfo` extension is required. It is used to detect the MIME type of files in multipart form-data
  requests. - [#81]
* The content of a request is a `ComposesRequestContent` object instead of a string. - [#57]
  * `AbstractRequest#__construct()` expects an optional content object as second argument:
    `__construct( string $scriptFilename, ?ComposesRequestContent $content = null )`
  * `AbstractRequest#setContent()` expects a `ComposesRequestContent` object and also sets the content type of the
    request. A content type that was set before is overwritten.
  * `ProvidesRequestData#getContent()` returns `?ComposesRequestContent` instead of `string`.
  * The named constructor `newWithRequestContent()`, introduced in 3.1.0, was removed from all request classes.
  * The content length and, unless it was set explicitly, the content type of a request are determined from the
    content object when the request is sent. Changes to the content object after it was passed to the request are
    taken into account. - [#76]

  ```php
  # Previous versions
  $request = new PostRequest( '/path/to/script.php', http_build_query( ['key' => 'value'] ) );
  $request = PostRequest::newWithRequestContent( '/path/to/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );
  $request = new PostRequest( '/path/to/script.php', 'plain text' );
  $request->setContentType( 'text/plain' );
  
  # Since 4.0.0
  $request = new PostRequest( '/path/to/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );
  $request = new PostRequest( '/path/to/script.php', new PlainText( 'plain text' ) );
  ```

  Content types that are not covered by the classes in `hollodotme\FastCGI\RequestContents` need an own implementation
  of the `ComposesRequestContent` interface.
* The interface `ConfiguresSocketConnection` has the new method `getStreamSelectTimeout() : int`, which must be added
  to own implementations of this interface. - [#82]
* `Client#readResponses()` and `Client#readReadyResponses()` throw the exception, if a response cannot be read,
  e.g. a `TimedoutException`. Before, such responses were skipped silently. Unknown socket IDs are still skipped.
* The interface `ProvidesResponseData` has the new method `getStatusCode() : int`, which must be added to own
  implementations of this interface.
* The public constant `Socket::STREAM_SELECT_USEC` was removed in favour of the configurable stream select timeout
  of the socket connections. - [#82]
* Properties and some method parameters have native type declarations now, e.g. `AbstractRequest#setCustomVar()`
  and `JsonData#__construct()` use `mixed`. Classes that extend classes of this library may need to adapt their
  declarations. - [#57], [#85]

### Added

* Method `Response#getStatusCode()`, which returns the status code of the `Status` header, or 200 if there is none.
* Request content composer `PlainText` for content type `text/plain`.
* Configurable stream select timeout as third timeout of the socket connections `NetworkSocket` and
  `UnixDomainSocket`. It defines how long the client waits when checking for responses. The default is
  `Defaults::STREAM_SELECT_TIMEOUT` (200 ms), which is the value used in previous versions. - [#82], [#18]
* Query parameters can be passed as an array to all requests with `AbstractRequest#setQueryParams()`. They are sent
  as `QUERY_STRING` and appended to the `REQUEST_URI`. `AbstractRequest#getQueryParams()` and
  `AbstractRequest#getQueryString()` return the parameters and the encoded query string. - [#83]
* Methods `Client#tryRequest()` and `Client#tryAsyncRequest()`, which send a request again on another socket, if
  writing the request to a socket failed. - [#84]
* Compatibility with PHP 8.2, 8.3, 8.4 and 8.5. All test suites run on PHP 8.0 - 8.5.
* Validation of received packets according to the FastCGI specification. A `ReadFailedException` is thrown, if a
  packet has an unsupported protocol version, an unexpected record type, the ID of another request or, in case of
  an end-request record, an unexpected length. - [#78]

### Improved

* Idle sockets that were closed by the peer, e.g. because their php-fpm child process was terminated, are detected
  and replaced before a request is sent. - [#84]
* Use of PHP 8.0 language features like constructor property promotion, `match` and `mixed`. - [#85]
* PHPStan (level 8) is part of the test pipeline and analyses the code on and for each supported PHP version.
* Values passed to `chr()` when encoding packets are limited to one byte, because values out of this range are
  deprecated in PHP 8.5.
* A single PHPUnit version is used for all supported PHP versions.
* Compatibility with FastCGI servers of other programming languages is checked continuously in a workflow per server,
  independently of the CI workflow (`make test-compatibility`).
* PHP_CodeSniffer is part of the test pipeline and checks the coding standard, PSR-12 with the adjustments listed
  in the README (`make phpcs`, `make phpcbf`). - [#75]
* Integration tests do not depend on fixed waiting times anymore. - [#86]
* The content of a request is composed once per request when it is sent, instead of once for every 65535 bytes.
* Documentation and changelog are split by major version. - [#87]
* The development environment uses the `docker compose` plugin instead of the standalone `docker-compose` binary.

### Fixed

* `Client#waitForResponse()` and `Client#waitForResponses()` did not return, if the server did not respond. After the
  timeout (default: the read/write timeout of the connection) the failure callbacks of the request are notified with
  a `TimedoutException` now.
* A socket that could not connect stayed in the collection of the client.
* Endless loop when the connection was closed before a packet was received completely, e.g. when the process
  handling the request was terminated while it sent its response, or when the client was connected to a HTTP server
  instead of a FastCGI server. A `ReadFailedException` is thrown now. - [#78]
* Reading a response that was not completed by the server timed out after twice the read/write timeout.
* Packet headers are read completely before they are decoded, also if they arrive in several parts.
* The first two lines of a response were lost, if it did not start with a header.
* Headers and body of a response are separated independently of the line endings of the platform the client runs on.

### Removed

* License information from all PHP files. The [LICENSE](./LICENSE) file applies to the whole project. - [#57]

[4.0.0]: https://github.com/hollodotme/fast-cgi-client/compare/v3.1.7...4.x-dev

[#18]: https://github.com/hollodotme/fast-cgi-client/issues/18

[#57]: https://github.com/hollodotme/fast-cgi-client/issues/57

[#75]: https://github.com/hollodotme/fast-cgi-client/pull/75

[#76]: https://github.com/hollodotme/fast-cgi-client/pull/76

[#78]: https://github.com/hollodotme/fast-cgi-client/pull/78

[#81]: https://github.com/hollodotme/fast-cgi-client/issues/81

[#82]: https://github.com/hollodotme/fast-cgi-client/issues/82

[#83]: https://github.com/hollodotme/fast-cgi-client/issues/83

[#84]: https://github.com/hollodotme/fast-cgi-client/issues/84

[#85]: https://github.com/hollodotme/fast-cgi-client/issues/85

[#86]: https://github.com/hollodotme/fast-cgi-client/issues/86

[#87]: https://github.com/hollodotme/fast-cgi-client/issues/87
