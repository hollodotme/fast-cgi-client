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
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use function chr;
use function explode;
use function fclose;
use function fwrite;
use function iterator_to_array;
use function str_repeat;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;

/**
 * The test acts as the FastCGI server: it accepts the connections of the client and writes the responses.
 */
final class ReadyResponsesTest extends TestCase
{
	private const STDOUT      = 6;

	private const END_REQUEST = 3;

	/** @var resource */
	private $server;

	/** @var string */
	private $host;

	/** @var int */
	private $port;

	protected function setUp() : void
	{
		$server = stream_socket_server( 'tcp://127.0.0.1:0' );

		if ( false === $server )
		{
			throw new RuntimeException( 'Could not start server.' );
		}

		$this->server = $server;
		[$this->host, $port] = explode( ':', (string)stream_socket_get_name( $server, false ) );
		$this->port = (int)$port;
	}

	protected function tearDown() : void
	{
		fclose( $this->server );
	}

	/**
	 * An idle socket becomes readable when the server closes its connection. It must not be reported as having a
	 * response, because its response was already read. Before, it was returned again and callbacks were notified twice.
	 *
	 * @throws Throwable
	 */
	public function testIdleSocketClosedByServerHasNoNewResponse() : void
	{
		$client = new Client();

		# First connection: the response is read, the socket becomes idle
		$idleSocketId   = $client->sendAsyncRequest( $this->getConnection( 1000 ), new GetRequest( 'script.php', '' ) );
		$idleConnection = $this->accept();
		$this->respond( $idleConnection, $idleSocketId, 'first' );

		self::assertSame( 'first', $client->readResponse( $idleSocketId )->getBody() );

		# Second connection: waits for its response
		$request = new GetRequest( 'script.php', '' );
		$bodies  = [];
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$bodies ) : void
			{
				$bodies[] = $response->getBody();
			}
		);

		$busySocketId   = $client->sendAsyncRequest( $this->getConnection( 2000 ), $request );
		$busyConnection = $this->accept();

		fclose( $idleConnection );

		self::assertSame( [], $client->getSocketIdsHavingResponse() );
		self::assertSame( [], iterator_to_array( $client->readReadyResponses() ) );

		$this->respond( $busyConnection, $busySocketId, 'second' );

		self::assertSame( [$busySocketId], $client->getSocketIdsHavingResponse() );

		$client->handleReadyResponses();

		self::assertSame( ['second'], $bodies );

		fclose( $busyConnection );
	}

	/**
	 * Before, stream_select() was called without streams to watch, which throws a ValueError on PHP 8.
	 *
	 * @throws Throwable
	 */
	public function testNoSocketHasResponseIfNoSocketIsBusy() : void
	{
		$client = new Client();

		$socketId   = $client->sendAsyncRequest( $this->getConnection( 1000 ), new GetRequest( 'script.php', '' ) );
		$connection = $this->accept();
		$this->respond( $connection, $socketId, 'first' );

		self::assertSame( 'first', $client->readResponse( $socketId )->getBody() );
		self::assertFalse( $client->hasUnhandledResponses() );

		fclose( $connection );

		self::assertSame( [], $client->getSocketIdsHavingResponse() );
		self::assertSame( [], iterator_to_array( $client->readReadyResponses() ) );

		$client->handleReadyResponses();
	}

	/**
	 * The response of an idle socket was already read. It stays available, also after the server closed the
	 * connection, so waiting for it notifies the response callbacks once, without waiting for a timeout.
	 *
	 * @throws Throwable
	 */
	public function testIdleSocketHasItsReadResponse() : void
	{
		$client  = new Client();
		$request = new GetRequest( 'script.php', '' );
		$bodies  = [];
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$bodies ) : void
			{
				$bodies[] = $response->getBody();
			}
		);
		$request->addFailureCallbacks(
			static function ( Throwable $throwable ) : void
			{
				self::fail( 'Unexpected failure: ' . $throwable->getMessage() );
			}
		);

		$socketId   = $client->sendAsyncRequest( $this->getConnection( 1000 ), $request );
		$connection = $this->accept();
		$this->respond( $connection, $socketId, 'first' );

		self::assertSame( 'first', $client->readResponse( $socketId )->getBody() );
		self::assertTrue( $client->hasResponse( $socketId ) );

		fclose( $connection );

		self::assertTrue( $client->hasResponse( $socketId ) );

		$client->waitForResponse( $socketId, 500 );

		self::assertSame( ['first'], $bodies );
		self::assertFalse( $client->hasUnhandledResponses() );
	}

	/**
	 * Connections with different read/write timeouts are not equal, so each request gets its own socket.
	 *
	 * @param int $readWriteTimeout
	 *
	 * @return NetworkSocket
	 */
	private function getConnection( int $readWriteTimeout ) : NetworkSocket
	{
		return new NetworkSocket( $this->host, $this->port, Defaults::CONNECT_TIMEOUT, $readWriteTimeout );
	}

	/**
	 * @return resource
	 */
	private function accept()
	{
		$connection = stream_socket_accept( $this->server, 1 );

		if ( false === $connection )
		{
			throw new RuntimeException( 'Client did not connect.' );
		}

		return $connection;
	}

	/**
	 * @param resource $connection
	 * @param int      $requestId
	 * @param string   $body
	 */
	private function respond( $connection, int $requestId, string $body ) : void
	{
		$encoder = new PacketEncoder();

		fwrite(
			$connection,
			$encoder->encodePacket( self::STDOUT, "Content-Type: text/plain\r\n\r\n" . $body, $requestId )
			. $encoder->encodePacket( self::END_REQUEST, str_repeat( chr( 0 ), 8 ), $requestId )
		);
	}
}
