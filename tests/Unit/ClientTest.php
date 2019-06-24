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

namespace hollodotme\FastCGI\Tests\Unit;

use Exception;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;

final class ClientTest extends TestCase
{
	use SocketDataProviding;

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testConnectAttemptToNotExistingSocketThrowsException() : void
	{
		$connection = new UnixDomainSocket( $this->getNonExistingUnixDomainSocket() );
		$client     = new Client();
		$request    = new PostRequest( '/path/to/script.php', '' );

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessage( 'Unable to connect to FastCGI application: No such file or directory' );

		/** @noinspection UnusedFunctionResultInspection */
		$client->sendRequest( $connection, $request );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testConnectAttemptToInvalidSocketThrowsException() : void
	{
		$testSocket = realpath( __DIR__ . $this->getInvalidUnixDomainSocket() );

		$connection = new UnixDomainSocket( '' . $testSocket );
		$client     = new Client();
		$request    = new PostRequest( '/path/to/script.php', '' );

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessage( 'Unable to connect to FastCGI application: Connection refused' );

		/** @noinspection UnusedFunctionResultInspection */
		$client->sendRequest( $connection, $request );
	}

	/**
	 * @throws ReadFailedException
	 */
	public function testWaitingForUnknownRequestThrowsException() : void
	{
		$client = new Client();

		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Socket not found for socket ID: 12345' );

		$client->waitForResponse( 12345 );
	}

	/**
	 * @throws ReadFailedException
	 * @throws Throwable
	 */
	public function testWaitingForResponsesWithoutRequestsThrowsException() : void
	{
		$client = new Client();

		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'No pending requests found.' );

		$client->waitForResponses();
	}

	/**
	 * @throws ReadFailedException
	 */
	public function testHandlingUnknownRequestThrowsException() : void
	{
		$client = new Client();

		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Socket not found for socket ID: 12345' );

		$client->handleResponse( 12345 );
	}

	/**
	 * @throws ReadFailedException
	 */
	public function testHandlingUnknownRequestsThrowsException() : void
	{
		$client = new Client();

		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Socket not found for socket ID: 12345' );

		$client->handleResponses( null, 12345, 12346 );
	}

	/**
	 * @throws ConnectException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testConnectAttemptToRestrictedUnixDomainSocketThrowsException() : void
	{
		$connection = new UnixDomainSocket( $this->getRestrictedUnixDomainSocket() );
		$client     = new Client();
		$request    = new PostRequest( '/path/to/script.php', '' );

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessageRegExp( '#.*unable to connect to.*#i' );

		/** @noinspection UnusedFunctionResultInspection */
		$client->sendRequest( $connection, $request );
	}

	/**
	 * @throws AssertionFailedError
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 */
	public function testHandlingReadyResponsesJustReturnsIfClientGotNoRequests() : void
	{
		$client = new Client();

		$this->assertFalse( $client->hasUnhandledResponses() );

		$client->handleReadyResponses();
	}
}
