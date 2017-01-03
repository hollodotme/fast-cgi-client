# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) and [Keep a CHANGELOG](http://keepachangelog.com).

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

[1.0.0]: https://github.com/hollodotme/fast-cgi-client/tree/v1.0.0
