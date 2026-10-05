<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\Signals;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use function dirname;
use function exec;
use function fclose;
use function file_exists;
use function microtime;
use function sprintf;
use function stream_socket_pair;
use function stream_socket_shutdown;
use function usleep;
use const PHP_VERSION_ID;
use const STREAM_PF_UNIX;
use const STREAM_SHUT_WR;
use const STREAM_SOCK_STREAM;

final class RetryRequestsTest extends TestCase
{
	use SocketDataProviding;

	/** @var array<resource> Peers of the broken sockets, kept open so the broken sockets do not look closed */
	private array $peers = [];

	protected function tearDown() : void
	{
		foreach ( $this->peers as $peer )
		{
			fclose( $peer );
		}

		$this->peers = [];
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 *
	 * @throws Throwable
	 * @dataProvider connectionProvider
	 */
	public function testRequestSucceedsAfterWorkerOfIdleSocketWasKilled( ConfiguresSocketConnection $connection ) : void
	{
		$client  = new Client();
		$request = $this->getPidRequest();

		$pid = (int)$client->tryRequest( $connection, $request )->getBody();

		self::assertGreaterThan( 0, $pid );

		$this->killProcess( $pid );

		$newPid = (int)$client->tryRequest( $connection, $request )->getBody();

		self::assertGreaterThan( 0, $newPid );
		self::assertNotSame( $pid, $newPid );
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 *
	 * @throws Throwable
	 * @dataProvider connectionProvider
	 */
	public function testIdleSocketIsReplacedAfterItsWorkerWasKilled( ConfiguresSocketConnection $connection ) : void
	{
		$client  = new Client();
		$request = $this->getPidRequest();

		$socketId = $client->sendAsyncRequest( $connection, $request );
		$pid      = (int)$client->readResponse( $socketId )->getBody();

		$this->killProcess( $pid );

		$newSocketId = $client->sendAsyncRequest( $connection, $request );
		$newPid      = (int)$client->readResponse( $newSocketId )->getBody();

		self::assertNotSame( $socketId, $newSocketId );
		self::assertNotSame( $pid, $newPid );
		self::assertCount( 1, $this->getSockets( $client ) );
	}

	/**
	 * @return array<string, array<int, ConfiguresSocketConnection>>
	 */
	public function connectionProvider() : array
	{
		return [
			'network socket'     => [$this->getNetworkSocketConnection()],
			'unix domain socket' => [$this->getUnixDomainSocketConnection()],
		];
	}

	/**
	 * @throws Throwable
	 */
	public function testSendingRequestFailsIfWritingToIdleSocketFails() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$this->createBrokenIdleSockets( $client, $connection, 1 );

		$this->expectException( WriteFailedException::class );

		/** @noinspection UnusedFunctionResultInspection */
		$client->sendAsyncRequest( $connection, $request );
	}

	/**
	 * @throws Throwable
	 */
	public function testAsyncRequestIsRetriedOnNewSocketIfWritingToIdleSocketFails() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$brokenSocketIds = $this->createBrokenIdleSockets( $client, $connection, 1 );

		$socketId = $client->tryAsyncRequest( $connection, $request );

		self::assertNotContains( $socketId, $brokenSocketIds );
		self::assertGreaterThan( 0, (int)$client->readResponse( $socketId )->getBody() );
	}

	/**
	 * @throws Throwable
	 */
	public function testRequestIsRetriedOnNewSocketIfWritingToIdleSocketFails() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$this->createBrokenIdleSockets( $client, $connection, 1 );

		$response = $client->tryRequest( $connection, $request );

