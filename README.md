![FastCGI Client CI PHP 7.1 - 8.0](https://github.com/hollodotme/fast-cgi-client/workflows/FastCGI%20Client%20CI%20PHP%207.1%20-%208.0/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/hollodotme/fast-cgi-client/v/stable)](https://packagist.org/packages/hollodotme/fast-cgi-client)
[![Total Downloads](https://poser.pugx.org/hollodotme/fast-cgi-client/downloads)](https://packagist.org/packages/hollodotme/fast-cgi-client)
[![codecov](https://codecov.io/gh/hollodotme/fast-cgi-client/branch/master/graph/badge.svg)](https://codecov.io/gh/hollodotme/fast-cgi-client)

# Fast CGI Client

A PHP fast CGI client to send requests (a)synchronously to PHP-FPM using
the [FastCGI Protocol](http://www.mit.edu/~yandros/doc/specs/fcgi-spec.html).

This library is based on the work
of [Pierrick Charron](https://github.com/adoy)'s [PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client/)
and was ported and modernized to latest PHP versions, extended with some features for handling multiple requests (in
loops) and unit and integration tests as well.

---

This is the documentation of the latest release.

Please have a look at the [backwards incompatible changes (BC breaks) in the changelog](./CHANGELOG.md).

Please see the following links for earlier releases:

* PHP >= 7.0 (EOL) [v1.0.0], [v1.0.1], [v1.1.0], [v1.2.0], [v1.3.0], [v1.4.0], [v1.4.1], [v1.4.2]
* PHP >= 7.1 [v2.0.0], [v2.0.1], [v2.1.0], [v2.2.0], [v2.3.0], [v2.4.0], [v2.4.1], [v2.4.2], [v2.4.3], [v2.5.0],
  [v2.6.0], [v2.7.0], [v2.7.1], [v2.7.2], [v3.0.0-alpha], [v3.0.0-beta], [v3.0.0], [v3.0.1], [v3.1.0], [v3.1.1],
  [v3.1.2], [v3.1.3], [v3.1.4]

Read more about the journey to and changes in `v2.6.0`
in [this blog post](https://github.com/hollodotme/fast-cgi-client/wiki/Background-Info-FastCgiClient-Version-2.6.0).

---

You can find an experimental use-case in my related blog posts:

* [Experimental async PHP vol. 1](https://github.com/hollodotme/fast-cgi-client/wiki/Experimental-Async-Php-Volume-1)
* [Experimental async PHP vol. 2](https://github.com/hollodotme/fast-cgi-client/wiki/Experimental-Async-Php-Volume-2)

You can also find slides of my talks about this project on [speakerdeck.com](https://speakerdeck.com/hollodotme).

---

## Installation

```bash
composer require hollodotme/fast-cgi-client:3.1.*
```

---

## Usage - connections

This library supports two types of connecting to a FastCGI server:

1. Via network socket
2. Via unix domain socket

### Create a network socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$connection = new NetworkSocket(
	'127.0.0.1',    # Hostname
	9000,           # Port
	5000,           # Connect timeout in milliseconds (default: 5000)
	5000            # Read/write timeout in milliseconds (default: 5000)
);
```

### Create a unix domain socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

$connection = new UnixDomainSocket(
	'/var/run/php/php7.3-fpm.sock',     # Socket path
	5000,                               # Connect timeout in milliseconds (default: 5000)
	5000                                # Read/write timeout in milliseconds (default: 5000)
);
```

## Usage - single request

The following examples assume that the content of `/path/to/target/script.php` looks like this:

```php
<?php declare(strict_types=1);

sleep((int)($_REQUEST['sleep'] ?? 0));
echo $_REQUEST['key'] ?? '';
```

### Send request synchronously

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = http_build_query(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

$response = $client->sendRequest($connection, $request);

echo $response->getBody();
```

```
# prints
value
```

### Send request asynchronously (Fire and forget)

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = http_build_query(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

$socketId = $client->sendAsyncRequest($connection, $request);

echo "Request sent, got ID: {$socketId}";
```

### Read the response, after sending the async request

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = http_build_query(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

$socketId = $client->sendAsyncRequest($connection, $request);

echo "Request sent, got ID: {$socketId}";

# Do something else here in the meanwhile

# Blocking call until response is received or read timed out
$response = $client->readResponse( 
	$socketId,     # The socket ID 
	3000            # Optional timeout to wait for response,
					# defaults to read/write timeout in milliseconds set in connection
);

echo $response->getBody();
```

```
# prints
value
```

### Notify a callback when async request responded

You can register response and failure callbacks for each request. In order to notify the callbacks when a response was
received instead of returning it, you need to use the `waitForResponse(int $socketId, ?int $timeoutMs = null)` method.

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use Throwable;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = http_build_query(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

# Register a response callback, expects a `ProvidesResponseData` instance as the only paramter
$request->addResponseCallbacks(
	static function( ProvidesResponseData $response )
	{
		echo $response->getBody();	
	}
);

# Register a failure callback, expects a `\Throwable` instance as the only parameter
$request->addFailureCallbacks(
	static function ( Throwable $throwable )
	{
		echo $throwable->getMessage();	
	}
);

$socketId = $client->sendAsyncRequest($connection, $request);

echo "Request sent, got ID: {$socketId}";

# Do something else here in the meanwhile

# Blocking call until response is received or read timed out
# If response was received all registered response callbacks will be notified
$client->waitForResponse( 
	$socketId,     # The socket ID 
	3000            # Optional timeout to wait for response,
					# defaults to read/write timeout in milliseconds set in connection
);

# ... is the same as

while(true)
{
	if ($client->hasResponse($socketId))
	{
		$client->handleResponse($socketId, 3000);
		break;
	}
}
```

```
# prints
value
```

---

## Usage - multiple requests

### Sending multiple requests and reading their responses (order preserved)

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);

$request1 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '1']));
$request2 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '2']));
$request3 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '3']));

$socketIds = [];

$socketIds[] = $client->sendAsyncRequest($connection, $request1);
$socketIds[] = $client->sendAsyncRequest($connection, $request2);
$socketIds[] = $client->sendAsyncRequest($connection, $request3);

echo 'Sent requests with IDs: ' . implode( ', ', $socketIds ) . "\n";

# Do something else here in the meanwhile

# Blocking call until all responses are received or read timed out
# Responses are read in same order the requests were sent
foreach ($client->readResponses(3000, ...$socketIds) as $response)
{
	echo $response->getBody() . "\n";	
}
```

```
# prints
1
2
3
```

### Sending multiple requests and reading their responses (reactive)

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);

$request1 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '1', 'sleep' => 3]));
$request2 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '2', 'sleep' => 2]));
$request3 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '3', 'sleep' => 1]));

$socketIds = [];

$socketIds[] = $client->sendAsyncRequest($connection, $request1);
$socketIds[] = $client->sendAsyncRequest($connection, $request2);
$socketIds[] = $client->sendAsyncRequest($connection, $request3);

echo 'Sent requests with IDs: ' . implode( ', ', $socketIds ) . "\n";

# Do something else here in the meanwhile

# Loop until all responses were received
while ( $client->hasUnhandledResponses() )
{
	# read all ready responses
	foreach ( $client->readReadyResponses( 3000 ) as $response )
	{
		echo $response->getBody() . "\n";
	}
	
	echo '.';
}

# ... is the same as

while ( $client->hasUnhandledResponses() )
{
	$readySocketIds = $client->getSocketIdsHavingResponse();
	
	# read all ready responses
	foreach ( $client->readResponses( 3000, ...$readySocketIds ) as $response )
	{
		echo $response->getBody() . "\n";
	}
	
	echo '.';
}

# ... is the same as

while ( $client->hasUnhandledResponses() )
{
	$readySocketIds = $client->getSocketIdsHavingResponse();
	
	# read all ready responses
	foreach ($readySocketIds as $socketId)
	{
		$response = $client->readResponse($socketId, 3000);
		echo $response->getBody() . "\n";
	}
	
	echo '.';
}
```

```
# prints
...............................................3
...............................................2
...............................................1
```

### Sending multiple requests and notifying callbacks (reactive)

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use Throwable;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);

$responseCallback = static function( ProvidesResponseData $response )
{
	echo $response->getBody();	
};

$failureCallback = static function ( Throwable $throwable )
{
	echo $throwable->getMessage();	
};

$request1 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '1', 'sleep' => 3]));
$request2 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '2', 'sleep' => 2]));
$request3 = new PostRequest('/path/to/target/script.php', http_build_query(['key' => '3', 'sleep' => 1]));

