<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2019 Holger Woltersdorf & Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace hollodotme\FastCGI;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;

require __DIR__ . '/../vendor/autoload.php';

function printLine( string $text, string $color = 'default', bool $headline = false )
{
	$colors = [
		'default' => "\e[39m",
		'red'     => "\e[31m",
		'green'   => "\e[32m",
		'yellow'  => "\e[33m",
		'blue'    => "\e[34m",
		'gray'    => "\e[94m",
	];

	$headlineStr = ($headline === true)
		? sprintf( "%s%s%s\n", $colors[ $color ], str_repeat( '=', strlen( $text ) ), $colors['default'] )
		: '';

	printf( "%s%s%s\n%s", $colors[ $color ], $text, $colors['default'], $headlineStr );
}

function printResponse( ProvidesResponseData $response )
{
	printLine( 'Response:', 'green', true );
	printLine( $response->getBody() );
	printLine( 'Duration: ' . $response->getDuration() );
}

$client     = new Client();
$connection = new UnixDomainSocket( '/var/run/php-uds.sock' );

$workerPath = __DIR__ . '/exampleWorker.php';

$request = new PostRequest( $workerPath, '' );

printLine( "\n" );
printLine( 'hollodotme/fast-cgi-client examples', 'blue', true );
printLine( 'Worker script: ' . $workerPath, 'blue' );
printLine( 'Socket: ' . $connection->getSocketAddress(), 'blue' );
printLine( "\n" );

sleep( 2 );

printLine( 'SINGLE REQUESTS', 'yellow', true );
printLine( "\n" );

sleep( 2 );

# Sync request

printLine( '# Sending one synchronous request... (worker sleeps 1 second)' );
printLine( 'CODE: $client->sendRequest( $request );', 'red' );
printLine( "\n" );

$request->setContent( http_build_query( ['sleep' => 1, 'key' => 'single synchronous request'] ) );

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$response = $client->sendRequest( $connection, $request );

printResponse( $response );
printLine( "\n" );

# Async request with read response

printLine( '# Sending one asynchronous request... (worker sleeps 1 second)' );
printLine( 'CODE: $client->sendAsyncRequest( $request );', 'red' );
printLine( "\n" );

$request->setContent( http_build_query( ['sleep' => 1, 'key' => 'single asynchronous request'] ) );

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$socketId = $client->sendAsyncRequest( $connection, $request );

printLine( "Sent request with ID: {$socketId}" );

printLine( "\n" );
printLine( '# Now reading response...' );
printLine( 'CODE: $response = $client->readResponse( $socketId );', 'red' );
printLine( "\n" );

/** @noinspection PhpUnhandledExceptionInspection */
$response = $client->readResponse( $socketId );

printResponse( $response );
printLine( "\n" );

# Async request with callback

sleep( 2 );

printLine( '# Adding a response callback to request' );
printLine( 'CODE: $request->addResponseCallbacks(', 'red' );
printLine( '        function( ProvidesResponseData $response )', 'red' );
printLine( '        {', 'red' );
printLine( '          printLine(\'Callback notified!\', \'green\');', 'red' );
printLine( '          printResponse($response);', 'red' );
printLine( '        }', 'red' );
printLine( '      );', 'red' );
printLine( "\n" );

$request->setContent( http_build_query( ['sleep' => 1, 'key' => 'single asynchronous request with callback'] ) );
$request->addResponseCallbacks(
	static function ( ProvidesResponseData $response )
	{
		printLine( 'Callback notified!', 'green' );
		printLine( "\n" );
		printResponse( $response );
	}
);

sleep( 2 );

printLine( '# Sending one asynchronous request... (worker sleeps 1 second)' );
printLine( 'CODE: $client->sendAsyncRequest( $request );', 'red' );
printLine( "\n" );

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$socketId = $client->sendAsyncRequest( $connection, $request );

printLine( "Sent request with ID: {$socketId}" );

printLine( "\n" );
printLine( '# Now waiting for response...' );
printLine( 'CODE: $client->waitForResponse( $socketId );', 'red' );
printLine( "\n" );

/** @noinspection PhpUnhandledExceptionInspection */
$client->waitForResponse( $socketId );

sleep( 2 );

# Async requests with read responses (order preserved)

printLine( "\n" );
printLine( 'MULTIPLE REQUEST', 'yellow', true );
printLine( "\n" );

sleep( 2 );

printLine( '# Sending 3 asynchronous requests... (each worker sleeps 1 second)' );
printLine( 'CODE: $client->sendAsyncRequest( $request1 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request2 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request3 );', 'red' );
printLine( "\n" );