		self::assertGreaterThan( 0, (int)$response->getBody() );
	}

	/**
	 * @throws Throwable
	 */
	public function testRequestIsRetriedUntilSocketIsUsable() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$brokenSocketIds = $this->createBrokenIdleSockets( $client, $connection, 3 );

		# 3 broken sockets need 4 tries
		$socketId = $client->tryAsyncRequest( $connection, $request, 4 );

		self::assertNotContains( $socketId, $brokenSocketIds );
		self::assertGreaterThan( 0, (int)$client->readResponse( $socketId )->getBody() );
	}

	/**
	 * @throws Throwable
	 */
	public function testLastExceptionIsThrownIfAllTriesFail() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$this->createBrokenIdleSockets( $client, $connection, 3 );

		try
		{
			$client->tryRequest( $connection, $request, 3 );

			self::fail( 'Expected WriteFailedException to be thrown.' );
		}
		catch ( WriteFailedException $e )
		{
			# All broken sockets were removed, so the next request uses a new one
			self::assertCount( 0, $this->getSockets( $client ) );
			self::assertGreaterThan( 0, (int)$client->sendRequest( $connection, $request )->getBody() );
		}
	}

	/**
	 * @throws Throwable
	 */
	public function testRequestIsNotRetriedIfMaximumNumberOfTriesIsOne() : void
	{
		$client     = new Client();
		$connection = $this->getUnixDomainSocketConnection();
		$request    = $this->getPidRequest();

		$this->createBrokenIdleSockets( $client, $connection, 1 );

		$this->expectException( WriteFailedException::class );

		/** @noinspection UnusedFunctionResultInspection */
		$client->tryAsyncRequest( $connection, $request, 1 );
	}

	/**
	 * @param int $maxTries
	 *
	 * @throws Throwable
	 * @dataProvider invalidMaxTriesProvider
	 */
	public function testTryRequestThrowsExceptionForInvalidMaximumNumberOfTries( int $maxTries ) : void
	{
		$client = new Client();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum number of tries must be at least 1, got: ' . $maxTries );

		/** @noinspection UnusedFunctionResultInspection */
		$client->tryRequest( $this->getUnixDomainSocketConnection(), $this->getPidRequest(), $maxTries );
	}

	/**
	 * @param int $maxTries
	 *
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @dataProvider invalidMaxTriesProvider
	 */
	public function testTryAsyncRequestThrowsExceptionForInvalidMaximumNumberOfTries( int $maxTries ) : void
	{
		$client = new Client();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum number of tries must be at least 1, got: ' . $maxTries );

		/** @noinspection UnusedFunctionResultInspection */
		$client->tryAsyncRequest( $this->getUnixDomainSocketConnection(), $this->getPidRequest(), $maxTries );
	}

	/**
	 * @return array<array<string, int>>
	 */
	public function invalidMaxTriesProvider() : array
	{
		return [
			['maxTries' => 0],
			['maxTries' => -1],
		];
	}

	private function getPidRequest() : PostRequest
	{
		return new PostRequest( dirname( __DIR__ ) . '/Workers/pidWorker.php' );
	}

	private function getNetworkSocketConnection() : NetworkSocket
	{
		return new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );
	}

	private function getUnixDomainSocketConnection() : UnixDomainSocket
	{
		return new UnixDomainSocket( $this->getUnixDomainSocket() );
	}

	private function killProcess( int $pid ) : void
	{
		exec( sprintf( 'kill -9 %d', $pid ) );

		$timeout = microtime( true ) + 5;

		while ( file_exists( '/proc/' . $pid ) )
		{
			if ( microtime( true ) > $timeout )
			{
				self::fail( sprintf( 'Process %d did not terminate.', $pid ) );
			}

			usleep( 10000 );
		}
	}

	/**
	 * Creates idle sockets and replaces their streams with ones that are open, but cannot be written to.
	 *
	 * @param Client                     $client
	 * @param ConfiguresSocketConnection $connection
	 * @param int                        $count
	 *
	 * @return array<int> IDs of the broken sockets
	 * @throws Throwable
	 */
	private function createBrokenIdleSockets(
		Client $client,
		ConfiguresSocketConnection $connection,
		int $count
	) : array
	{
		$socketIds = [];

		# Sockets become idle after their response was read, so all requests need to be sent first
		for ( $i = 0; $i < $count; $i++ )
		{
			$socketIds[] = $client->sendAsyncRequest( $connection, $this->getPidRequest() );
		}

		foreach ( $socketIds as $socketId )
		{
			/** @noinspection UnusedFunctionResultInspection */
			$client->readResponse( $socketId );
		}

		foreach ( $this->getSockets( $client ) as $socket )
		{
			$this->getAccessibleProperty( $socket, 'resource' )->setValue( $socket, $this->getUnwritableStream() );
		}

		self::assertCount( $count, $this->getSockets( $client ) );

		return $socketIds;
	}

	/**
	 * @return resource
	 */
	private function getUnwritableStream()
	{
		$pair = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0 );

		if ( false === $pair )
		{
			self::fail( 'Could not create socket pair.' );
		}

		stream_socket_shutdown( $pair[0], STREAM_SHUT_WR );

		$this->peers[] = $pair[1];

		return $pair[0];
	}

	/**
	 * @param Client $client
	 *
	 * @return array<int, Socket>
	 * @throws ReflectionException
	 */
	private function getSockets( Client $client ) : array
	{
		/** @var SocketCollection $collection */
		$collection = $this->getAccessibleProperty( $client, 'sockets' )->getValue( $client );

		/** @var array<int, Socket> $sockets */
		$sockets = $this->getAccessibleProperty( $collection, 'sockets' )->getValue( $collection );

		return $sockets;
	}

	/**
	 * @param object $object
	 * @param string $name
	 *
	 * @return ReflectionProperty
	 * @throws ReflectionException
	 */
	private function getAccessibleProperty( object $object, string $name ) : ReflectionProperty
	{
		$property = (new ReflectionClass( $object ))->getProperty( $name );

		if ( PHP_VERSION_ID < 80100 )
		{
			$property->setAccessible( true );
		}

		return $property;
	}
}
