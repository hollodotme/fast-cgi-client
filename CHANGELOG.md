# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

## [1.1.0] - YYYY-MM-DD

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

[1.1.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/hollodotme/fast-cgi-client/tree/v1.0.0

[#1]: https://github.com/hollodotme/fast-cgi-client/issues/1
[#2]: https://github.com/hollodotme/fast-cgi-client/issues/2
[#5]: https://github.com/hollodotme/fast-cgi-client/issues/5
