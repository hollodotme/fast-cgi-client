# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

## [2.2.0] - YYYY-MM-DD (unreleased)

### Added

* Method `addResponseCallbacks(callable ...$callbacks)` to all request classes to enable response evaluation delegation - [#6]
* Method `addFailureCallbacks(callable ...$callbacks)` to all request classes to enable exception handling delegation
* Method `readResponse(int $requestId, ?int $timeoutMs = null) : ProvidesResponseData` to read and retrieve a single response
* Method `readResponses(?int $imeoutMs = null, int ...$requestIds) : \Generator` to read and yield multiple responses
* Method `waitForResponses(?int $timeout = null)` to `Client` class for waiting for multiple responses and calling the respective response callbacks - [#5]
* Method `getRequestIdsHavingResponse()` to enable reactive read of responses as they occur

### Changed

* Method `waitForResponse(int $requestId, ?int $timeoutMs = null)` is not returning a response anymore (use `readResponse()` for that), but will call the response callback

### Removed

* Optional flag to make a connection persistent (is now always disabled in favour of better timeout handling and FPM pool-children-scalability)
* Optional flag to keep the server-side connection alive (is now always enabled, affects only network sockets)

### Improved

* Code coverage by automated integration tests
* Timeout handling on multiple requests

## [2.1.0] - 2017-03-07

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

## [2.0.1] - 2017-02-23
   
### Fixed
   
- Erroneous response returned by Client::sendRequest() and Client::waitForResponse() - [#1]
   
### Changed
   
- Testsuite updated for PHPUnit >= 6

## [2.0.0] - 2017-01-03

### Changed

 * Class constant visibility to private in class `Client`
 * Class constant visibility to privare in class `Encoders\PacketEncoder`
 * Class constant visibility to public in class `SocketConnections\Defaults`
 * Composer requires php >= 7.1
 

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

[2.2.0]: https://github.com/hollodotme/fast-cgi-client/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/hollodotme/fast-cgi-client/compare/v2.0.1...v2.1.0
[2.0.1]: https://github.com/hollodotme/fast-cgi-client/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/hollodotme/fast-cgi-client/tree/v1.0.0

[#1]: https://github.com/hollodotme/fast-cgi-client/issues/1
[#2]: https://github.com/hollodotme/fast-cgi-client/issues/2
[#5]: https://github.com/hollodotme/fast-cgi-client/issues/5
[#6]: https://github.com/hollodotme/fast-cgi-client/issues/6
