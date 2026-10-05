<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Compatibility;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\RequestContents\PlainText;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\AbstractRequest;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;
use Throwable;
use function chr;
use function explode;
use function fclose;
use function in_array;
use function md5;
use function microtime;
use function sort;
use function sprintf;
use function str_repeat;
use function stream_socket_client;
use function strlen;
use function trim;
use function usleep;

/**
 * Checks the compatibility of the client with the FastCGI server of another programming language.
 * The server runs the application described in .docker/compatibility/README.md.
 */
final class CompatibilityTest extends TestCase
{
	private const SERVER_START_TIMEOUT = 30;

	private Client $client;

	private NetworkSocket $connection;

	/**
	 * Waits until the server accepts connections, because its container is started right before the tests.
	 */
	public static function setUpBeforeClass() : void
	{
		$address = sprintf( 'tcp://%s:%d', self::getServerHost(), self::getServerPort() );
		$timeout = microtime( true ) + self::SERVER_START_TIMEOUT;

		do
		{
			$connection = @stream_socket_client( $address, $errorNumber, $errorMessage, 1 );

			if ( false !== $connection )
			{
				fclose( $connection );

				return;
			}

			usleep( 100000 );
		}
		while ( microtime( true ) < $timeout );

		self::fail(
			sprintf(
				'FastCGI server at %s is not reachable after %d seconds: %s',
				$address,
				self::SERVER_START_TIMEOUT,
				(string)$errorMessage
			)
		);
	}

	protected function setUp() : void
	{
		$this->client     = new Client();
		$this->connection = new NetworkSocket( self::getServerHost(), self::getServerPort() );
	}

	/**
	 * @throws Throwable
	 */
	public function testRequestParametersAreReceived() : void
	{
		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/echo', ['unit' => 'test', 'number' => 1] );
		$request->setCustomVar( 'COMPATIBILITY_TEST', 'Custom value' );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( 'GET', $response->getHeaderLine( 'X-Request-Method' ) );
		self::assertSame( 'unit=test&number=1', $response->getHeaderLine( 'X-Query-String' ) );
		self::assertSame( 'Custom value', $response->getHeaderLine( 'X-Custom-Param' ) );
		self::assertSame( '0', $response->getHeaderLine( 'X-Content-Length' ) );
		self::assertStringStartsWith( 'text/plain', $response->getHeaderLine( 'Content-Type' ) );
		self::assertSame( '', $response->getBody() );
		self::assertSame( '', $response->getError() );
	}

	/**
	 * Names and values of parameters are encoded with 4 bytes for their length, if they are longer than 127 bytes.
	 *
	 * @throws Throwable
	 */
	public function testLongRequestParametersAreReceived() : void
	{
		$value   = str_repeat( 'Long value ', 50 );
		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/echo' );
		$request->setCustomVar( 'COMPATIBILITY_TEST', trim( $value ) );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( trim( $value ), $response->getHeaderLine( 'X-Custom-Param' ) );
	}

	/**
	 * @throws Throwable
	 */
	public function testRequestBodyIsReceived() : void
	{
		$content = new UrlEncodedFormData( ['unit' => 'test', 'text' => 'some text & more'] );
		$request = $this->getRequest( new PostRequest( 'compatibility', $content ), '/echo' );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( 'POST', $response->getHeaderLine( 'X-Request-Method' ) );
		self::assertSame( (string)strlen( $content->getContent() ), $response->getHeaderLine( 'X-Content-Length' ) );
		self::assertSame( $content->getContent(), $response->getBody() );
	}

	/**
	 * A request body that is larger than 65535 bytes is sent in multiple packets.
	 * The body contains all byte values to make sure that request and response are transferred binary safe.
	 *
	 * @param int $length
	 *
	 * @throws Throwable
	 * @dataProvider lengthProvider
	 */
	public function testRequestBodyOfAnyLengthIsReceived( int $length ) : void
	{
		$body    = $this->getBinaryContent( $length );
		$request = $this->getRequest( new PostRequest( 'compatibility', new PlainText( $body ) ), '/echo' );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( (string)$length, $response->getHeaderLine( 'X-Content-Length' ) );
		self::assertSame( $length, strlen( $response->getBody() ) );
		self::assertSame( md5( $body ), md5( $response->getBody() ) );
	}

	/**
	 * A response that is larger than 65535 bytes is received in multiple packets.
	 *
	 * @param int $length
	 *
	 * @throws Throwable
	 * @dataProvider lengthProvider
	 */
	public function testResponseOfAnyLengthIsRead( int $length ) : void
	{
		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/output', ['bytes' => $length] );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( $length, strlen( $response->getBody() ) );
		self::assertSame( str_repeat( 'x', $length ), $response->getBody() );
	}

	/**
	 * @return array<string, array<int, int>>
	 */
	public function lengthProvider() : array
	{
		return [
			'1 byte'                           => [1],
			'largest content of one packet'    => [65535],
			'one byte more than one packet'    => [65536],
			'multiple packets'                 => [200000],
		];
	}

