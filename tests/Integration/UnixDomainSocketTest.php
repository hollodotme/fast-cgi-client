<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2018 Holger Woltersdorf
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
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use function chmod;

final class UnixDomainSocketTest extends TestCase
{
	/** @var Client */
	private $client;

	protected function setUp() : void
	{
		$connection   = new UnixDomainSocket( '/var/run/php-uds.sock' );
		$this->client = new Client( $connection );
	}

	protected function tearDown() : void
	{
		$this->client = null;
	}

	/**
	 * @throws Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReceiveRequestId() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestId = $this->client->sendAsyncRequest( $request );

		$this->assertGreaterThanOrEqual( 1, $requestId );
		$this->assertLessThanOrEqual( 65535, $requestId );
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

		$requestId = $this->client->sendAsyncRequest( $request );
		$response  = $this->client->readResponse( $requestId );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );
		$this->assertSame( $requestId, $response->getRequestId() );
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

		$response = $this->client->sendRequest( $request );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );

		$this->assertGreaterThanOrEqual( 1, $response->getRequestId() );
		$this->assertLessThanOrEqual( 65535, $response->getRequestId() );
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

		$this->client->sendAsyncRequest( $request );
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
			function ( Throwable $throwable ) use ( $unitTest )
			{
				$unitTest->assertInstanceOf( RuntimeException::class, $throwable );
				$unitTest->assertSame( 'Response callback threw exception.', $throwable->getMessage() );
			}
		);

		$this->client->sendAsyncRequest( $request );
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
	public function testCanCheckForRequestIdsHavingResponses() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestId = $this->client->sendAsyncRequest( $request );

		usleep( 60000 );

		$this->assertTrue( $this->client->hasResponse( $requestId ) );
		$this->assertEquals( [$requestId], $this->client->getRequestIdsHavingResponse() );
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

		$requestIdOne = $this->client->sendAsyncRequest( $request );

		$request->setContent( http_build_query( ['test-key' => 'test'] ) );

		$requestIdTwo = $this->client->sendAsyncRequest( $request );

		usleep( 110000 );

		$requestIds = [$requestIdOne, $requestIdTwo];

		$this->assertEquals( $requestIds, $this->client->getRequestIdsHavingResponse() );

		foreach ( $this->client->readResponses( null, ...$requestIds ) as $response )
		{
			if ( $response->getRequestId() === $requestIdOne )
			{
				$this->assertSame( 'unit', $response->getBody() );
			}

			if ( $response->getRequestId() === $requestIdTwo )
			{
				$this->assertSame( 'test', $response->getBody() );
			}
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
		$connection = new UnixDomainSocket( '/var/run/php-uds.sock', Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

		$response = $client->sendRequest( $request );

		$this->assertSame( 'unit - 0', $response->getBody() );

		$content = http_build_query( ['sleep' => 2, 'test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

		$this->expectException( TimedoutException::class );

		/** @noinspection UnusedFunctionResultInspection */
		$client->sendRequest( $request );
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

		$this->client->sendAsyncRequest( $request );

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

		$this->client->sendAsyncRequest( $request );

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

		$requestId = $this->client->sendAsyncRequest( $request );

		$this->client->waitForResponse( $requestId );
	}

	/**
	 * @throws Exception
	 * @throws AssertionFailedError
	 * @throws \PHPUnit\Framework\Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testReadResponsesSkipsUnknownRequestIds() : void
	{
		$content = http_build_query( ['test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestIds   = [];
		$requestIds[] = $this->client->sendAsyncRequest( $request );
		$requestIds[] = 12345;

		sleep( 1 );

		foreach ( $this->client->readResponses( null, ...$requestIds ) as $response )
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

		$this->client->sendAsyncRequest( $request );
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

		$response = $this->client->sendRequest( $request );

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

		$response = $this->client->sendRequest( $request );

		$this->assertSame( '404 Not Found', $response->getHeader( 'Status' ) );
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

		$response = $this->client->sendRequest( $request );

		$this->assertSame( '403 Forbidden', $response->getHeader( 'Status' ) );
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
		$response = $this->client->sendRequest( $request );

		$this->assertSame( '403 Forbidden', $response->getHeader( 'Status' ) );
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

		$requestId = $this->client->sendAsyncRequest( $request );
		$this->client->handleResponse( $requestId );

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

		$requestId = $this->client->sendAsyncRequest( $request );
		$this->client->handleResponse( $requestId );
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
		$response = $this->client->sendRequest( $request );

		$expectedError = "#^PHP message: ERROR1\n\n?"
		                 . "PHP message: ERROR2\n\n?"
		                 . "PHP message: ERROR3\n\n?"
		                 . "PHP message: ERROR4\n\n?"
		                 . "PHP message: ERROR5\n\n?$#";

		$this->assertRegExp( $expectedError, $response->getError() );
	}
}
