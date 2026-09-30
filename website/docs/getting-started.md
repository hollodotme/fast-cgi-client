---
title: Getting started
sidebar_position: 3
---

This page walks you through your first request. It assumes you [installed the library](./installation.md) and have
PHP-FPM listening on `127.0.0.1:9000`.

## 1. Create a script to execute

Create `/path/to/target/script.php`. PHP-FPM executes it for every request you send:

```php
<?php declare(strict_types=1);

echo 'Hello ' . ($_REQUEST['name'] ?? 'World');
```

:::note

PHP-FPM must be able to read the script, and the path must be the path *on the FPM server*.

:::

## 2. Connect and send a request

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

require __DIR__ . '/vendor/autoload.php';

$client     = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );
$request    = new PostRequest(
	'/path/to/target/script.php',
	new UrlEncodedFormData( ['name' => 'FastCGI'] )
);

$response = $client->sendRequest( $connection, $request );

echo $response->getBody();
```

```
# prints
Hello FastCGI
```

If PHP-FPM listens on a unix domain socket instead, use
`new UnixDomainSocket( '/var/run/php/php-fpm.sock' )` as connection.

## 3. Go further

* [Connections](./usage/connections.md) — network vs. unix domain sockets and timeouts
* [Single requests](./usage/single-requests.mdx) — sync, fire and forget, callbacks and retries
* [Multiple requests](./usage/multiple-requests.mdx) — send in parallel and react to responses as they arrive
* [Requests](./usage/requests.md) — request methods, custom variables and request contents
* [Responses](./usage/responses.md) — headers, body, errors and durations
* [API reference](./api-reference.md) — all classes and methods