$request1 = new PostRequest( $workerPath, http_build_query( ['sleep' => 1, 'key' => 'Request 1'] ) );
$request2 = new PostRequest( $workerPath, http_build_query( ['sleep' => 1, 'key' => 'Request 2'] ) );
$request3 = new PostRequest( $workerPath, http_build_query( ['sleep' => 1, 'key' => 'Request 3'] ) );

$socketIds = [];

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request1 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request2 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request3 );

printLine( 'Sent requests with IDs: ' . implode( ', ', $socketIds ) );

printLine( "\n" );
printLine( '# Now reading responses... (request order will be preserved)' );
printLine( 'CODE: foreach($client->readResponses(null, ...$socketIds) as $response)', 'red' );
printLine( '      {', 'red' );
printLine( '        printResponse($response);', 'red' );
printLine( '      }', 'red' );
printLine( "\n" );

foreach ( $client->readResponses( null, ...$socketIds ) as $response )
{
	printResponse( $response );
	printLine( "\n" );
}

printLine( "\n" );

# Async requests with read ready responses (reactive)

sleep( 2 );

printLine( '# Sending 3 asynchronous requests... (worker sleep 3, 2, 1 seconds)' );
printLine( 'CODE: $client->sendAsyncRequest( $request1 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request2 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request3 );', 'red' );
printLine( "\n" );

$request1 = new PostRequest( $workerPath, http_build_query( ['sleep' => 3, 'key' => 'Request 1'] ) );
$request2 = new PostRequest( $workerPath, http_build_query( ['sleep' => 2, 'key' => 'Request 2'] ) );
$request3 = new PostRequest( $workerPath, http_build_query( ['sleep' => 1, 'key' => 'Request 3'] ) );

$socketIds = [];

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request1 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request2 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request3 );

printLine( 'Sent requests with IDs: ' . implode( ', ', $socketIds ) );
printLine( "\n" );
printLine( '# Now reading ready responses... (reactive)' );
printLine( 'CODE: while($client->hasUnhandledResponses())', 'red' );
printLine( '      {', 'red' );
printLine( '        echo ".";', 'red' );
printLine( '        foreach ($client->readReadyResponses() as $response)', 'red' );
printLine( '        {', 'red' );
printLine( '          printResponse( $response );', 'red' );
printLine( '        }', 'red' );
printLine( '      }', 'red' );
printLine( "\n" );

while ( $client->hasUnhandledResponses() )
{
	echo '.';

	/** @noinspection PhpUnhandledExceptionInspection */
	foreach ( $client->readReadyResponses() as $response )
	{
		printLine( "\n" );
		printResponse( $response );
		printLine( "\n" );
	}
}

printLine( "\n" );

# Async requests with callbacks waiting for responses (reactive)

sleep( 2 );

printLine( '# Sending 3 asynchronous requests with callbacks... (worker sleep 2, 3, 1 seconds)' );
printLine( 'CODE: $client->sendAsyncRequest( $request1 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request2 );', 'red' );
printLine( '      $client->sendAsyncRequest( $request3 );', 'red' );
printLine( "\n" );

$responseCallback = static function ( ProvidesResponseData $response )
{
	printLine( "\n" );
	printLine( 'Callback notified!', 'green' );
	printLine( "\n" );
	printResponse( $response );
};

$request1 = new PostRequest( $workerPath, http_build_query( ['sleep' => 2, 'key' => 'Request 1'] ) );
$request2 = new PostRequest( $workerPath, http_build_query( ['sleep' => 3, 'key' => 'Request 2'] ) );
$request3 = new PostRequest( $workerPath, http_build_query( ['sleep' => 1, 'key' => 'Request 3'] ) );

$request1->addResponseCallbacks( $responseCallback );
$request2->addResponseCallbacks( $responseCallback );
$request3->addResponseCallbacks( $responseCallback );

$socketIds = [];

sleep( 2 );

/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request1 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request2 );
/** @noinspection PhpUnhandledExceptionInspection */
$socketIds[] = $client->sendAsyncRequest( $connection, $request3 );

printLine( 'Sent requests with IDs: ' . implode( ', ', $socketIds ) );

printLine( "\n" );
printLine( '# Now waiting for responses... (reactive)' );
printLine( 'CODE: $client->waitForResponses()', 'red' );
printLine( "\n" );

/** @noinspection PhpUnhandledExceptionInspection */
$client->waitForResponses();

printLine( "\n" );
printLine( "\n" );
printLine( 'Thanks for watching!', 'blue' );
printLine( 'Get it on https://github.com/hollodotme/fast-cgi-client', 'blue' );
printLine( "\n" );
