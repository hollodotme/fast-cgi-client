<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;
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

	private string $host;

	private int $port;

	protected function setUp() : void
	{
		$server = stream_socket_server( 'tcp://127.0.0.1:0' );

		if ( false === $server )
		{
			self::fail( 'Could not start server.' );
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
		$idleSocketId   = $client->sendAsyncRequest( $this->getConnection( 1000 ), new GetRequest( 'script.php' ) );
		$idleConnection = $this->accept();
		$this->respond( $idleConnection, $idleSocketId, 'first' );

		self::assertSame( 'first', $client->readResponse( $idleSocketId )->getBody() );

		# Second connection: waits for its response
		$request = new GetRequest( 'script.php' );
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
	 * Connections with different read/write timeouts are not equal, so each request gets its own socket.
	 */
	private function getConnection( int $readWriteTimeout ) : NetworkSocket
	{
		return new NetworkSocket( $this->host, $this->port, Defaults::CONNECT_TIMEOUT, $readWriteTimeout, 50 );
	}

	/**
	 * @return resource
	 */
	private function accept()
	{
		$connection = stream_socket_accept( $this->server, 1 );

		if ( false === $connection )
		{
			self::fail( 'Client did not connect.' );
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