$request1->addResponseCallbacks($responseCallback);
$request1->addFailureCallbacks($failureCallback);

$request2->addResponseCallbacks($responseCallback);
$request2->addFailureCallbacks($failureCallback);

$request3->addResponseCallbacks($responseCallback);
$request3->addFailureCallbacks($failureCallback);

$socketIds = [];

$socketIds[] = $client->sendAsyncRequest($connection, $request1);
$socketIds[] = $client->sendAsyncRequest($connection, $request2);
$socketIds[] = $client->sendAsyncRequest($connection, $request3);

echo 'Sent requests with IDs: ' . implode( ', ', $socketIds ) . "\n";

# Do something else here in the meanwhile

# Blocking call until all responses were received and all callbacks notified
$client->waitForResponses(3000);

# ... is the same as

while ( $client->hasUnhandledResponses() )
{
	$client->handleReadyResponses(3000);
}

# ... is the same as

while ( $client->hasUnhandledResponses() )
{
	$readySocketIds = $client->getSocketIdsHavingResponse();
	
	# read all ready responses
	foreach ($readySocketIds as $socketId)
	{
		$client->handleResponse($socketId, 3000);
	}
}
```

```
# prints
3
2
1
```

### Reading output buffer from worker script using pass through callbacks

It may be useful to see the progression of a requested script by having access to the flushed output of that script. The
php.ini default output buffering for php-fpm is 4096 bytes and is (hard-coded) disabled for CLI
mode. ([See documentation](http://php.net/manual/en/outcontrol.configuration.php#ini.output-buffering))
Calling `ob_implicit_flush()` causes every call to `echo` or `print` to immediately be flushed.

The callee script could look like this:

```php
<?php declare(strict_types=1);

