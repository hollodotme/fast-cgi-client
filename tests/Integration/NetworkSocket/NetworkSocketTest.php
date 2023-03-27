<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\NetworkSocket;

use Exception;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\RegularExpression;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;
use function preg_match;

final class NetworkSocketTest extends TestCase
{
	use SocketDataProviding;

	/** @var NetworkSocket */
	private $connection;

	/** @var Client */
	private $client;

	protected function setUp() : void
	{
		$this->connection = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );
		$this->client     = new Client();
	}

	protected function tearDown() : void
	{
		unset( $this->connection, $this->client );
	}

	private function getWorkerPath( string $workerFile ) : string
	{
		return sprintf( '%s/Workers/%s', dirname( __DIR__ ), $workerFile );
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReceiveSocketId() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );

		self::assertGreaterThanOrEqual( 1, $socketId );
		self::assertLessThanOrEqual( 65535, $socketId );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReadResponse() : void
	{
		$content          = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request          = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );
		$response = $this->client->readResponse( $socketId );

		self::assertEquals( $expectedResponse, $response->getOutput() );
		self::assertSame( 'unit', $response->getBody() );
		self::assertGreaterThan( 0, $response->getDuration() );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendSyncRequestAndReceiveResponse() : void
	{
		$content          = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request          = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertEquals( $expectedResponse, $response->getOutput() );
		self::assertSame( 'unit', $response->getBody() );
		self::assertGreaterThan( 0, $response->getDuration() );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReceiveResponseInCallback() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$this->client->sendAsyncRequest( $this->connection, $request );
		$this->client->waitForResponses();
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanHandleExceptionsInFailureCallback() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			static function ()
			{
				throw new RuntimeException( 'Response callback threw exception.' );
			}
		);

		$request->addFailureCallbacks(
			static function ( Throwable $throwable ) use ( $unitTest )
			{
				$unitTest->assertInstanceOf( RuntimeException::class, $throwable );
				$unitTest->assertSame( 'Response callback threw exception.', $throwable->getMessage() );
			}
		);

		$this->client->sendAsyncRequest( $this->connection, $request );
		$this->client->waitForResponses();
	}

	/**
	 * @throws Exception
	 * @throws AssertionFailedError
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanCheckForSocketIdsHavingResponses() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );

		usleep( 60000 );

		self::assertTrue( $this->client->hasResponse( $socketId ) );
		self::assertEquals( [$socketId], $this->client->getSocketIdsHavingResponse() );
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReadResponses() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$socketIdOne = $this->client->sendAsyncRequest( $this->connection, $request );

        $request = new PostRequest( $this->getWorkerPath( 'worker.php' ), new UrlEncodedFormData( ['test-key' => 'test'] ) );

		$socketIdTwo = $this->client->sendAsyncRequest( $this->connection, $request );

		usleep( 110000 );

		$socketIds = [$socketIdOne, $socketIdTwo];

		self::assertEquals( $socketIds, $this->client->getSocketIdsHavingResponse() );

		$expectedBodies = ['unit' => 'unit', 'test' => 'test'];
		foreach ( $this->client->readResponses( null, ...$socketIds ) as $response )
		{
			self::assertContains( $response->getBody(), $expectedBodies );

			unset( $expectedBodies[ $response->getBody() ] );
		}
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testReadingSyncResponseCanTimeOut() : void
	{
		$connection = new NetworkSocket(
			$this->getNetworkSocketHost(),
			$this->getNetworkSocketPort(),
			Defaults::CONNECT_TIMEOUT,
			100
		);
		$content    = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request    = new PostRequest( $this->getWorkerPath( 'sleepWorker.php' ), $content );

		$response = $this->client->sendRequest( $connection, $request );

		self::assertSame( 'unit - 0', $response->getBody() );

		$content = new UrlEncodedFormData( ['sleep' => 1, 'test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'sleepWorker.php' ), $content );

		$this->expectException( TimedoutException::class );

		/** @noinspection UnusedFunctionResultInspection */
		$this->client->sendRequest( $connection, $request );
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanHandleReadyResponses() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$this->client->sendAsyncRequest( $this->connection, $request );

		while ( $this->client->hasUnhandledResponses() )
		{
			$this->client->handleReadyResponses();
		}
	}

	/**
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ReadFailedException
	 */
	public function testCanReadReadyResponses() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$this->client->sendAsyncRequest( $this->connection, $request );

		while ( $this->client->hasUnhandledResponses() )
		{
			foreach ( $this->client->readReadyResponses() as $response )
			{
				echo $response->getBody();
			}
		}

		$this->expectOutputString( 'unit' );
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanWaitForResponse() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );

		$this->client->waitForResponse( $socketId );
	}

	/**
	 * @throws Exception
	 * @throws AssertionFailedError
	 * @throws \PHPUnit\Framework\Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testReadResponsesSkipsUnknownSocketIds() : void
	{
		$content = new UrlEncodedFormData( ['test-key' => 'unit'] );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$socketIds   = [];
		$socketIds[] = $this->client->sendAsyncRequest( $this->connection, $request );
		$socketIds[] = 12345;

		sleep( 1 );

		foreach ( $this->client->readResponses( null, ...$socketIds ) as $response )
		{
			echo $response->getBody();
		}

		self::assertFalse( $this->client->hasUnhandledResponses() );

		$this->expectOutputString( 'unit' );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReceiveBufferInPassThroughCallback() : void
	{
		$data    = [
			'test-key'        => str_repeat( 'test-first-key', 5000 ),
			'test-second-key' => 'test-second-key',
			'test-third-key'  => str_repeat( 'test-third-key', 5000 ),
		];
		$content = new UrlEncodedFormData( $data );
		$request = new PostRequest( $this->getWorkerPath( 'worker.php' ), $content );

		$unitTest    = $this;
		$passCounter = 0;

		$request->addPassThroughCallbacks(
			static function ( $buffer ) use ( $unitTest, $data, &$passCounter )
			{
				if ( 0 === $passCounter++ )
				{
					# The first key is large enough to be dumped immediately with all headers
					$unitTest->assertStringContainsString( $data['test-key'], $buffer );

					return;
				}

				# The second key is too small to be dumped,
				# hence the second packet will show both second AND third keys
				$unitTest->assertStringNotContainsString( $data['test-key'], $buffer );
				$unitTest->assertStringContainsString( $data['test-second-key'], $buffer );
				$unitTest->assertStringContainsString( $data['test-third-key'], $buffer );
			}
		);

		$this->client->sendAsyncRequest( $this->connection, $request );
		$this->client->waitForResponses();
	}

	/**
	 * @param int $length
	 *
	 * @throws Throwable
	 * @throws WriteFailedException
	 *
	 * @dataProvider contentLengthProvider
	 */
	public function testCanGetLengthOfSentContent( int $length ) : void
	{
        $content = new UrlEncodedFormData(['test' => str_repeat( 'a', $length )]);
		$request = new PostRequest( $this->getWorkerPath( 'lengthWorker.php' ), $content );
		$request->setContentType( '*/*' );
		$result = $this->client->sendRequest( $this->connection, $request );

		self::assertEquals( $length + 5, $result->getBody() );
	}

	/**
	 * @return array<array<string, int>>
	 */
	public function contentLengthProvider() : array
	{
		return [
			[
				'length' => 1024,
			],
			[
				'length' => 2048,
			],
			[
				'length' => 4096,
			],
			[
				'length' => 8192,
			],
			[
				'length' => 16384,
			],
			[
				'length' => 32768,
			],
			[
				'length' => 65535,
			],
			[
				'length' => 65536,
			],
			[
				'length' => 131072,
			],
			[
				'length' => 262144,
			],
			[
				'length' => 524288,
			],
		];
	}

	/**
	 * @param string $scriptFilename
	 *
	 * @throws ConnectException
	 * @throws AssertionFailedError
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @dataProvider invalidScriptFileNamesProvider
	 */
	public function testRequestingAnUnknownScriptPathThrowsException( string $scriptFilename ) : void
	{
		$request = new GetRequest( $scriptFilename );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( '404 Not Found', $response->getHeaderLine( 'Status' ) );
		self::assertSame( "File not found.\n", $response->getBody() );
		$this->assertMatchesRegExp( "#^Primary script unknown\n?$#", $response->getError() );
	}

	/**
	 * @param string $pattern
	 * @param string $string
	 * @param string $message
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	private function assertMatchesRegExp( string $pattern, string $string, string $message = '' ) : void
	{
		self::assertThat( $string, new RegularExpression( $pattern ), $message );
	}

	/**
	 * @return array<array<string, string>>
	 */
	public function invalidScriptFileNamesProvider() : array
	{
		return [
			[
				'scriptFilename' => '/unknown/script.php',
			],
			[
				# Existing script filenames containing path traversals do not work either
				'scriptFilename' => dirname( __DIR__ ) . '/../Integration/Workers/worker.php',
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testNotAllowedFileNameExtensionRespondsWithAccessDeniedHeader() : void
	{
		$request = new GetRequest( $this->getWorkerPath( 'worker.php7' ) );

		$response = $this->client->sendRequest( $this->connection, $request );

		self::assertSame( '403 Forbidden', $response->getHeaderLine( 'Status' ) );
		$this->assertMatchesRegExp(
			'#^Access to the script .+ has been denied \(see security\.limit_extensions\)$#',
			$response->getError()
		);
		self::assertSame( "Access denied.\n", $response->getBody() );
	}

	/**
	 * @throws ConnectException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testUnaccessibleScriptRespondsWithAccessDeniedHeader() : void
	{
		$scriptPath = $this->getWorkerPath( 'inaccessibleWorker.php' );

		$this->makeFileUnaccessible( $scriptPath );

		$request  = new GetRequest( $scriptPath );
		$response = $this->client->sendRequest( $this->connection, $request );

        $this->makeFileAccessible( $scriptPath );

		$expectedStatus = [
			'403 Forbidden',
			'404 Not Found',
		];

		$expectedErrors = [
			'#^Unable to open primary script\: .+ \(Permission denied\)$#',
			'#^Unable to open primary script\: .+ \(Operation not permitted\)$#',
		];

		$expectedBodies = [
			"Access denied.\n",
			"No input file specified.\n",
		];

		self::assertContains( $response->getHeaderLine( 'Status' ), $expectedStatus );

		$errorMatched = false;
		foreach ( $expectedErrors as $errorPattern )
		{
			$errorMatched = $errorMatched || (bool)preg_match( $errorPattern, $response->getError() );
		}

		self::assertTrue( $errorMatched );
		self::assertContains( $response->getBody(), $expectedBodies );
	}

	private function makeFileUnaccessible( string $filepath ) : void
	{
		chmod( $filepath, 0200 );
	}

	private function makeFileAccessible( string $filepath ) : void
	{
		chmod( $filepath, 0755 );
	}

	/**
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanGetErrorBufferInPassThroughCallback() : void
	{
		$expectecOutputRegExp = "#^ERROR: Primary script unknown\n?$#";

		$request = new GetRequest( '/not/existing.php' );
		$request->addPassThroughCallbacks(
			static function (
				/** @noinspection PhpUnusedParameterInspection */
				string $outputBuffer,
				string $errorBuffer
			)
			{
				if ( '' !== $errorBuffer )
				{
					echo 'ERROR: ' . $errorBuffer;
				}
			}
		);

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );
		$this->client->handleResponse( $socketId );

		$this->expectOutputRegex( $expectecOutputRegExp );
	}

	/**
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanGetErrorInResponseCallback() : void
	{
		$unitTest = $this;

		$request = new GetRequest( '/not/existing.php' );
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'Primary script unknown', $response->getError() );
			}
		);

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );
		$this->client->handleResponse( $socketId );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanGetErrorOutputFromWorkerUsingErrorLog() : void
	{
		$request  = new GetRequest( $this->getWorkerPath( 'errorLogWorker.php' ) );
		$response = $this->client->sendRequest( $this->connection, $request );

		$expectedError = "#^PHP message: ERROR1\n\n?"
		                 . "PHP message: ERROR2\n\n?"
		                 . "PHP message: ERROR3\n\n?"
		                 . "PHP message: ERROR4\n\n?"
		                 . "PHP message: ERROR5\n\n?$#";

		$this->assertMatchesRegExp( $expectedError, $response->getError() );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws InvalidArgumentException
	 */
	public function testSuccessiveRequestsShouldUseSameSocket() : void
	{
		$request = new GetRequest( $this->getWorkerPath( 'sleepWorker.php' ) );

		$sockets = (new ReflectionClass( $this->client ))->getProperty( 'sockets' );
		$sockets->setAccessible( true );

		self::assertCount( 0, $sockets->getValue( $this->client ) );

		/** @noinspection UnusedFunctionResultInspection */
		$this->client->sendRequest( $this->connection, $request );

		/** @var SocketCollection $socketCollection */
		$socketCollection = $sockets->getValue( $this->client );
		$firstSocket      = $socketCollection->getIdleSocket( $this->connection );

		for ( $i = 0; $i < 5; $i++ )
		{
			/** @noinspection UnusedFunctionResultInspection */
			$this->client->sendRequest( $this->connection, $request );
		}

		$lastSocket = $socketCollection->getIdleSocket( $this->connection );

		self::assertSame( $firstSocket, $lastSocket );
		self::assertCount( 1, $sockets->getValue( $this->client ) );
	}
}
