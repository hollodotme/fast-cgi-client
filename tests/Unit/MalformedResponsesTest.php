<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;
use Throwable;
use function chr;
use function explode;
use function fclose;
use function fwrite;
use function is_resource;
use function microtime;
use function str_repeat;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function strlen;
use function substr;

/**
 * Tests how the client deals with responses that are not valid FastCGI responses.
 * The test acts as the server itself: it accepts the connection of the client and writes the response to it.
 */
final class MalformedResponsesTest extends TestCase
{
	private const END_REQUEST       = 3;

	private const PARAMS            = 4;

	private const STDOUT            = 6;

	private const STDERR            = 7;

	private const UNKNOWN_TYPE      = 11;

	private const READ_TIMEOUT      = 200;

	/** @var resource */
	private $server;

	/** @var resource|null */
	private $connectionToClient;

	private Client $client;

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
		if ( is_resource( $this->connectionToClient ) )
		{
			fclose( $this->connectionToClient );
		}

		fclose( $this->server );
	}

	/**
	 * @param callable(int) : string $response
	 * @param string                 $expectedMessage
	 *
	 * @throws Throwable
	 * @dataProvider invalidPacketProvider
	 */
	public function testInvalidPacketsAreRejected( callable $response, string $expectedMessage ) : void
	{
		$socketId = $this->sendRequestAndRespond( $response );

		try
		{
			$this->client->readResponse( $socketId );

			self::fail( 'Expected ReadFailedException to be thrown.' );
		}
		catch ( ReadFailedException $e )
		{
			self::assertMatchesRegularExpression( $expectedMessage, $e->getMessage() );
			self::assertFalse( $this->client->hasUnhandledResponses() );
		}
	}

	/**
	 * @return array<string, array<int, callable|string>>
	 */
	public function invalidPacketProvider() : array
	{
		return [
			'response of a HTTP server'                    => [
				static fn( int $id ) : string => "HTTP/1.1 400 Bad Request\r\nContent-Length: 11\r\n\r\nBad Request",
				'#^Not a FastCGI packet: unsupported protocol version 72$#',
			],
			'unsupported protocol version'                 => [
				fn( int $id ) : string => $this->header( self::STDOUT, $id, 0, 0, 2 ),
				'#^Not a FastCGI packet: unsupported protocol version 2$#',
			],
			'record type that does not exist'              => [
				fn( int $id ) : string => $this->record( 0, $id, 'unit' ),
				'#^Invalid FastCGI packet: unexpected record type 0$#',
			],
			'record type above the maximum type'           => [
				fn( int $id ) : string => $this->record( 12, $id, 'unit' ),
				'#^Invalid FastCGI packet: unexpected record type 12$#',
			],
			'record type only sent by web servers'         => [
				fn( int $id ) : string => $this->record( self::PARAMS, $id, 'unit' ),
				'#^Invalid FastCGI packet: unexpected record type 4$#',
			],
			'stdout record of another request'             => [
				fn( int $id ) : string => $this->record( self::STDOUT, $id + 1, 'unit' ),
				'#^Invalid FastCGI packet: expected request ID \d+, got \d+$#',
			],
			'end-request record of another request'        => [
				fn( int $id ) : string => $this->record( self::STDOUT, $id, 'unit' ) . $this->endRequest( $id + 1 ),
				'#^Invalid FastCGI packet: expected request ID \d+, got \d+$#',
			],
			'management record with a request ID'          => [
				fn( int $id ) : string => $this->record( self::UNKNOWN_TYPE, $id, str_repeat( chr( 0 ), 8 ) ),
				'#^Invalid FastCGI packet: management record with request ID \d+$#',
			],
			'end-request record with unexpected length'    => [
				fn( int $id ) : string => $this->record( self::END_REQUEST, $id, str_repeat( chr( 0 ), 4 ) ),
				'#^Invalid FastCGI packet: unexpected length of end-request record 4$#',
			],
		];
	}

	/**
	 * These cases ended up in an endless loop, because reading from a closed connection was repeated forever.
	 *
	 * @param callable(int) : string $response
	 *
	 * @throws Throwable
	 * @dataProvider incompleteResponseProvider
	 */
	public function testReadingFailsIfConnectionIsClosedBeforeResponseIsComplete( callable $response ) : void
	{
		$socketId = $this->sendRequestAndRespond( $response );

		$this->expectException( ReadFailedException::class );

		/** @noinspection UnusedFunctionResultInspection */
		$this->client->readResponse( $socketId );
	}

	/**
	 * @return array<string, array<int, callable>>
	 */
	public function incompleteResponseProvider() : array
	{
		return [
			'no response at all'          => [
				static fn( int $id ) : string => '',
			],
			'incomplete header'           => [
				fn( int $id ) : string => substr( $this->header( self::STDOUT, $id, 100 ), 0, 4 ),
			],
			'incomplete content'          => [
				fn( int $id ) : string => $this->header( self::STDOUT, $id, 100 ) . 'only 10 by',
			],
			'missing padding'             => [
				fn( int $id ) : string => $this->header( self::STDOUT, $id, 4, 5 ) . 'unit',
			],
			'missing end-request record'  => [
				fn( int $id ) : string => $this->record( self::STDOUT, $id, 'unit' ),
			],
			'incomplete end-request body' => [
				fn( int $id ) : string => $this->record( self::STDOUT, $id, 'unit' )
				                          . $this->header( self::END_REQUEST, $id, 8 ) . chr( 0 ),
			],
		];
	}

	/**
	 * @throws Throwable
	 */
	public function testReadingTimesOutIfResponseIsNotCompleted() : void
	{
		$socketId = $this->sendRequestAndRespond(
			fn( int $id ) : string => $this->header( self::STDOUT, $id, 100 ) . 'only 10 by',
			false
		);

		$start = microtime( true );

		try
		{
			$this->client->readResponse( $socketId );

			self::fail( 'Expected TimedoutException to be thrown.' );
		}
		catch ( TimedoutException $e )
		{
			$duration = (microtime( true ) - $start) * 1000;

			self::assertSame( 'Read timed out', $e->getMessage() );

			# The read must not wait for the timeout more than once
			self::assertGreaterThanOrEqual( self::READ_TIMEOUT - 10, $duration );
			self::assertLessThan( self::READ_TIMEOUT * 2, $duration );
		}
	}

	/**
	 * @throws Throwable
	 */
	public function testValidResponseIsRead() : void
	{
		$socketId = $this->sendRequestAndRespond(
			fn( int $id ) : string => $this->record( self::STDOUT, $id, "X-Unit: Test\r\n\r\nunit" )
			                          . $this->endRequest( $id )
		);

		$response = $this->client->readResponse( $socketId );

		self::assertSame( 'Test', $response->getHeaderLine( 'X-Unit' ) );
		self::assertSame( 'unit', $response->getBody() );
		self::assertSame( '', $response->getError() );
	}

	/**
	 * @throws Throwable
	 */
	public function testPaddingAndManagementRecordsAreSkipped() : void
	{
		$socketId = $this->sendRequestAndRespond(
			fn( int $id ) : string => $this->record( self::STDOUT, $id, "X-Unit: Test\r\n\r\n", 3 )
			                          . $this->record( self::UNKNOWN_TYPE, 0, str_repeat( chr( 0 ), 8 ) )
			                          . $this->record( self::STDERR, $id, 'error', 7 )
			                          . $this->record( self::STDOUT, $id, 'unit', 4 )
			                          . $this->endRequest( $id )
		);

		$response = $this->client->readResponse( $socketId );

		self::assertSame( 'unit', $response->getBody() );
		self::assertSame( 'error', $response->getError() );
	}

	/**
	 * Sends a request to the server of this test and lets the server write the given response to the client.
	 *
	 * @param callable(int) : string $response Gets the ID of the request and returns the response of the server
	 * @param bool                   $closeConnection
	 *
	 * @return int Socket ID
	 * @throws Throwable
	 */
	private function sendRequestAndRespond( callable $response, bool $closeConnection = true ) : int
	{
		[$host, $port] = explode( ':', (string)stream_socket_get_name( $this->server, false ) );

		$socketId = $this->client->sendAsyncRequest(
			new NetworkSocket( $host, (int)$port, self::READ_TIMEOUT, self::READ_TIMEOUT ),
			new PostRequest( '/path/to/script.php' )
		);

		$connectionToClient = stream_socket_accept( $this->server, 1 );

		if ( false === $connectionToClient )
		{
			self::fail( 'Client did not connect to server.' );
		}

		# The client derives the ID of the request from the ID of the socket
		fwrite( $connectionToClient, $response( $socketId ) );

		if ( $closeConnection )
		{
			fclose( $connectionToClient );

			return $socketId;
		}

		$this->connectionToClient = $connectionToClient;

		return $socketId;
	}

	private function header(
		int $type,
		int $requestId,
		int $contentLength,
		int $paddingLength = 0,
		int $version = 1
	) : string
	{
		return chr( $version & 0xFF )
		       . chr( $type & 0xFF )
		       . chr( ($requestId >> 8) & 0xFF )
		       . chr( $requestId & 0xFF )
		       . chr( ($contentLength >> 8) & 0xFF )
		       . chr( $contentLength & 0xFF )
		       . chr( $paddingLength & 0xFF )
		       . chr( 0 );
	}

	private function record( int $type, int $requestId, string $content, int $paddingLength = 0 ) : string
	{
		return $this->header( $type, $requestId, strlen( $content ), $paddingLength )
		       . $content
		       . str_repeat( chr( 0 ), $paddingLength );
	}

	private function endRequest( int $requestId ) : string
	{
		return $this->record( self::END_REQUEST, $requestId, str_repeat( chr( 0 ), 8 ) );
	}
}
