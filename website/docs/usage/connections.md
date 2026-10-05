---
title: Connections
sidebar_position: 1
---

This library supports two types of connecting to a FastCGI server:

1. Via network socket
2. Via unix domain socket

## Create a network socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$connection = new NetworkSocket(
	'127.0.0.1',    # Hostname
	9000,           # Port
	5000,           # Connect timeout in milliseconds (default: 5000)
	5000,           # Read/write timeout in milliseconds (default: 5000)
	200             # Stream select timeout in milliseconds (default: 200)
);
```

## Create a unix domain socket connection

```php
<?php declare(strict_types=1);

namespace YourVendor\YourProject;

use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

$connection = new UnixDomainSocket(
	'/var/run/php/php8.3-fpm.sock',     # Socket path
	5000,                               # Connect timeout in milliseconds (default: 5000)
	5000,                               # Read/write timeout in milliseconds (default: 5000)
	200                                 # Stream select timeout in milliseconds (default: 200)
);
```

## Stream select timeout

The stream select timeout defines how long the client waits for a response to become available, when you check for
responses with one of the following methods:

* `Client#hasResponse()`
* `Client#getSocketIdsHavingResponse()`
* `Client#readReadyResponses()`
* `Client#handleReadyResponses()`
* `Client#waitForResponse()`
* `Client#waitForResponses()`

The check returns as soon as a response is available, so a higher value does not delay the handling of responses.
It only reduces the number of iterations (and CPU usage) of a loop like this:

```php
while ( !$client->hasResponse( $socketId ) )
{
	# Do something else here in the meanwhile
}

$client->handleResponse( $socketId );
```

If you check multiple sockets at once and their connections have different stream select timeouts, the client waits
for the smallest timeout of all sockets that are waiting for a response.

A timeout of `0` makes the check return immediately.
