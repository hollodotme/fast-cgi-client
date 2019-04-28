<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Bin;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use function error_reporting;
use function ini_set;

error_reporting( E_ALL );
ini_set( 'display_errors', 'On' );

require __DIR__ . '/../vendor/autoload.php';

$connection = new UnixDomainSocket( '/var/run/php-uds.sock' );
$client     = new Client( $connection );

$workerPath = __DIR__ . '/exampleWorker.php';

$request = new PostRequest( $workerPath, 'test=persistant' );

for ( $i = 1; $i < 10; $i++ )
{
	echo "{$i}. Request\n";
	$response = $client->sendRequest( $request );
	printf( "Socket-ID: %s\n%s\n\n", $response->getRequestId(), $response->getOutput() );
}