ob_implicit_flush();

function show( string $string )
{
	echo $string . str_repeat( "\r", 4096 - strlen( $string ) ) . "\n";
	sleep( 1 );
}

show( 'One' );
show( 'Two' );
show( 'Three' );

error_log("Oh oh!\n");

echo 'End';
```

The caller than could look like this:

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);

$passThroughCallback = static function( string $outputBuffer, string $errorBuffer )
{
	echo 'Output: ' . $outputBuffer;
	echo 'Error: ' . $errorBuffer;
};

$request = new GetRequest('/path/to/target/script.php', '');
$request->addPassThroughCallbacks( $passThroughCallback );

$client->sendAsyncRequest($connection, $request);
$client->waitForResponses();
```

```
# prints immediately
Buffer: Content-type: text/html; charset=UTF-8

Output: One
# sleeps 1 sec
Output: Two
# sleeps 1 sec
Output: Three
# sleeps 1 sec
Error: Oh oh!
Output: End
```

----

### Requests

Requests are defined by the following interface:

```php
<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

interface ProvidesRequestData
{
	public function getGatewayInterface() : string;

	public function getRequestMethod() : string;

	public function getScriptFilename() : string;

	public function getServerSoftware() : string;

	public function getRemoteAddress() : string;

	public function getRemotePort() : int;

	public function getServerAddress() : string;

	public function getServerPort() : int;

	public function getServerName() : string;

	public function getServerProtocol() : string;

	public function getContentType() : string;

	public function getContentLength() : int;

	public function getContent() : string;

	public function getCustomVars() : array;

	public function getParams() : array;
	
	public function getRequestUri() : string;
}
```

Alongside with this interface, this package provides an abstract request class, containing default values to make the
API more handy for you and 5 request method implementations of this abstract class:

* `hollodotme\FastCGI\Requests\GetRequest`
* `hollodotme\FastCGI\Requests\PostRequest`
* `hollodotme\FastCGI\Requests\PutRequest`
* `hollodotme\FastCGI\Requests\PatchRequest`
* `hollodotme\FastCGI\Requests\DeleteRequest`

So you can either implement the interface, inherit from the abstract class or simply use one of the 5 implementations.

#### Default values

The abstract request class defines several default values which you can optionally overwrite:

| Key               | Default value                     | Comment                                                                                 |
|-------------------|-----------------------------------|-----------------------------------------------------------------------------------------|
| GATEWAY_INTERFACE | FastCGI/1.0                       | Cannot be overwritten, because this is the only supported version of the client.        |
| SERVER_SOFTWARE   | hollodotme/fast-cgi-client        |                                                                                         |
| REMOTE_ADDR       | 192.168.0.1                       |                                                                                         |
| REMOTE_PORT       | 9985                              |                                                                                         |
| SERVER_ADDR       | 127.0.0.1                         |                                                                                         |
| SERVER_PORT       | 80                                |                                                                                         |
| SERVER_NAME       | localhost                         |                                                                                         |
| SERVER_PROTOCOL   | HTTP/1.1                          | You can use the public class constants in `hollodotme\FastCGI\Constants\ServerProtocol` |
| CONTENT_TYPE      | application/x-www-form-urlencoded |                                                                                         |
| REQUEST_URI       | <empty string>                    |                                                                                         |
| CUSTOM_VARS       | empty array                       | You can use the methods `setCustomVar`, `addCustomVars` to add own key-value pairs      |

