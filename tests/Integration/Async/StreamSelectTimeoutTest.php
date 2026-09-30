<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\Async;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use function dirname;
use function microtime;

final class StreamSelectTimeoutTest extends TestCase
{
	use SocketDataProviding;

	# Tolerance in seconds for the precision of measuring the waiting time
	private const TOLERANCE = 0.01;

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCheckingForResponseWaitsForStreamSelectTimeoutOfConnection() : void
	{
		$client   = new Client();
		$socketId = $client->sendAsyncRequest(
			$this->getNetworkSocketConnection( 300 ),
			$this->getSleepRequest( 1 )
		);

		$start       = microtime( true );
		$hasResponse = $client->hasResponse( $socketId );
		$duration    = microtime( true ) - $start;

		self::assertFalse( $hasResponse );
		self::assertGreaterThanOrEqual( 0.3 - self::TOLERANCE, $duration );
		self::assertLessThan( 1.0, $duration );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCheckingForResponseReturnsAsSoonAsResponseIsAvailable() : void
	{
		$client   = new Client();
		$socketId = $client->sendAsyncRequest(
			$this->getNetworkSocketConnection( 3000 ),
			$this->getSleepRequest( 0 )
		);

		$start       = microtime( true );
		$hasResponse = $client->hasResponse( $socketId );
		$duration    = microtime( true ) - $start;

		self::assertTrue( $hasResponse );
		self::assertLessThan( 3.0, $duration );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCheckingMultipleSocketsForResponsesWaitsForSmallestStreamSelectTimeout() : void
	{
		$client  = new Client();
		$request = $this->getSleepRequest( 1 );

		$client->sendAsyncRequest( $this->getNetworkSocketConnection( 900 ), $request );
		$client->sendAsyncRequest( $this->getUnixDomainSocketConnection( 300 ), $request );

		$start     = microtime( true );
		$socketIds = $client->getSocketIdsHavingResponse();
		$duration  = microtime( true ) - $start;

		self::assertSame( [], $socketIds );
		self::assertGreaterThanOrEqual( 0.3 - self::TOLERANCE, $duration );
		self::assertLessThan( 0.9, $duration );
	}

	private function getSleepRequest( int $seconds ) : PostRequest
	{
		return new PostRequest(
			dirname( __DIR__ ) . '/Workers/sleepWorker.php',
			new UrlEncodedFormData( ['test-key' => 'unit', 'sleep' => $seconds] )
		);
	}

	private function getNetworkSocketConnection( int $streamSelectTimeout ) : NetworkSocket
	{
		return new NetworkSocket(
			$this->getNetworkSocketHost(),
			$this->getNetworkSocketPort(),
			Defaults::CONNECT_TIMEOUT,
			Defaults::READ_WRITE_TIMEOUT,
			$streamSelectTimeout
		);
	}

	private function getUnixDomainSocketConnection( int $streamSelectTimeout ) : UnixDomainSocket
	{
		return new UnixDomainSocket(
			$this->getUnixDomainSocket(),
			Defaults::CONNECT_TIMEOUT,
			Defaults::READ_WRITE_TIMEOUT,
			$streamSelectTimeout
		);
	}
}
