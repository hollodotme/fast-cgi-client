---
title: Exceptions
sidebar_position: 6
---

All exceptions thrown by the client while connecting, sending requests and reading responses extend
`hollodotme\FastCGI\Exceptions\FastCGIClientException`, so you can catch them all at once or handle them separately:

| Exception               | Is thrown, if ...                                                                                   |
|-------------------------|-----------------------------------------------------------------------------------------------------|
| `ConnectException`      | the connection to the FastCGI server could not be established.                                      |
| `WriteFailedException`  | the request could not be written to the socket, or the FastCGI server rejected the request, e.g. because it is overloaded. |
| `ReadFailedException`   | the response could not be read, e.g. because the process handling the request was terminated, the response is not a valid FastCGI response, or the given socket ID is unknown. |
| `TimedoutException`     | writing the request or reading the response exceeded the read/write timeout.                        |

The methods deal differently with exceptions that occur while reading a response:

* `sendRequest()`, `tryRequest()` and `readResponse()` throw them.
* `readResponses()` and `readReadyResponses()` throw them as well. The remaining responses are not read then,
  but you can read them with another call. Unknown socket IDs are skipped.
* `waitForResponse()`, `waitForResponses()`, `handleResponse()`, `handleResponses()` and `handleReadyResponses()`
  pass them to the failure callbacks of the request instead of throwing them. `waitForResponse()` and
  `waitForResponses()` also notify the failure callbacks with a `TimedoutException`, if there is no response within
  the timeout.

The client validates every packet it receives. A response is rejected with a `ReadFailedException`, if a packet

* does not have the FastCGI protocol version 1, e.g. because the server is a HTTP server and not a FastCGI server,
* is not a stdout, stderr or end-request record,
* belongs to another request than the one that was sent,
* is an end-request record with an unexpected length.

If the connection is closed before the response is complete, a `ReadFailedException` is thrown as well.
If the rest of a response does not arrive within the read/write timeout, a `TimedoutException` is thrown.

Invalid arguments are reported with PHP's `InvalidArgumentException`, e.g. if a file for a multipart form-data request
does not exist. `JsonData` throws a `RuntimeException`, if the data cannot be encoded.

---
