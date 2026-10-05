<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use Exception;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\RequestContents\PlainText;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketId;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;
use function chr;
use function fclose;
use function fwrite;
use function is_resource;
use function microtime;
use function str_repeat;
use function stream_socket_pair;
use const PHP_VERSION_ID;
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

		$socket->sendRequest( new PostRequest( '/path/to/script.php' ) );
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

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessage( 'Trying to send a request to a socket that is not usable anymore.' );

		$socket->sendRequest( new PostRequest( '/path/to/script.php' ) );
	}

	/**
	 * @throws Exception
	 */
	private function getSocketWithPeer( int $readWriteTimeout ) : Socket
	{
		$pair = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0 );

		if ( false === $pair )
		{
			self::fail( 'Could not create socket pair.' );
		}

		$socket = new Socket(
			SocketId::new(),
			new UnixDomainSocket( '/not/used.sock', Defaults::CONNECT_TIMEOUT, $readWriteTimeout ),
			new PacketEncoder(),
			new NameValuePairEncoder()
		);

		$property = new ReflectionProperty( $socket, 'resource' );
		if ( PHP_VERSION_ID < 80100 )
		{
			$property->setAccessible( true );
		}
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
			self::fail( 'Peer is not open.' );
		}

		return $this->peer;
	}

	/**
	 * The peer never reads, so a request that is larger than the buffers of the socket pair cannot be written.
	 */
	private function getLargeRequest() : PostRequest
	{
		return new PostRequest( '/path/to/script.php', new PlainText( str_repeat( 'x', 8 * 1024 * 1024 ) ) );
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
