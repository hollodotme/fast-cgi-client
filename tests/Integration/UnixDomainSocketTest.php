<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2019 Holger Woltersdorf & Contributors
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

namespace hollodotme\FastCGI\Tests\Integration;

use Exception;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;
use function chmod;

final class UnixDomainSocketTest extends TestCase
{
	use SocketDataProviding;

	/** @var UnixDomainSocket */
	private $connection;

	/** @var Client */
	private $client;

	protected function setUp() : void
	{
		$this->connection = new UnixDomainSocket( $this->getUnixDomainSocket() );
		$this->client     = new Client();
	}

	protected function tearDown() : void
	{
		$this->connection = null;
		$this->client     = null;
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReceiveSocketId() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );

		$this->assertGreaterThanOrEqual( 1, $socketId );
		$this->assertLessThanOrEqual( 65535, $socketId );
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
		$content          = http_build_query( ['test-key' => 'unit'] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );
		$response = $this->client->readResponse( $socketId );

		$this->assertEquals( $expectedResponse, $response->getOutput() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );
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
		$content          = http_build_query( ['test-key' => 'unit'] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$response = $this->client->sendRequest( $this->connection, $request );

		$this->assertEquals( $expectedResponse, $response->getOutput() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );
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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$socketId = $this->client->sendAsyncRequest( $this->connection, $request );

		usleep( 60000 );

		$this->assertTrue( $this->client->hasResponse( $socketId ) );
		$this->assertEquals( [$socketId], $this->client->getSocketIdsHavingResponse() );
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReadResponses() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$socketIdOne = $this->client->sendAsyncRequest( $this->connection, $request );

		$request->setContent( http_build_query( ['test-key' => 'test'] ) );

		$socketIdTwo = $this->client->sendAsyncRequest( $this->connection, $request );

		usleep( 110000 );

		$socketIds = [$socketIdOne, $socketIdTwo];

		$this->assertEquals( $socketIds, $this->client->getSocketIdsHavingResponse() );

		$expectedBodies = ['unit' => 'unit', 'test' => 'test'];
		foreach ( $this->client->readResponses( null, ...$socketIds ) as $response )
		{
			$this->assertContains( $response->getBody(), $expectedBodies );

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
		$connection = new UnixDomainSocket(
			$this->getUnixDomainSocket(),
			Defaults::CONNECT_TIMEOUT,
			100
		);
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

		$response = $this->client->sendRequest( $connection, $request );

		$this->assertSame( 'unit - 0', $response->getBody() );

		$content = http_build_query( ['sleep' => 1, 'test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReadReadyResponses() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$socketIds   = [];
		$socketIds[] = $this->client->sendAsyncRequest( $this->connection, $request );
		$socketIds[] = 12345;

		sleep( 1 );

		foreach ( $this->client->readResponses( null, ...$socketIds ) as $response )
		{
			echo $response->getBody();
		}

		$this->assertFalse( $this->client->hasUnhandledResponses() );

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
		$content = http_build_query( $data );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

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
		$content = str_repeat( 'a', $length );
		$request = new PostRequest( __DIR__ . '/Workers/lengthWorker.php', $content );

		$response = $this->client->sendRequest( $this->connection, $request );

		$this->assertEquals( $length, $response->getBody() );
	}

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
	 * @throws AssertionFailedError
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @dataProvider invalidScriptFileNamesProvider
	 */
	public function testRequestingAnUnknownScriptPathThrowsException( string $scriptFilename ) : void
	{
		$request = new GetRequest( $scriptFilename, '' );

		$response = $this->client->sendRequest( $this->connection, $request );

		$this->assertSame( '404 Not Found', $response->getHeaderLine( 'Status' ) );
		$this->assertSame( "File not found.\n", $response->getBody() );
		$this->assertRegExp( "#^Primary script unknown\n?$#", $response->getError() );
	}

	public function invalidScriptFileNamesProvider() : array
	{
		return [
			[
				'scriptFilename' => '/unknown/script.php',
			],
			[
				# Existing script filenames containing path traversals do not work either
				'scriptFilename' => __DIR__ . '/../Integration/Workers/worker.php',
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testNotAllowedFileNameExtensionRespondsWithAccessDeniedHeader() : void
	{
		$request = new GetRequest( __DIR__ . '/Workers/worker.php7', '' );

		$response = $this->client->sendRequest( $this->connection, $request );

		$this->assertSame( '403 Forbidden', $response->getHeaderLine( 'Status' ) );
		$this->assertRegExp(
			'#^Access to the script .+ has been denied \(see security\.limit_extensions\)$#',
			$response->getError()
		);
		$this->assertSame( "Access denied.\n", $response->getBody() );
	}

	/**
	 * @throws ConnectException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public function testUnaccessibleScriptRespondsWithAccessDeniedHeader() : void
	{
		$scriptPath = __DIR__ . '/Workers/inaccessibleWorker.php';

		$this->makeFileUnaccessible( $scriptPath );

		$request  = new GetRequest( $scriptPath, '' );
		$response = $this->client->sendRequest( $this->connection, $request );

		$this->assertSame( '403 Forbidden', $response->getHeaderLine( 'Status' ) );
		$this->assertRegExp(
			'#^Unable to open primary script\: .+ \(Permission denied\)$#',
			$response->getError()
		);
		$this->assertSame( "Access denied.\n", $response->getBody() );

		$this->makeFileAccessible( $scriptPath );
	}

	private function makeFileUnaccessible( string $filepath ) : void
	{
		@chmod( $filepath, 0200 );
	}

	private function makeFileAccessible( string $filepath ) : void
	{
		@chmod( $filepath, 0755 );
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

		$request = new GetRequest( '/not/existing.php', '' );
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

		$request = new GetRequest( '/not/existing.php', '' );
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
		$request  = new GetRequest( __DIR__ . '/Workers/errorLogWorker.php', '' );
		$response = $this->client->sendRequest( $this->connection, $request );

		$expectedError = "#^PHP message: ERROR1\n\n?"
		                 . "PHP message: ERROR2\n\n?"
		                 . "PHP message: ERROR3\n\n?"
		                 . "PHP message: ERROR4\n\n?"
		                 . "PHP message: ERROR5\n\n?$#";

		$this->assertRegExp( $expectedError, $response->getError() );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public function testSuccessiveRequestsShouldUseSameSocket() : void
	{
		$request = new GetRequest( __DIR__ . '/Workers/sleepWorker.php', '' );

		$sockets = (new ReflectionClass( $this->client ))->getProperty( 'sockets' );
		$sockets->setAccessible( true );

		$this->assertCount( 0, $sockets->getValue( $this->client ) );

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

		$this->assertSame( $firstSocket, $lastSocket );
		$this->assertCount( 1, $sockets->getValue( $this->client ) );
	}
}