#### Request contents

In order to make the composition of different request content types easier there are classes covering the typical
content types:

* [UrlEncodedFormData](./src/RequestContents/UrlEncodedFormData.php)
* [MultipartFormData](./src/RequestContents/MultipartFormData.php)
* [JsonData](./src/RequestContents/JsonData.php)

You can create your own request content type composer by implementing the following interface:

[**ComposesRequestContent**](./src/Interfaces/ComposesRequestContent.php)

```php
interface ComposesRequestContent
{
	public function getContentType() : string;

	public function getContent() : string;
}
```

##### Request content example: URL encoded form data (application/x-www-form-urlencoded)

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Client;

$client = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );

$urlEncodedContent = new UrlEncodedFormData(
	[
		'nested' => [
			'one',
			'two'   => 'value2',
			'three' => [
				'value3',
			],
		],
	]
);

$postRequest = PostRequest::newWithRequestContent( '/path/to/target/script.php', $urlEncodedContent );

$response = $client->sendRequest( $connection, $postRequest );
```

This example produces the following `$_POST` array at the target script:

```
Array
(
    [nested] => Array
        (
            [0] => one
            [two] => value2
            [three] => Array
                (
                    [0] => value3
                )

        )
)
```

##### Request content example: multipart form data (multipart/form-data)

Multipart form-data can be used to transfer any binary data as files to the target script just like a file upload in a
browser does.

**PLEASE NOTE:** Multipart form-data content type works with POST requests only.

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\RequestContents\MultipartFormData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Client;

$client = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );

$multipartContent = new MultipartFormData(
    # POST data
	[
        'simple'          => 'value',
		'nested[]'        => 'one',
		'nested[two]'     => 'value2',
		'nested[three][]' => 'value3',
	],
	# FILES
	[
		'file1'        => __FILE__,
		'files[1]'     => __FILE__,
		'files[three]' => __FILE__,
	]
);

$postRequest = PostRequest::newWithRequestContent( '/path/to/target/script.php', $multipartContent );

$response = $client->sendRequest( $connection, $postRequest );
```

This example produces the following `$_POST` and `$_FILES` array at the target script:

```
# $_POST
Array
(
    [simple] => value
    [nested] => Array
        (
            [0] => one
            [two] => value2
            [three] => Array
                (
                    [0] => value3
                )

        )

)

# $_FILES
Array
(
    [file1] => Array
        (
            [name] => multipart.php
            [type] => application/octet-stream
            [tmp_name] => /tmp/phpiIdCNM
            [error] => 0
            [size] => 1086
        )

    [files] => Array
        (
            [name] => Array
                (
                    [1] => multipart.php
                    [three] => multipart.php
                )

            [type] => Array
                (
                    [1] => application/octet-stream
                    [three] => application/octet-stream
                )

            [tmp_name] => Array
                (
                    [1] => /tmp/phpAjHINL
                    [three] => /tmp/phpicAmjN
                )

            [error] => Array
                (
                    [1] => 0
                    [three] => 0
                )

            [size] => Array
                (
                    [1] => 1086
                    [three] => 1086
                )

        )

)
```

##### Request content example: JSON encoded data (application/json)

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\RequestContents\JsonData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Client;

$client = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );

$jsonContent = new JsonData(
	[
		'nested' => [
			'one',
			'two'   => 'value2',
			'three' => [
				'value3',
			],
		],
	]
);

$postRequest = PostRequest::newWithRequestContent( '/path/to/target/script.php', $jsonContent );

$response = $client->sendRequest( $connection, $postRequest );
```

This example produces the following content for `php://input` at the target script:

```json
{
  "nested": {
    "0": "one",
    "two": "value2",
    "three": [
      "value3"
    ]
  }
}
```

### Responses

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
* There won't be any HTTP specific headers like `HTTP/1.1 200 OK`, because there is no webserver involved.

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

# Get the raw response output from STDOUT stream
$response->getOutput();
// 'X-Custom: Header
// Set-Cookie: yummy_cookie=choco
// Set-Cookie: tasty_cookie=strawberry
// Content-type: text/html; charset=UTF-8
// 
// Hello World'

# Get the raw response from SFTERR stream
$response->getError();
// Some error

