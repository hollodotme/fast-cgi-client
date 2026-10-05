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

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use Exception;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketId;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use function chr;
use function fclose;
use function fwrite;
use function is_resource;
use function microtime;
use function str_repeat;
use function stream_socket_pair;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

/**
 * The socket writes to one end of a socket pair. The test holds the other end and decides whether it reads.
 */
final class SocketWriteTest extends TestCase
{
	private const STDOUT      = 6;

	private const END_REQUEST = 3;

	/** @var resource|null */
	private $peer;

	protected function tearDown() : void
	{
		if ( is_resource( $this->peer ) )
		{
			fclose( $this->peer );
		}
	}

	/**
	 * Before, a partially written request was treated as sent, and the client waited for a response that never came.
	 *
	 * @throws Throwable
	 */
	public function testIncompleteWriteThrowsException() : void
	{
		$socket = $this->getSocketWithPeer( 200 );

		$start = microtime( true );

		try
		{
			$socket->sendRequest( $this->getLargeRequest() );

			self::fail( 'Expected TimedoutException to be thrown.' );
		}
		catch ( TimedoutException $e )
		{
			self::assertSame( 'Write timed out', $e->getMessage() );
			# One timeout, not two
			self::assertGreaterThanOrEqual( 0.19, microtime( true ) - $start );
			self::assertLessThan( 0.35, microtime( true ) - $start );
		}
	}

	/**
	 * Before, a read timeout passed to fetchResponse() also applied to writing the next request on the socket.
	 *
	 * @throws Throwable
	 */
	public function testReadTimeoutOfPreviousResponseDoesNotApplyToNextRequest() : void
	{
		$socket = $this->getSocketWithPeer( 400 );

		$socket->sendRequest( new PostRequest( '/path/to/script.php', '' ) );
		$this->respond( $socket->getId() );

		self::assertSame( 'unit', $socket->fetchResponse( 50 )->getBody() );

		$start = microtime( true );

		try
		{
			$socket->sendRequest( $this->getLargeRequest() );

			self::fail( 'Expected TimedoutException to be thrown.' );
		}
		catch ( TimedoutException $e )
		{
			# The read/write timeout of the connection applies once, not the 50 ms of the previous read
			self::assertGreaterThanOrEqual( 0.39, microtime( true ) - $start );
			self::assertLessThan( 0.7, microtime( true ) - $start );
		}
	}

	/**
	 * @throws Throwable
	 */
	public function testSendingRequestToSocketClosedByPeerThrowsException() : void
	{
		$socket = $this->getSocketWithPeer( Defaults::READ_WRITE_TIMEOUT );

		fclose( $this->getPeer() );

		$this->expectException( WriteFailedException::class );
		$this->expectExceptionMessage( 'Failed to write request to socket [broken pipe]' );

		$socket->sendRequest( new PostRequest( '/path/to/script.php', '' ) );
	}

	/**
	 * @param int $readWriteTimeout
	 *
	 * @return Socket
	 * @throws Exception
	 */
	private function getSocketWithPeer( int $readWriteTimeout ) : Socket
	{
		$pair = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0 );

		if ( false === $pair )
		{
			throw new RuntimeException( 'Could not create socket pair.' );
		}

		$socket = new Socket(
			SocketId::new(),
			new UnixDomainSocket( '/not/used.sock', Defaults::CONNECT_TIMEOUT, $readWriteTimeout ),
			new PacketEncoder(),
			new NameValuePairEncoder()
		);

		$property = new ReflectionProperty( $socket, 'resource' );
		$property->setAccessible( true );
		$property->setValue( $socket, $pair[0] );

		$this->peer = $pair[1];

		return $socket;
	}

	/**
	 * @return resource
	 */
	private function getPeer()
	{
		if ( !is_resource( $this->peer ) )
		{
			throw new RuntimeException( 'Peer is not open.' );
		}

		return $this->peer;
	}

	/**
	 * The peer never reads, so a request that is larger than the buffers of the socket pair cannot be written.
	 */
	private function getLargeRequest() : PostRequest
	{
		return new PostRequest( '/path/to/script.php', str_repeat( 'x', 8 * 1024 * 1024 ) );
	}

	private function respond( int $requestId ) : void
	{
		$encoder = new PacketEncoder();

		fwrite(
			$this->getPeer(),
			$encoder->encodePacket( self::STDOUT, "Content-Type: text/plain\r\n\r\nunit", $requestId )
			. $encoder->encodePacket( self::END_REQUEST, str_repeat( chr( 0 ), 8 ), $requestId )
		);
	}
}
