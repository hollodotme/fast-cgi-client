---
title: Migrating from 3.x to 4.x
sidebar_label: Migration guide
sidebar_position: 4
description: What you need to change in your code when upgrading from version 3.x to 4.x.
---

Version 4.x modernizes the library for PHP 8 and fixes a number of protocol issues. Most applications only need to
change **how they pass the content of a request**. This guide lists every change that can affect your code, with
examples of the code before and after.

The [changelog](/changelog/4.x) lists all changes, including the new features and fixes.

## Checklist

1. [Upgrade to PHP >= 8.0 and install the `fileinfo` extension](#requirements)
2. [Pass request content as an object instead of a string](#request-content)
3. [Replace `newWithRequestContent()` with the constructor](#named-constructor)
4. [Catch exceptions of `readResponses()` and `readReadyResponses()`](#reading-multiple-responses)
5. [Pass a timeout to `waitForResponse()` and `waitForResponses()` for long-running scripts](#waiting-for-responses)
6. [Replace `Socket::STREAM_SELECT_USEC`](#stream-select-timeout)
7. If you implement interfaces of the library: [add the new methods](#own-implementations-of-interfaces)
8. If you extend classes of the library: [adapt your subclasses](#extended-classes)

## Requirements

Version 4.x requires **PHP >= 8.0** and the **`fileinfo`** extension, which is used to detect the MIME type of files
in multipart form-data requests.

```bash
composer require "hollodotme/fast-cgi-client:^4.0"
```

If you need to stay on PHP 7.1 - 7.4, keep using version 3.x. It still gets bug fixes, but no new features.

## Request content

In 3.x, the constructor of a request and `setContent()` took the content as a string, and you had to set the matching
content type yourself. The content classes `UrlEncodedFormData`, `JsonData` and `MultipartFormData` already existed
since 3.1.0, but were mainly used with the named constructor [`newWithRequestContent()`](#named-constructor).
In 4.x, the content is always an object implementing `ComposesRequestContent`. It composes the content and determines
its content type, so you pass it as the optional second argument of the constructor or to `setContent()`.

| Content in 3.x                                             | Content object in 4.x                 | Content type                        |
|------------------------------------------------------------|---------------------------------------|-------------------------------------|
| `http_build_query( $data )`                                | `new UrlEncodedFormData( $data )`     | `application/x-www-form-urlencoded` |
| `json_encode( $data )` with `setContentType()`             | `new JsonData( $data )`               | `application/json`                  |
| `newWithRequestContent()` with `MultipartFormData`         | `new MultipartFormData( $data, $files )` | `multipart/form-data; boundary=…`   |
| Plain string with `setContentType( 'text/plain' )`         | `new PlainText( $text )` (new in 4.x) | `text/plain`                        |
| Empty string `''`                                          | no content: omit the argument         | `application/x-www-form-urlencoded` |

### URL encoded form data

```php
# 3.x
$request = new PostRequest( '/path/to/script.php', http_build_query( ['key' => 'value'] ) );

# 4.x
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;

$request = new PostRequest( '/path/to/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );
```

### JSON

```php
# 3.x
$request = new PostRequest( '/path/to/script.php', json_encode( ['key' => 'value'] ) );
$request->setContentType( 'application/json' );

# 4.x
use hollodotme\FastCGI\RequestContents\JsonData;

$request = new PostRequest( '/path/to/script.php', new JsonData( ['key' => 'value'] ) );
```

### Plain text

```php
# 3.x
$request = new PostRequest( '/path/to/script.php', 'plain text' );
$request->setContentType( 'text/plain' );

# 4.x
use hollodotme\FastCGI\RequestContents\PlainText;

$request = new PostRequest( '/path/to/script.php', new PlainText( 'plain text' ) );
```

### Requests without content

```php
# 3.x
$request = new GetRequest( '/path/to/script.php', '' );

# 4.x
$request = new GetRequest( '/path/to/script.php' );
```

### Setting the content later

`setContent()` expects a content object now. It also sets the content type of the request and **overwrites a content
type that was set before**. If you need a content type that differs from the one of the content object, call
`setContentType()` after `setContent()`:

```php
# 3.x
$request->setContentType( 'application/vnd.api+json' );
$request->setContent( json_encode( $data ) );

# 4.x
$request->setContent( new JsonData( $data ) );
$request->setContentType( 'application/vnd.api+json' );
```

### Reading the content of a request

`getContent()` returns the content object, or `null` if the request has no content, instead of a string:

```php
# 3.x
$body = $request->getContent();

# 4.x
$body = $request->getContent()?->getContent() ?? '';
```

### Other content types

For content types that are not covered by the classes in `hollodotme\FastCGI\RequestContents`, implement the
`ComposesRequestContent` interface:

```php
use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use hollodotme\FastCGI\Requests\PostRequest;

final class XmlData implements ComposesRequestContent
{
	public function __construct( private string $xml )
	{
	}

	public function getContentType() : string
	{
		return 'application/xml';
	}

	public function getContent() : string
	{
		return $this->xml;
	}
}

$request = new PostRequest( '/path/to/script.php', new XmlData( '<root/>' ) );
```

The content length, and the content type if it was not set explicitly, are determined from the content object when
the request is sent. Changes to the content object after it was passed to the request, e.g. files added to a
`MultipartFormData` object, are sent as well.

## Named constructor

The named constructor `newWithRequestContent()`, introduced in 3.1.0, was removed from all request classes. The
constructor accepts the content object directly:

```php
# 3.x
$request = PostRequest::newWithRequestContent( '/path/to/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );

# 4.x
$request = new PostRequest( '/path/to/script.php', new UrlEncodedFormData( ['key' => 'value'] ) );
```

## Reading multiple responses

`readResponses()` and `readReadyResponses()` throw the exception of a response that cannot be read, e.g. a
`TimedoutException` or a `ReadFailedException`. In 3.x, such responses were skipped silently, so you got fewer
responses than requests. Unknown socket IDs are still skipped.

```php
# 3.x: failed responses are missing
foreach ( $client->readResponses( 3000, ...$socketIds ) as $response )
{
	echo $response->getBody();
}

# 4.x: handle failed responses
use hollodotme\FastCGI\Exceptions\FastCGIClientException;

try
{
	foreach ( $client->readResponses( 3000, ...$socketIds ) as $response )
	{
		echo $response->getBody();
	}
}
catch ( FastCGIClientException $e )
{
	# The remaining responses of this call are not read
	echo $e->getMessage();
}
```

If you want to handle each failure individually and continue with the other responses, register failure callbacks
and use `waitForResponses()` or `handleReadyResponses()` instead, see
[multiple requests](./usage/multiple-requests.mdx#sending-multiple-requests-and-notifying-callbacks-reactive).

## Waiting for responses

`waitForResponse()` and `waitForResponses()` stop waiting after a timeout now, even if you pass none. The default is
the read/write timeout of the connection (`Defaults::READ_WRITE_TIMEOUT`, 5000 ms). The failure callbacks of requests
without response are notified with a `TimedoutException` then. In 3.x, both methods waited until the response was
received, if no timeout was passed (3.1.8 still does), so scripts that run longer than the read/write timeout before
they send output fail in 4.x. A timeout you pass limits the whole waiting time now, not only the reading of the
response.

```php
# 3.x: waits until all responses are received, however long the scripts run
$client->waitForResponses();

# 4.x: waits at most the read/write timeout of the connections (default: 5000 ms)
$client->waitForResponses();

# 4.x: waits up to 60 seconds
$client->waitForResponses( 60000 );
```

## Stream select timeout

The public constant `Socket::STREAM_SELECT_USEC` (200000 µs) was removed. How long the client waits when checking for
responses is configurable per connection now, as third timeout in milliseconds. The default
`Defaults::STREAM_SELECT_TIMEOUT` is 200 ms, the value used in previous versions.

```php
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

$connection = new NetworkSocket(
	'127.0.0.1',
	9000,
	5000,    # connect timeout
	5000,    # read/write timeout
	50       # stream select timeout
);

$connection = new UnixDomainSocket( '/var/run/php/php-fpm.sock', 5000, 5000, 50 );
```

## Own implementations of interfaces

If you implement interfaces of the library yourself, e.g. in test doubles, add the new methods:

| Interface                    | New or changed method                                        |
|------------------------------|--------------------------------------------------------------|
| `ConfiguresSocketConnection` | `getStreamSelectTimeout() : int` (milliseconds)              |
| `ProvidesResponseData`       | `getStatusCode() : int`                                      |
| `ProvidesRequestData`        | `getContent() : ?ComposesRequestContent` instead of `string` |

```php
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\SocketConnections\Defaults;

final class MyConnection implements ConfiguresSocketConnection
{
	# ...

	public function getStreamSelectTimeout() : int
	{
		return Defaults::STREAM_SELECT_TIMEOUT;
	}
}
```

## Extended classes

If you extend `AbstractRequest` or a request class, adapt overridden `setContent()` and `getContent()` methods and
calls of `parent::__construct()` to the content object. Check your subclasses for methods that collide with the new
public methods `Client#tryRequest()`, `Client#tryAsyncRequest()`, `AbstractRequest#setQueryParams()`,
`AbstractRequest#getQueryParams()`, `AbstractRequest#getQueryString()` and `Response#getStatusCode()`.

## Changed behaviour

These changes need no code changes, but you may notice them:

* Output that starts with header lines without a blank line after them is the body of the response, without headers.
  Header values continued on the next line (obsolete line folding) are joined to the value of the header.
* `hasResponse()` returns `true` for a socket whose response was already read with `readResponse()`.
  `getSocketIdsHavingResponse()`, `readReadyResponses()` and `handleReadyResponses()` ignore idle sockets.
* Received packets are validated. A response with an invalid packet, e.g. from a server that is no FastCGI server,
  throws a `ReadFailedException` instead of hanging in an endless loop.
* A request that could only be written partly throws a `TimedoutException` or `WriteFailedException` instead of
  being treated as sent.
* Request parameters longer than 65535 bytes in total are sent in multiple records instead of corrupting the request.
* Idle sockets that were closed by the server are replaced before a request is sent.

## New features worth a look

* [`tryRequest()` and `tryAsyncRequest()`](./usage/single-requests.mdx#retry-sending-a-request) send a request
  again on another socket, if writing it failed.
* [Query parameters](./usage/requests.mdx#query-parameters) can be passed as an array with `setQueryParams()`.
* [`Response#getStatusCode()`](./usage/responses.md) returns the status code of the `Status` header.
* [`PlainText`](./usage/requests.mdx#request-contents) composes `text/plain` content.