# Get the duration
$response->getDuration(); 
// e.g. 0.0016319751739502
```

---

## Trouble shooting

### "File not found." response (php-fpm)

This response is generated by php-fpm for the preceding error `Primary script unknown` in case the requested script does
not exists or there are path traversals in its path like `/var/www/../run/script.php`.

Although the given path may exist and would resolve to an absolute path in the file system, php-fpm does not do any path
resolution and accepts only **absolute paths** to the script you want to execute.

Programatically you can handle this error like this:

```php
if (preg_match("#^Primary script unknown\n?$#", $response->getError()))
{
    throw new Exception('Could not find or resolve path to script for execution.');
}

# OR

if ('404 Not Found' === $response->getHeaderLine('Status'))
{
    throw new Exception('Could not find or resolve path to script for execution.');
}

# OR

if ('File not found.' === trim($response->getBody()))
{
    throw new Exception('Could not find or resolve path to script for execution.');
}
```

---

## Prepare local development environment

This requires `docker` and `docker-compose` installed on your machine.

    make update

## Run examples

	make examples

## Run all tests

    make tests

## Command line tool (for local debugging only)

**Please note:** `bin/fcgiget` is not included and linked to `vendor/bin` via composer anymore since version `v3.1.2`for
security reasons. [Read more.](https://github.com/hollodotme/fast-cgi-client/pull/58)

Run a call through a network socket:

    docker-compose exec php74 php bin/fcgiget localhost:9001/status

Run a call through a Unix Domain Socket

    docker-compose exec php74 php bin/fcgiget unix:///var/run/php-uds.sock/status

This shows the response of the php-fpm status page.


[v3.1.4]: https://github.com/hollodotme/fast-cgi-client/blob/v3.1.4/README.md

[v3.1.3]: https://github.com/hollodotme/fast-cgi-client/blob/v3.1.3/README.md

[v3.1.2]: https://github.com/hollodotme/fast-cgi-client/blob/v3.1.2/README.md

[v3.1.1]: https://github.com/hollodotme/fast-cgi-client/blob/v3.1.1/README.md

[v3.1.0]: https://github.com/hollodotme/fast-cgi-client/blob/v3.1.0/README.md

[v3.0.1]: https://github.com/hollodotme/fast-cgi-client/blob/v3.0.1/README.md

[v3.0.0]: https://github.com/hollodotme/fast-cgi-client/blob/v3.0.0/README.md

[v3.0.0-beta]: https://github.com/hollodotme/fast-cgi-client/blob/v3.0.0-beta/README.md

[v3.0.0-alpha]: https://github.com/hollodotme/fast-cgi-client/blob/v3.0.0-alpha/README.md

[v2.7.2]: https://github.com/hollodotme/fast-cgi-client/blob/v2.7.2/README.md

[v2.7.1]: https://github.com/hollodotme/fast-cgi-client/blob/v2.7.1/README.md

[v2.7.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.7.0/README.md

[v2.6.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.6.0/README.md

[v2.5.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.5.0/README.md

[v2.4.3]: https://github.com/hollodotme/fast-cgi-client/blob/v2.4.3/README.md

[v2.4.2]: https://github.com/hollodotme/fast-cgi-client/blob/v2.4.2/README.md

[v2.4.1]: https://github.com/hollodotme/fast-cgi-client/blob/v2.4.1/README.md

[v2.4.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.4.0/README.md

[v2.3.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.3.0/README.md

[v2.2.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.2.0/README.md

[v2.1.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.1.0/README.md

[v2.0.1]: https://github.com/hollodotme/fast-cgi-client/blob/v2.0.1/README.md

[v2.0.0]: https://github.com/hollodotme/fast-cgi-client/blob/v2.0.0/README.md

[v1.4.2]: https://github.com/hollodotme/fast-cgi-client/blob/v1.4.2/README.md

[v1.4.1]: https://github.com/hollodotme/fast-cgi-client/blob/v1.4.1/README.md

[v1.4.0]: https://github.com/hollodotme/fast-cgi-client/blob/v1.4.0/README.md

[v1.3.0]: https://github.com/hollodotme/fast-cgi-client/blob/v1.3.0/README.md

[v1.2.0]: https://github.com/hollodotme/fast-cgi-client/blob/v1.2.0/README.md

[v1.1.0]: https://github.com/hollodotme/fast-cgi-client/blob/v1.1.0/README.md

[v1.0.1]: https://github.com/hollodotme/fast-cgi-client/blob/v1.0.1/README.md

[v1.0.0]: https://github.com/hollodotme/fast-cgi-client/blob/v1.0.0/README.md
