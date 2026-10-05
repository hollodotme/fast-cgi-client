<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use Exception;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketId;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;
use function dirname;
use function get_resource_type;
use const PHP_VERSION_ID;

final class SocketTest extends TestCase
{
	use SocketDataProviding;

	/**
	 * @throws Exception
	 */
	public function testCanGetIdAfterConstruction() : void
	{
		$socket = $this->getSocket();

		self::assertGreaterThanOrEqual( 1, $socket->getId() );
		self::assertLessThanOrEqual( (1 << 16) - 1, $socket->getId() );
	}

	/**
	 * @param int $connectTimeout
	 * @param int $readWriteTimeout
	 * @param int $streamSelectTimeout
	 *
	 * @return Socket
	 * @throws Exception
	 */
	private function getSocket(
		int $connectTimeout = Defaults::CONNECT_TIMEOUT,
		int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT,
		int $streamSelectTimeout = Defaults::STREAM_SELECT_TIMEOUT
	) : Socket
	{
		$nameValuePairEncoder = new NameValuePairEncoder();
		$packetEncoder        = new PacketEncoder();
		$connection           = new UnixDomainSocket(
			$this->getUnixDomainSocket(),
			$connectTimeout,
			$readWriteTimeout,
			$streamSelectTimeout
		);

		return new Socket( SocketId::new(), $connection, $packetEncoder, $nameValuePairEncoder );
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanSendRequestAndFetchResponse() : void
	{
		$socket  = $this->getSocket();
		$request = new PostRequest(
			dirname( __DIR__, 2 ) . '/Integration/Workers/worker.php',
			new UrlEncodedFormData( ['test-key' => 'unit'] )
		);

		$socket->sendRequest( $request );

		$response = $socket->fetchResponse();

		self::assertSame( 'unit', $response->getBody() );

		$response2 = $socket->fetchResponse();

		self::assertSame( $response, $response2 );
	}

	/**
	 * @throws Exception
	 * @throws AssertionFailedError
	 * @throws \PHPUnit\Framework\Exception
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanCollectResource() : void
	{
		$resources = [];
		$socket    = $this->getSocket();
		$request   = new PostRequest(
			dirname( __DIR__, 2 ) . '/Integration/Workers/worker.php',
			new UrlEncodedFormData( ['test-key' => 'unit'] )
		);

		$socket->collectResource( $resources );

		self::assertEmpty( $resources );

		$socket->sendRequest( $request );

		$socket->collectResource( $resources );

		self::assertCount( 1, $resources );
		self::assertArrayHasKey( $socket->getId(), $resources );
		self::assertSame( 'stream', get_resource_type( $resources[ $socket->getId() ] ) );
	}

	/**
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ReadFailedException
	 * @throws Exception
	 */
	public function testCanNotifyResponseCallback() : void
	{
		$socket  = $this->getSocket();
		$request = new PostRequest(
			dirname( __DIR__, 2 ) . '/Integration/Workers/worker.php',
			new UrlEncodedFormData( ['test-key' => 'unit'] )
		);
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response )
			{
				echo $response->getBody();
			}
		);

		$socket->sendRequest( $request );
		$response = $socket->fetchResponse();
		$socket->notifyResponseCallbacks( $response );

