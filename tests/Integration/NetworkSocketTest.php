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

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;

/**
 * Class NetworkSocketTest
 * @package hollodotme\FastCGI\Tests\Integration
 */
final class NetworkSocketTest extends TestCase
{
	/**
	 * @throws \Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReceiveRequestId() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestId = $client->sendAsyncRequest( $request );

		$this->assertGreaterThanOrEqual( 1, $requestId );
		$this->assertLessThanOrEqual( 65535, $requestId );
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanSendAsyncRequestAndReadResponse() : void
	{
		$connection       = new NetworkSocket( '127.0.0.1', 9000 );
		$client           = new Client( $connection );
		$content          = http_build_query( ['test-key' => 'unit'] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$requestId = $client->sendAsyncRequest( $request );
		$response  = $client->readResponse( $requestId );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );
		$this->assertSame( $requestId, $response->getRequestId() );
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanSendSyncRequestAndReceiveResponse() : void
	{
		$connection       = new NetworkSocket( '127.0.0.1', 9000 );
		$client           = new Client( $connection );
		$content          = http_build_query( ['test-key' => 'unit'] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$response = $client->sendRequest( $request );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );

		$this->assertGreaterThanOrEqual( 1, $response->getRequestId() );
		$this->assertLessThanOrEqual( 65535, $response->getRequestId() );
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanReceiveResponseInCallback() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$client->sendAsyncRequest( $request );
		$client->waitForResponses();
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanHandleExceptionsInFailureCallback() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			function ()
			{
				throw new \RuntimeException( 'Response callback threw exception.' );
			}
		);

		$request->addFailureCallbacks(
			function ( \Throwable $throwable ) use ( $unitTest )
			{
				$unitTest->assertInstanceOf( \RuntimeException::class, $throwable );
				$unitTest->assertSame( 'Response callback threw exception.', $throwable->getMessage() );
			}
		);

		$client->sendAsyncRequest( $request );
		$client->waitForResponses();
	}

	/**
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\AssertionFailedError
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanCheckForRequestIdsHavingResponses() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestId = $client->sendAsyncRequest( $request );

		usleep( 60000 );

		$this->assertTrue( $client->hasResponse( $requestId ) );
		$this->assertEquals( [$requestId], $client->getRequestIdsHavingResponse() );
	}

	/**
	 * @throws \Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanReadResponses() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestIdOne = $client->sendAsyncRequest( $request );

		$request->setContent( http_build_query( ['test-key' => 'test'] ) );

		$requestIdTwo = $client->sendAsyncRequest( $request );

		usleep( 110000 );

		$requestIds = [$requestIdOne, $requestIdTwo];

		$this->assertEquals( $requestIds, $client->getRequestIdsHavingResponse() );

		foreach ( $client->readResponses( null, ...$requestIds ) as $response )
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
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 *
	 * @expectedException \hollodotme\FastCGI\Exceptions\TimedoutException
	 */
	public function testReadingSyncResponseCanTimeOut() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000, Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

		$response = $client->sendRequest( $request );

		$this->assertSame( 'unit - 0', $response->getBody() );

		$content = http_build_query( ['sleep' => 2, 'test-key' => 'unit'] );
		$request = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', $content );

		$client->sendRequest( $request );
	}

	/**
	 * @throws \Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanHandleReadyResponses() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000, Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$client->sendAsyncRequest( $request );

		while ( $client->hasUnhandledResponses() )
		{
			$client->handleReadyResponses();
		}
	}

	/**
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanReadReadyResponses() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000, Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$client->sendAsyncRequest( $request );

		while ( $client->hasUnhandledResponses() )
		{
			foreach ( $client->readReadyResponses() as $response )
			{
				echo $response->getBody();
			}
		}

		$this->expectOutputString( 'unit' );
	}

	/**
	 * @throws \Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanWaitForResponse() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000, Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$unitTest = $this;

		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response ) use ( $unitTest )
			{
				$unitTest->assertSame( 'unit', $response->getBody() );
			}
		);

		$requestId = $client->sendAsyncRequest( $request );

		$client->waitForResponse( $requestId );
	}

	/**
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\AssertionFailedError
	 * @throws \PHPUnit\Framework\Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testReadResponsesSkipsUnknownRequestIds() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000, Defaults::CONNECT_TIMEOUT, 1000 );
		$client     = new Client( $connection );
		$content    = http_build_query( ['test-key' => 'unit'] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestIds   = [];
		$requestIds[] = $client->sendAsyncRequest( $request );
		$requestIds[] = 12345;

		sleep( 1 );

		$responses = $client->readResponses( null, ...$requestIds );

		foreach ( $responses as $response )
		{
			echo $response->getBody();
		}

		$this->assertFalse( $client->hasUnhandledResponses() );

		$this->expectOutputString( 'unit' );
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanReceiveBufferInPassThroughCallback() : void
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$data       = [
			'test-key'        => str_repeat( 'test-first-key', 5000 ),
			'test-second-key' => 'test-second-key',
			'test-third-key'  => str_repeat( 'test-third-key', 5000 ),
		];
		$content    = http_build_query( $data );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$unitTest    = $this;
		$passCounter = 0;

		$request->addPassThroughCallbacks(
			function ( $buffer ) use ( $unitTest, $data, &$passCounter )
			{
				if ( 0 === $passCounter++ )
				{
					# The first key is large enough to be dumped immediately with all headers
					$unitTest->assertContains( $data['test-key'], $buffer );

					return;
				}

				# The second key is too small to be dumped,
				# hence the second packet will show both second AND third keys

				$unitTest->assertNotContains( $data['test-key'], $buffer );
				$unitTest->assertContains( $data['test-second-key'], $buffer );
				$unitTest->assertContains( $data['test-third-key'], $buffer );
			}
		);

		$client->sendAsyncRequest( $request );
		$client->waitForResponses();
	}

	/**
	 * @param int $length
	 *
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 *
	 * @dataProvider contentLengthProvider
	 */
	public function testCanGetLengthOfSentContent( int $length ) : void
	{
		$content    = str_repeat( 'a', $length );
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$request    = new PostRequest( __DIR__ . '/Workers/lengthWorker.php', $content );
		$request->setContentType( '*/*' );
		$result = $client->sendRequest( $request );

		$this->assertEquals( $length, $result->getBody() );
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
}
