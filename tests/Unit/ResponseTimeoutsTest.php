<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2020 Holger Woltersdorf & Contributors
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

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use function chr;
use function count;
use function explode;
use function fclose;
use function fwrite;
use function microtime;
use function str_repeat;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;

/**
 * The test acts as the FastCGI server: it accepts the connections of the client and decides whether it answers.
 */
final class ResponseTimeoutsTest extends TestCase
{
	use SocketDataProviding;

	private const STDOUT      = 6;

	private const END_REQUEST = 3;

	/** @var resource */
	private $server;

	/** @var array<int, resource> */
	private $connections = [];

	/** @var Client */
	private $client;

	/** @var array<int, string> */
	private $bodies = [];

	/** @var array<int, Throwable> */
	private $failures = [];

	protected function setUp() : void
	{
		$server = stream_socket_server( 'tcp://127.0.0.1:0' );

		if ( false === $server )
		{
			throw new RuntimeException( 'Could not start server.' );
		}

		$this->server      = $server;
		$this->client      = new Client();
		$this->connections = [];
		$this->bodies      = [];
		$this->failures    = [];
	}

	protected function tearDown() : void
	{
		foreach ( $this->connections as $connection )
		{
			fclose( $connection );
		}

		fclose( $this->server );
	}

	/**
	 * Before, waiting for a response that never came did not end.
	 *
	 * @throws Throwable
	 */
	public function testWaitingForResponseNotifiesFailureCallbacksIfResponseTimesOut() : void
	{
		$socketId = $this->sendRequest( 5000 );
		$start    = microtime( true );

		$this->client->waitForResponse( $socketId, 200 );

		$this->assertTimedOut( $start, 0.2 );
		self::assertSame( [], $this->bodies );
		self::assertFalse( $this->client->hasUnhandledResponses() );
	}

	/**
	 * @throws Throwable
	 */
	public function testWaitingForResponseUsesReadWriteTimeoutOfConnectionByDefault() : void
	{
		$socketId = $this->sendRequest( 300 );
		$start    = microtime( true );

		$this->client->waitForResponse( $socketId );

		$this->assertTimedOut( $start, 0.3 );
	}

	/**
	 * @throws Throwable
	 */
	public function testWaitingForResponsesNotifiesFailureCallbacksOfResponsesThatTimeOut() : void
	{
		$this->sendRequest( 5000 );
		$answeredSocketId = $this->sendRequest( 5000 );
		$this->respond( 1, $answeredSocketId, 'answered' );

		$start = microtime( true );

		$this->client->waitForResponses( 300 );

		$this->assertTimedOut( $start, 0.3 );
		self::assertSame( ['answered'], $this->bodies );
		self::assertFalse( $this->client->hasUnhandledResponses() );
	}

	/**
	 * @throws Throwable
	 */
	public function testWaitingForResponsesUsesReadWriteTimeoutOfConnectionsByDefault() : void
	{
		$this->sendRequest( 300 );

		$start = microtime( true );

		$this->client->waitForResponses();

		$this->assertTimedOut( $start, 0.3 );
		self::assertFalse( $this->client->hasUnhandledResponses() );
	}

	/**
	 * Before, the socket that could not connect stayed in the collection of the client.
	 *
	 * @throws Throwable
	 */
	public function testSocketIsRemovedIfItCannotConnect() : void
	{
		try
		{
			$this->client->sendAsyncRequest(
				new UnixDomainSocket( $this->getNonExistingUnixDomainSocket() ),
				new GetRequest( 'script.php', '' )
			);

			self::fail( 'Expected ConnectException to be thrown.' );
		}
		catch ( ConnectException $e )
		{
			$property = new ReflectionProperty( $this->client, 'sockets' );
			$property->setAccessible( true );

			/** @var SocketCollection $sockets */
			$sockets = $property->getValue( $this->client );

			self::assertCount( 0, $sockets );
		}
	}

	/**
	 * Sends a request with callbacks on a new connection and accepts the connection on the server side.
	 *
	 * @param int $readWriteTimeout
	 *
	 * @return int
	 * @throws Throwable
	 */
	private function sendRequest( int $readWriteTimeout ) : int
	{
		[$host, $port] = explode( ':', (string)stream_socket_get_name( $this->server, false ) );

		$request = new GetRequest( 'script.php', '' );
		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response ) : void
			{
				$this->bodies[] = $response->getBody();
			}
		);
		$request->addFailureCallbacks(
			function ( Throwable $e ) : void
			{
				$this->failures[] = $e;
			}
		);

		# Connections with different timeouts are not equal, so each request gets its own socket
		$socketId = $this->client->sendAsyncRequest(
			new NetworkSocket(
				$host,
				(int)$port,
				Defaults::CONNECT_TIMEOUT,
				$readWriteTimeout + count( $this->connections )
			),
			$request
		);

		$connection = stream_socket_accept( $this->server, 1 );

		if ( false === $connection )
		{
			throw new RuntimeException( 'Client did not connect.' );
		}

		$this->connections[] = $connection;

		return $socketId;
	}

	private function respond( int $connectionIndex, int $requestId, string $body ) : void
	{
		$encoder = new PacketEncoder();

		fwrite(
			$this->connections[ $connectionIndex ],
			$encoder->encodePacket( self::STDOUT, "Content-Type: text/plain\r\n\r\n" . $body, $requestId )
			. $encoder->encodePacket( self::END_REQUEST, str_repeat( chr( 0 ), 8 ), $requestId )
		);
	}

	private function assertTimedOut( float $start, float $timeoutSeconds ) : void
	{
		$duration = microtime( true ) - $start;

		self::assertCount( 1, $this->failures );
		self::assertInstanceOf( TimedoutException::class, $this->failures[0] );
		self::assertSame( 'Read timed out', $this->failures[0]->getMessage() );
		self::assertGreaterThanOrEqual( $timeoutSeconds - 0.01, $duration );
		self::assertLessThan( $timeoutSeconds + 1.0, $duration );
	}
}