	/**
	 * @param int $statusCode
	 *
	 * @throws Throwable
	 * @dataProvider statusCodeProvider
	 */
	public function testStatusOfResponseIsRead( int $statusCode ) : void
	{
		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/status', ['code' => $statusCode] );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertStringStartsWith( (string)$statusCode, $response->getHeaderLine( 'Status' ) );
		self::assertSame( 'Status ' . $statusCode, $response->getBody() );
	}

	/**
	 * @return array<array<int, int>>
	 */
	public function statusCodeProvider() : array
	{
		return [[201], [404], [500]];
	}

	/**
	 * @throws Throwable
	 */
	public function testUnknownPathRespondsWithStatusNotFound() : void
	{
		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/unknown' );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertStringStartsWith( '404', $response->getHeaderLine( 'Status' ) );
	}

	/**
	 * The client keeps connections open and re-uses them. Whether the server keeps them open as well or closes
	 * them after each request, successive requests must succeed.
	 *
	 * @throws Throwable
	 */
	public function testSuccessiveRequestsSucceed() : void
	{
		for ( $i = 1; $i <= 10; $i++ )
		{
			$content = new PlainText( 'Request ' . $i );
			$request = $this->getRequest( new PostRequest( 'compatibility', $content ), '/echo', ['request' => $i] );

			$response = $this->client->sendRequest( $this->connection, $request );

			self::assertSame( 'request=' . $i, $response->getHeaderLine( 'X-Query-String' ) );
			self::assertSame( 'Request ' . $i, $response->getBody() );
		}
	}

	/**
	 * @throws Throwable
	 */
	public function testResponsesOfAsynchronousRequestsAreRead() : void
	{
		$socketIds = [];

		for ( $i = 1; $i <= 5; $i++ )
		{
			$content     = new PlainText( 'Request ' . $i );
			$request     = $this->getRequest( new PostRequest( 'compatibility', $content ), '/echo' );
			$socketIds[] = $this->client->sendAsyncRequest( $this->connection, $request );
		}

		$bodies = [];

		foreach ( $this->client->readResponses( null, ...$socketIds ) as $response )
		{
			$bodies[] = $response->getBody();
		}

		self::assertSame( ['Request 1', 'Request 2', 'Request 3', 'Request 4', 'Request 5'], $bodies );
	}

	/**
	 * @throws Throwable
	 */
	public function testCallbacksOfAsynchronousRequestsAreNotified() : void
	{
		$bodies   = [];
		$failures = [];

		for ( $i = 1; $i <= 5; $i++ )
		{
			$content = new PlainText( 'Request ' . $i );
			$request = $this->getRequest( new PostRequest( 'compatibility', $content ), '/echo' );

			$request->addResponseCallbacks(
				static function ( ProvidesResponseData $response ) use ( &$bodies ) : void
				{
					$bodies[] = $response->getBody();
				}
			);
			$request->addFailureCallbacks(
				static function ( Throwable $e ) use ( &$failures ) : void
				{
					$failures[] = $e->getMessage();
				}
			);

			$this->client->sendAsyncRequest( $this->connection, $request );
		}

		$this->client->waitForResponses();

		sort( $bodies );

		self::assertSame( [], $failures );
		self::assertSame( ['Request 1', 'Request 2', 'Request 3', 'Request 4', 'Request 5'], $bodies );
	}

	/**
	 * @throws Throwable
	 */
	public function testErrorOutputIsRead() : void
	{
		$this->skipIfServerDoesNotSupport( 'stderr' );

		$request = $this->getRequest( new GetRequest( 'compatibility' ), '/stderr' );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( 'Compatibility test error', trim( $response->getError() ) );
		self::assertSame( 'stderr written', $response->getBody() );
	}

	/**
	 * @param AbstractRequest           $request
	 * @param string                    $path
	 * @param array<string, int|string> $queryParams
	 *
	 * @return AbstractRequest
	 */
	private function getRequest( AbstractRequest $request, string $path, array $queryParams = [] ) : AbstractRequest
	{
		$request->setRequestUri( $path );
		$request->setQueryParams( $queryParams );

		return $request;
	}

	private function getBinaryContent( int $length ) : string
	{
		$allBytes = '';

		for ( $byte = 0; $byte <= 255; $byte++ )
		{
			$allBytes .= chr( $byte );
		}

		return substr( str_repeat( $allBytes, (int)( $length / 256 ) + 1 ), 0, $length );
	}

	/**
	 * @throws Throwable
	 */
	private function skipIfServerDoesNotSupport( string $feature ) : void
	{
		$request  = $this->getRequest( new GetRequest( 'compatibility' ), '/capabilities' );
		$response = $this->client->sendRequest( $this->connection, $request );
		$features = explode( "\n", trim( $response->getBody() ) );

		if ( !in_array( $feature, $features, true ) )
		{
			self::markTestSkipped( sprintf( 'The FastCGI server does not support the feature "%s".', $feature ) );
		}
	}

	private static function getServerHost() : string
	{
		return (string)$_ENV['compatibility-server-host'];
	}

	private static function getServerPort() : int
	{
		return (int)$_ENV['compatibility-server-port'];
	}
}
