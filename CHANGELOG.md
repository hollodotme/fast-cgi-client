# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

## [1.4.1] - 2017-11-19

* Fixes PHP warning when trying to get ready request IDs - [#14] 

## [1.4.0] - 2017-09-28

### Added

* Ability to add pass through callbacks in order to access output buffer from a long running callee - [#11]  
  See an [example in the documentation](./README.md#reading-output-buffer-from-worker-script-using-pass-through-callbacks) for further informaiton.

## [1.3.0] - 2017-06-15

### Changed

* Replaced methods `getHost()` and `getPort()` with `getSocketAddress()` in interface `hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection` - [#9]
* The transport protocol `unix://` must be omitted for the first parameter of `hollodotme\FastCGI\SocketConnections\UnixDomainSocket`  
  Only the socket path must be passed. - [#9]
* Relaced `fsockopen()` with `stream_socket_client()` for connecting to php-fpm. - [#9]

## [1.2.0] - 2017-05-27

### Added

* Method `addResponseCallbacks(callable ...$callbacks)` to all request classes to enable response evaluation delegation - [#6]
* Method `addFailureCallbacks(callable ...$callbacks)` to all request classes to enable exception handling delegation
* Method `readResponse(int $requestId, $timeoutMs = null) : ProvidesResponseData` to read and retrieve a single response
* Method `readResponses(?int $imeoutMs = null, int ...$requestIds) : \Generator` to read and yield multiple responses
* Method `readReadyResponses($imeoutMs = null) : \Generator` to check for ready responses, read and yield them
* Method `waitForResponses($timeout = null)` to `Client` class for waiting for multiple responses and calling the respective response callbacks - [#5]
* Method `getRequestIdsHavingResponse() : array` to enable reactive read of responses as they occur
* Method `hasUnhandledResponses() : bool` to check for outstanding responses
* Method `handleResponse(int $requestId, $timeoutMs = null)` to fetch a specific response and notify the respective response callback
* Method `handleResponses($timeoutMs = null, int ...$requestIds)` to fetch a specific responses and notify the respective response callbacks
* Method `handleReadyResponses($timeoutMs = null)` to check for ready responses, fetch them and notify the respective response callbacks

### Changed

* Method `waitForResponse(int $requestId, $timeoutMs = null)` is not returning a response anymore, but will call the response callback  
  Use `readResponse(int $requestId, $timeoutMs = null): ProvidesResponseData` if you want to get the response directly.

### Removed

* Optional flag to make a connection persistent (is now always disabled in favour of better timeout handling and FPM pool-children-scalability)
* Optional flag to keep the server-side connection alive (is now always enabled, affects only network sockets)

### Improved

* Code coverage by automated integration tests
* Timeout handling on multiple requests

## [1.1.0] - 2017-03-07

### Changed

* Methods `sendRequest` and `sendAsyncRequest` expect to get an object of interface `hollodotme\FastCGI\Interfaces\ProvidesRequestData` - [#5]
* Methods `sendRequest` and `waitForResponse` now return an object of interface `hollodotme\FastCGI\Interfaces\ProvidesResponseData` - [#2]

### Added

* Public class constants for request methods `GET`, `POST`, `PUT`, `PATCH` and `DELETE` in `hollodotme\FastCGI\Constants\RequestMethod` - [#5]
* Public class constants for server protocols `HTTP/1.0` and `HTTP/1.1` in `hollodotme\FastCGI\Constants\ServerProtocol` - [#5]
* Abstract request class for implementing individual request methods, contains all request default values - [#5]
* Request implementations: - [#5]
  * `hollodotme\FastCGI\Requests\GetRequest`
  * `hollodotme\FastCGI\Requests\PostRequest`
  * `hollodotme\FastCGI\Requests\PutRequest`
  * `hollodotme\FastCGI\Requests\PatchRequest`
  * `hollodotme\FastCGI\Requests\DeleteRequest`
* Response implementation - [#2]

## [1.0.1] - 2017-02-23

### Fixed

* Erroneous response returned by `Client::sendRequest()` and `Client::waitForResponse()` - [#1]
  
### Changed

* Testsuite updated for PHPUnit >= 6

## [1.0.0] - 2017-01-03

Based on [Pierrick Charron](https://github.com/adoy)'s [PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client/):

### Added 

 * Socket connection interface `ConfiguresSocketConnection`
 * Socket connection classes `UnixDomainSocket` and `NetworkSocket`
 * Base exception `FastCGIClientException`
 * Derived exceptions `ForbiddenException`, `ReadFailedException`, `TimeoutException`, `WriteFailedException`
 
### Changed

 * Constructor of `Client` now expects a `ConfiguresSocketConnection` instance
 * Renamed `Client->request()` to `Client->sendRequest()`
 * Renamed `Client->async_request()` to `Client->sendAsyncRequest()`
 * Renamed `Client->wait_for_response()` to `Client->waitForResponse()`
 
### Removed

 * Unused class constants from `Client`
 * Getters/Setters for connect timeout, read/write timeout, keep alive, socket persistence from `Client` (now part of the socket connection)
 * Method `Client->getValues()`

[1.4.1]: https://github.com/hollodotme/fast-cgi-client/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/hollodotme/fast-cgi-client/tree/v1.0.0

[#1]: https://github.com/hollodotme/fast-cgi-client/issues/1
[#2]: https://github.com/hollodotme/fast-cgi-client/issues/2
[#5]: https://github.com/hollodotme/fast-cgi-client/issues/5
[#6]: https://github.com/hollodotme/fast-cgi-client/issues/6
[#9]: https://github.com/hollodotme/fast-cgi-client/issues/9
[#11]: https://github.com/hollodotme/fast-cgi-client/issues/11
[#14]: https://github.com/hollodotme/fast-cgi-client/issues/14
