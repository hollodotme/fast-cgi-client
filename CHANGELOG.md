# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

## [2.0.1] - 2017-02-23
   
### Fixed
   
- Erroneous response returned by Client::sendRequest() and Client::waitForResponse() - [#1](https://github.com/hollodotme/fast-cgi-client/issues/1)
   
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

[2.0.1]: https://github.com/hollodotme/fast-cgi-client/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/hollodotme/fast-cgi-client/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/hollodotme/fast-cgi-client/tree/v1.0.0
