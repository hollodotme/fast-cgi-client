---
title: Single requests
sidebar_position: 2
---

The following examples assume that the content of `/path/to/target/script.php` looks like this:

```php
<?php declare(strict_types=1);

sleep((int)($_REQUEST['sleep'] ?? 0));
echo $_REQUEST['key'] ?? '';
```

## Send request synchronously

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = new UrlEncodedFormData(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

$response = $client->sendRequest($connection, $request);

echo $response->getBody();
```

```
# prints
value
```

## Send request asynchronously (Fire and forget)

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = new UrlEncodedFormData(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

$socketId = $client->sendAsyncRequest($connection, $request);

echo "Request sent, got ID: {$socketId}";
```

## Read the response, after sending the async request

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = new UrlEncodedFormData(['key' => 'value']);
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

## Notify a callback when async request responded

You can register response and failure callbacks for each request. In order to notify the callbacks when a response was
received instead of returning it, you need to use the `waitForResponse(int $socketId, ?int $timeoutMs = null)` method.

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use Throwable;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = new UrlEncodedFormData(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

# Register a response callback, expects a `ProvidesResponseData` instance as the only parameter
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
# If no response was received within the timeout, all registered failure callbacks
# will be notified with a TimedoutException
$client->waitForResponse( 
	$socketId,     # The socket ID 
	3000            # Optional timeout to wait for response,
					# defaults to read/write timeout in milliseconds set in connection
);

# ... is the same as, apart from the timeout while waiting for the response

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

## Retry sending a request

The client keeps its sockets open and reuses them for subsequent requests to the same connection.
Sockets that were closed in the meantime, e.g. because their php-fpm child process was terminated, are detected
and replaced by new ones before a request is sent.

If writing a request to a socket still fails, `sendRequest()` and `sendAsyncRequest()` throw a `WriteFailedException`.
If you want the client to send the request again on another socket instead, use the following methods:

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket('127.0.0.1', 9000);
$content    = new UrlEncodedFormData(['key' => 'value']);
$request    = new PostRequest('/path/to/target/script.php', $content);

# Same as sendRequest(), but tries to send the request up to 5 times (default)
$response = $client->tryRequest($connection, $request);

# Same as sendAsyncRequest(), but tries to send the request up to 3 times
$socketId = $client->tryAsyncRequest($connection, $request, 3);
```

Please note:

* Each try uses another socket, because a socket is discarded when writing to it failed.
* If the last try fails too, its `WriteFailedException` is thrown.
* The maximum number of tries must be at least 1, otherwise an `InvalidArgumentException` is thrown.
* Only sending the request is retried. If reading the response fails, the request is not sent again,
  because it may already have been processed by the target script.

---
