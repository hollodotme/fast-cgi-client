<?php declare(strict_types=1);

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
		$request    = new PostRequest( '/path/to/script.php' );

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
		$request    = new PostRequest( '/path/to/script.php' );

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
		$request    = new PostRequest( '/path/to/script.php' );

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessage( 'Unable to connect to FastCGI application: No such file or directory' );

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

		self::assertFalse( $client->hasUnhandledResponses() );

		$client->handleReadyResponses();
	}
}
