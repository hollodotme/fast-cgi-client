---
title: Responses
sidebar_position: 5
---

Responses are defined by the following interface:

```php
<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

interface ProvidesResponseData
{
	public function getHeaders() : array;

	public function getHeader( string $headerKey ) : array;
	
	public function getHeaderLine( string $headerKey ) : string;

	public function getBody() : string;

	public function getOutput() : string;
	
	public function getError() : string;

	public function getDuration() : float;

	public function getStatusCode() : int;
}
```

Assuming `/path/to/target/script.php` has the following content:

```php
<?php declare(strict_types=1);

echo 'Hello World';
error_log('Some error');
```

The raw response would look like this:

```
Content-type: text/html; charset=UTF-8

Hello World
```

**Please note:**

* All headers sent by your script will precede the response body
* The headers are separated from the body by the first blank line. If the output does not start with headers
  followed by a blank line, the whole output is the body.
* The status of a response is sent as `Status` header, if the script sets one. There is no HTTP status line like
  `HTTP/1.1 200 OK`, because there is no web server involved. A response that starts with such a line has no
  headers, its whole output is the body.

Custom headers will also be part of the response:

```php
<?php declare(strict_types=1);

header('X-Custom: Header');
header('Set-Cookie: yummy_cookie=choco');
header('Set-Cookie: tasty_cookie=strawberry');

echo 'Hello World';
error_log('Some error');
```

The raw response would look like this:

```
X-Custom: Header
Set-Cookie: yummy_cookie=choco
Set-Cookie: tasty_cookie=strawberry
Content-type: text/html; charset=UTF-8

Hello World
```

You can retrieve all of the response data separately from the response object:

```php
# Get all values of a single response header
$response->getHeader('Set-Cookie'); 
// ['yummy_cookie=choco', 'tasty_cookie=strawberry']

# Get all values of a single response header as comma separated string
$response->getHeaderLine('Set-Cookie');
// 'yummy_cookie=choco, tasty_cookie=strawberry'

# Get all headers as grouped array
$response->getHeaders();
// [
//   'X-Custom' => [
//      'Header',
//   ],
//   'Set-Cookie' => [
//      'yummy_cookie=choco',
//      'tasty_cookie=strawberry',
//   ],
//   'Content-type' => [
//      'text/html; charset=UTF-8',
//   ],
// ]

# Get the body
$response->getBody(); 
// 'Hello World'

# Get the status code of the Status header, 200 if there is no Status header
$response->getStatusCode();
// 200

# Get the raw response output from STDOUT stream
$response->getOutput();
// 'X-Custom: Header
// Set-Cookie: yummy_cookie=choco
// Set-Cookie: tasty_cookie=strawberry
// Content-type: text/html; charset=UTF-8
// 
// Hello World'

# Get the raw response from STDERR stream
$response->getError();
// Some error

# Get the duration
$response->getDuration(); 
// e.g. 0.0016319751739502
```

### Size of responses

The client keeps the whole response in memory until the FastCGI server has ended the request. There is no upper limit
for the size of a response:

* The output (STDOUT) and the error output (STDERR) are collected completely, also if you use pass-through
  callbacks. The callbacks receive the output while it arrives, but the response object still contains all of it.
* The read/write timeout limits how long the client waits for the next part of the response, not how long it reads
  the response as a whole. A server that keeps sending output without ending the request, even slowly, makes the
  client use more and more memory, until PHP's `memory_limit` is reached.

php-fpm ends every request, so with php-fpm a response is as large as the output of the script. Only connect to
FastCGI servers you trust, and make sure that the responses you expect fit into the `memory_limit` of the process
running the client.