		$this->expectOutputString( 'unit' );
	}

	/**
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws Exception
	 */
	public function testCanNotifyFailureCallback() : void
	{
		$socket  = $this->getSocket();
		$request = new PostRequest(
			dirname( __DIR__, 2 ) . '/Integration/Workers/worker.php',
			new UrlEncodedFormData( ['test-key' => 'unit'] )
		);
		$request->addFailureCallbacks(
			static function ( Throwable $throwable )
			{
				echo $throwable->getMessage();
			}
		);
		$throwable = new RuntimeException( 'Something went wrong.' );

		$socket->sendRequest( $request );
		$socket->notifyFailureCallbacks( $throwable );

		$this->expectOutputString( 'Something went wrong.' );
	}

	/**
	 * @throws AssertionFailedError
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws Exception
	 */
	public function testThrowsExceptionIfRequestIsSentToSocketThatIsNotIdle() : void
	{
		$socket  = $this->getSocket();
		$request = new PostRequest( '/some/script.php' );

		$socket->sendRequest( $request );

		$this->expectException( ConnectException::class );
		$this->expectExceptionMessage( 'Trying to send a request to a socket that is not idle.' );

		$socket->sendRequest( $request );

		self::fail( 'Expected ConnectException to be thrown.' );
	}

	/**
	 * @param int                     $flag
	 * @param class-string<Throwable> $expectedException
	 * @param string                  $expectedExceptionMessage
	 *
	 * @throws AssertionFailedError
	 * @throws ReflectionException
	 * @throws Exception
	 *
	 * @dataProvider responseFlagProvider
	 */
	public function testRequestCompletedGuard(
		int $flag,
		string $expectedException,
		string $expectedExceptionMessage
	) : void
	{
		$socket = $this->getSocket();

		$guardMethod = (new ReflectionClass( $socket ))->getMethod( 'guardRequestCompleted' );
		if ( PHP_VERSION_ID < 80100 )
		{
			$guardMethod->setAccessible( true );
		}

		$this->expectException( $expectedException );
		$this->expectExceptionMessage( $expectedExceptionMessage );

		$guardMethod->invoke( $socket, $flag );

		self::fail( 'Expected an Exception to be thrown.' );
	}

	/**
	 * @return array<array<string, int|string>>
	 */
	public function responseFlagProvider() : array
	{
		return [
			[
				'flag'                     => 1,
				'expectedException'        => WriteFailedException::class,
				'expectedExceptionMessage' => 'This app can\'t multiplex [CANT_MPX_CONN]',
			],
			[
				'flag'                     => 2,
				'expectedException'        => WriteFailedException::class,
				'expectedExceptionMessage' => 'New request rejected; too busy [OVERLOADED]',
			],
			[
				'flag'                     => 3,
				'expectedException'        => WriteFailedException::class,
				'expectedExceptionMessage' => 'Role value not known [UNKNOWN_ROLE]',
			],
			[
				'flag'                     => 123,
				'expectedException'        => ReadFailedException::class,
				'expectedExceptionMessage' => 'Unknown content.',
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function testIsUsableWhenInInitialState() : void
	{
		$socket = $this->getSocket();

		self::assertTrue( $socket->isUsable() );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws Exception
	 */
	public function testIsNotUsableWhenTimedOut() : void
	{
		$socket  = $this->getSocket();
		$request = new PostRequest(
			dirname( __DIR__, 2 ) . '/Integration/Workers/sleepWorker.php',
			new UrlEncodedFormData( ['sleep' => 1, 'test-key' => 'unit'] )
		);
		$socket->sendRequest( $request );

		try
		{
			/** @noinspection UnusedFunctionResultInspection */
			$socket->fetchResponse( 100 );
		}
		catch ( TimedoutException $e )
		{
		}

		self::assertFalse( $socket->isUsable() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function testIsNotUsableWhenSocketWasClosed() : void
	{
		$socket = $this->getSocket();

		$connectMethod = (new ReflectionClass( $socket ))->getMethod( 'connect' );
		if ( PHP_VERSION_ID < 80100 )
		{
			$connectMethod->setAccessible( true );
		}
		$connectMethod->invoke( $socket );

		$disconnectMethod = (new ReflectionClass( $socket ))->getMethod( 'disconnect' );
		if ( PHP_VERSION_ID < 80100 )
		{
			$disconnectMethod->setAccessible( true );
		}
		$disconnectMethod->invoke( $socket );

		self::assertFalse( $socket->isUsable() );
	}

	/**
	 * @param int $configuredTimeout
	 * @param int $expectedTimeout
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @dataProvider streamSelectTimeoutProvider
	 */
	public function testStreamSelectTimeoutIsTakenFromConnection( int $configuredTimeout, int $expectedTimeout ) : void
	{
		$socket = $this->getSocket(
			Defaults::CONNECT_TIMEOUT,
			Defaults::READ_WRITE_TIMEOUT,
			$configuredTimeout
		);

		self::assertSame( $expectedTimeout, $socket->getStreamSelectTimeout() );
	}

	/**
	 * @return array<array<string, int>>
	 */
	public function streamSelectTimeoutProvider() : array
	{
		return [
			[
				'configuredTimeout' => Defaults::STREAM_SELECT_TIMEOUT,
				'expectedTimeout'   => 200,
			],
			[
				'configuredTimeout' => 1500,
				'expectedTimeout'   => 1500,
			],
			[
				'configuredTimeout' => 0,
				'expectedTimeout'   => 0,
			],
			[
				'configuredTimeout' => -1,
				'expectedTimeout'   => 0,
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function testCanCheckIfSocketUsesConnection() : void
	{
		$unixDomainConnection = new UnixDomainSocket( $this->getUnixDomainSocket() );
		$networkConnection    = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );

		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$unixDomainSocket = new Socket(
			SocketId::new(),
			$unixDomainConnection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$networkSocket = new Socket(
			SocketId::new(),
			$networkConnection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		self::assertTrue( $unixDomainSocket->usesConnection( $unixDomainConnection ) );
		self::assertFalse( $unixDomainSocket->usesConnection( $networkConnection ) );

		self::assertTrue( $networkSocket->usesConnection( $networkConnection ) );
		self::assertFalse( $networkSocket->usesConnection( $unixDomainConnection ) );
	}
}
