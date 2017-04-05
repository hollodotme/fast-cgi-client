<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016 Holger Woltersdorf
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

$connection = new UnixDomainSocket( 'unix:///var/run/php/php7.1-fpm.sock', 5000, 5000 );
$client     = new Client( $connection );

$workerPath = '/vagrant/tests/Integration/Workers/sleepWorker.php';
$request    = new PostRequest( $workerPath, '' );
$request->addResponseCallbacks(
	function ( ProvidesResponseData $response )
	{
		echo $response->getRequestId() . "\n" . $response->getBody() . "\n" . $response->getDuration() . "\n\n";
		flush();
	}
);
$request->addFailureCallbacks(
	function ( \Throwable $throwable )
	{
		echo "!FAILURE! : {$throwable->getMessage()} (" . get_class( $throwable ) . ")\n\n";
		flush();
	}
);

$request->setContent( http_build_query( [ 'sleep' => random_int( 1, 3 ), 'test-key' => 0 ] ) );
$response = $client->sendRequest( $request );

echo '<pre>', htmlspecialchars( print_r( $response, true ) ), '</pre>';

for ( $i = 0; $i < (int)$argv[1]; $i++ )
{
	$request->setContent( http_build_query( [ 'test-key' => $i, 'sleep' => random_int( 1, 3 ) ] ) );

	$requestId = $client->sendAsyncRequest( $request );

	echo "\nSent request {$requestId}";
	flush();
}

echo "\n\nWaiting for responses...\n\n";

$client->waitForResponses();

die( 'done' );
