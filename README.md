[![Build Status](https://travis-ci.org/hollodotme/fast-cgi-client.svg?branch=master)](https://travis-ci.org/hollodotme/fast-cgi-client)
[![Tested PHP versions](https://php-eye.com/badge/hollodotme/fast-cgi-client/tested.svg?branch=1.x-stable)](https://php-eye.com/package/hollodotme/fast-cgi-client)
[![Latest Stable Version](https://poser.pugx.org/hollodotme/fast-cgi-client/v/stable)](https://packagist.org/packages/hollodotme/fast-cgi-client) 
[![Total Downloads](https://poser.pugx.org/hollodotme/fast-cgi-client/downloads)](https://packagist.org/packages/hollodotme/fast-cgi-client) 
[![Coverage Status](https://coveralls.io/repos/github/hollodotme/fast-cgi-client/badge.svg?branch=1.x-stable)](https://coveralls.io/github/hollodotme/fast-cgi-client?branch=1.x-stable)

# Fast CGI Client

A PHP fast CGI client to send requests (a)synchronously to PHP-FPM using the [FastCGI Protocol](http://www.mit.edu/~yandros/doc/specs/fcgi-spec.html).

This library is based on the work of [Pierrick Charron](https://github.com/adoy)'s [PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client/) 
and was ported and modernized to PHP 7.0/PHP 7.1 and extended with unit tests.

You can find an experimental use-case in my related blog posts:
 
* [Experimental async PHP vol. 1](http://bit.ly/eapv1)
* [Experimental async PHP vol. 2](http://bit.ly/eapv2)

---

## Installation

### Use version 1.x for compatibility with PHP 7.0.x

```bash
composer require hollodotme/fast-cgi-client:^1.0
```

### Use version 2.x for compatibility with PHP 7.1.x

```bash
composer require hollodotme/fast-cgi-client:^2.0
```

---

## Usage

### Init client with a unix domain socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

require( 'vendor/autoload.php' );

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

$connection = new UnixDomainSocket(
	'unix:///var/run/php/php7.0-fpm.sock',  # Socket path to php-fpm
	5000,                                   # Connect timeout in milliseconds (default: 5000)
	5000,                                   # Read/write timeout in milliseconds (default: 5000)
	false,                                  # Make socket connection persistent (default: false)
	false                                   # Keep socket connection alive (default: false) 
);

$client = new Client( $connection );
```

### Init client with a network socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

require( 'vendor/autoload.php' );

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$connection = new NetworkSocket(
	'127.0.0.1',    # Hostname
	9000,           # Port
	5000,           # Connect timeout in milliseconds (default: 5000)
	5000,           # Read/write timeout in milliseconds (default: 5000)
	false,          # Make socket connection persistent (default: false)
	false           # Keep socket connection alive (default: false) 
);

$client = new Client( $connection );
```

### Send request synchronously

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

require( 'vendor/autoload.php' );

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

$client  = new Client( new UnixDomainSocket( 'unix:///var/run/php/php7.0-fpm.sock' ) );
$content = http_build_query( ['key' => 'value'] );

$request = new PostRequest('/path/to/target/script.php', $content);

$response = $client->sendRequest($request);

print_r( $response );
```

### Send request asynchronously

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

require( 'vendor/autoload.php' );

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client  = new Client( new NetworkSocket( '127.0.0.1', 9000 ) );
$content = http_build_query( ['key' => 'value'] );

$request = new PostRequest('/path/to/target/script.php', $content);

$requestId = $client->sendAsyncRequest($request);

echo "Request sent, got ID: {$requestId}";
```

### Optionally wait for a response, after sending the async request

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

require( 'vendor/autoload.php' );

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client  = new Client( new NetworkSocket( '127.0.0.1', 9000 ) );
$content = http_build_query( ['key' => 'value'] );

$request = new PostRequest('/path/to/target/script.php', $content);

$requestId = $client->sendAsyncRequest($request);

echo "Request sent, got ID: {$requestId}";

$response = $client->waitForResponse( 
	$requestId,     # The request ID 
	3000            # Optional timeout to wait for response,
					# defaults to read/write timeout in milliseconds set in connection
);
```

### Requests

Request are defined by the following interface:

```php
<?php declare(strict_types=1);

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
}
```

Alongside with this interface, this package provides ab abstract request class, containing default values to make the API more handy for you 
and 5 request method implementations of this abstract class:

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
| CUSTOM_VARS       | empty array                       | You can use the methods `setCustomVar`, `addCustomVars` to add own key-value pairs      |


### Responses

Assuming `/path/to/target/script.php` has the following content:
 
```php
<?php declare(strict_types=1);

echo "Hello World";
```

The response would look like this:

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

echo "Hello World";
```

The response would look like this:

```
X-Custom: Header
Content-type: text/html; charset=UTF-8

Hello World
```

---

## Command line tool (for debugging only)

Run a call through a network socket:

    bin/fcgiget localhost:9000/status

Run a call through a Unix Domain Socket

    bin/fcgiget unix:/var/run/php/php7.0-fpm.sock/status
