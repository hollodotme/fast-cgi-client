---
title: Requests
sidebar_position: 4
---

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

	public function getContent() : ?ComposesRequestContent;

	public function getCustomVars() : array;

	public function getParams() : array;

	public function getRequestUri() : string;

	public function getResponseCallbacks() : array;

	public function getFailureCallbacks() : array;

	public function getPassThroughCallbacks() : array;
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

### Default values

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
| CONTENT_TYPE      | application/x-www-form-urlencoded | Is set to the content type of the request content, if the request has one               |
| REQUEST_URI       | <empty string>                    |                                                                                         |
| CUSTOM_VARS       | empty array                       | You can use the methods `setCustomVar`, `addCustomVars` to add own key-value pairs      |

Each of these values has a setter in the abstract request class: `setServerSoftware()`, `setRemoteAddress()`,
`setRemotePort()`, `setServerAddress()`, `setServerPort()`, `setServerName()`, `setServerProtocol()`,
`setContentType()` and `setRequestUri()`. Custom variables can be removed again with `resetCustomVars()`.

All parameters of a request are sent in FastCGI records of at most 65535 bytes. If they are longer in total, they are
split into multiple records between two parameters, so php-fpm can decode them. A single parameter (name and value)
that is longer than 65535 bytes is split as well, which the FastCGI specification allows, but php-fpm does not accept
such a parameter and closes the connection. The client then throws a `ReadFailedException`.

**Please note:** `setContent()` overwrites the content type of the request. If you need a content type that differs
from the one of the request content, call `setContentType()` after the content was set.

### Query parameters

If the target script expects query parameters (`$_GET`), you can pass them as an array to any request.
There is no need to compose a query string or to set the `QUERY_STRING` variable on your own.

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );

$request = new GetRequest( '/path/to/target/script.php' );
$request->setRequestUri( '/some/path' );
$request->setQueryParams(
	[
		'key'  => 'value',
		'list' => ['one', 'two'],
	]
);

$response = $client->sendRequest( $connection, $request );
```

This example produces the following values at the target script:

```
# $_GET
Array
(
    [key] => value
    [list] => Array
        (
            [0] => one
            [1] => two
        )

)

# $_SERVER['REQUEST_URI']
/some/path?key=value&list%5B0%5D=one&list%5B1%5D=two

# $_SERVER['QUERY_STRING']
key=value&list%5B0%5D=one&list%5B1%5D=two
```

Please note:

* The query parameters are encoded according to [RFC 3986](https://www.rfc-editor.org/rfc/rfc3986).
* If the request URI already contains a query string, the query parameters are appended to it.
* Query parameters take precedence over a `QUERY_STRING` that was set as a custom variable.
* If you don't set query parameters, no `QUERY_STRING` is added and the request URI is sent as it is.

### Request contents

In order to make the composition of different request content types easier there are classes covering the typical
content types:

* [UrlEncodedFormData](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/src/RequestContents/UrlEncodedFormData.php)
* [MultipartFormData](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/src/RequestContents/MultipartFormData.php)
* [JsonData](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/src/RequestContents/JsonData.php)
* [PlainText](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/src/RequestContents/PlainText.php)

The content of a request is optional. You can pass it as the second argument to the constructor of a request
or set it later with `setContent()`. Both ways also set the content type and content length of the request:

```php
$request = new PostRequest( '/path/to/target/script.php', new JsonData( ['key' => 'value'] ) );

# ... is the same as

$request = new PostRequest( '/path/to/target/script.php' );
$request->setContent( new JsonData( ['key' => 'value'] ) );
```

The content length and the content type are determined from the content object when the request is sent.
So you can still change the content object after it was passed to the request, e.g. add files to a
`MultipartFormData` object. A content type that was set with `setContentType()` takes precedence over the content
type of the content object.

You can create your own request content type composer by implementing the following interface:

[**ComposesRequestContent**](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/src/Interfaces/ComposesRequestContent.php)

```php
interface ComposesRequestContent
{
	public function getContentType() : string;

	public function getContent() : string;
}
```

#### Request content example: URL encoded form data (application/x-www-form-urlencoded)

```php
<?php declare(strict_types=1);

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;

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

$postRequest = new PostRequest( '/path/to/target/script.php', $urlEncodedContent );

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

#### Request content example: multipart form data (multipart/form-data)

Multipart form-data can be used to transfer any binary data as files to the target script just like a file upload in a
browser does.

**PLEASE NOTE:** Multipart form-data content type works with POST requests only.

The MIME type of each file is detected using the [fileinfo extension](https://www.php.net/manual/en/book.fileinfo.php),
which is required by this library. Files whose type cannot be determined are sent as `application/octet-stream`.

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

$postRequest = new PostRequest( '/path/to/target/script.php', $multipartContent );

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

#### Request content example: JSON encoded data (application/json)

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

$postRequest = new PostRequest( '/path/to/target/script.php', $jsonContent );

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
