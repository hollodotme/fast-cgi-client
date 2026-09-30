<?php declare(strict_types=1);

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
use Throwable;
use function chr;
use function count;
use function explode;
use function fclose;
use function fwrite;
use function iterator_to_array;
use function microtime;
use function str_repeat;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use const PHP_VERSION_ID;

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
	private array $connections = [];

	private Client $client;

	/** @var array<int, string> */
	private array $bodies = [];

	/** @var array<int, Throwable> */
	private array $failures = [];

	protected function setUp() : void
	{
		$server = stream_socket_server( 'tcp://127.0.0.1:0' );

		if ( false === $server )
		{
			self::fail( 'Could not start server.' );
		}

		$this->server = $server;
		$this->client = new Client();
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
	 * Before, a response that could not be read was skipped silently.
	 *
	 * @throws Throwable
	 */
	public function testReadingResponsesThrowsExceptionIfResponseTimesOut() : void
	{
		$socketId = $this->sendRequest( 5000 );

		$this->expectException( TimedoutException::class );
		$this->expectExceptionMessage( 'Read timed out' );

		iterator_to_array( $this->client->readResponses( 100, $socketId ) );
	}

	/**
	 * @throws Throwable
	 */
	public function testReadingResponsesSkipsUnknownSocketIds() : void
	{
		$socketId = $this->sendRequest( 5000 );
		$this->respond( 0, $socketId, 'unit' );

		$bodies = [];

		foreach ( $this->client->readResponses( null, 12345, $socketId ) as $response )
		{
			$bodies[] = $response->getBody();
		}

		self::assertSame( ['unit'], $bodies );
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
				new GetRequest( 'script.php' )
			);

			self::fail( 'Expected ConnectException to be thrown.' );
		}
		catch ( ConnectException $e )
		{
			$property = new ReflectionProperty( $this->client, 'sockets' );
			if ( PHP_VERSION_ID < 80100 )
			{
				$property->setAccessible( true );
			}

			/** @var SocketCollection $sockets */
			$sockets = $property->getValue( $this->client );

			self::assertCount( 0, $sockets );
		}
	}

	/**
	 * Sends a request with callbacks on a new connection and accepts the connection on the server side.
	 *
	 * @throws Throwable
	 */
	private function sendRequest( int $readWriteTimeout ) : int
	{
		[$host, $port] = explode( ':', (string)stream_socket_get_name( $this->server, false ) );

		$request = new GetRequest( 'script.php' );
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
				$readWriteTimeout + count( $this->connections ),
				50
			),
			$request
		);

		$connection = stream_socket_accept( $this->server, 1 );

		if ( false === $connection )
		{
			self::fail( 'Client did not connect.' );
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
		self::assertGreaterThanOrEqual( $timeoutSeconds - 0.01, $duration );
		self::assertLessThan( $timeoutSeconds + 1.0, $duration );
	}
}
