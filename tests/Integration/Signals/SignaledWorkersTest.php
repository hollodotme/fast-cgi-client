<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\Signals;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\Polling;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\TestCase;
use Throwable;
use function dirname;
use function exec;
use function file_exists;
use function preg_match;
use function sort;
use function sprintf;

final class SignaledWorkersTest extends TestCase
{
	use SocketDataProviding;
	use Polling;

	/** @var array<int> */
	private array $success = [];

	/** @var array<Throwable> */
	private array $failures = [];

	protected function setUp() : void
	{
		$this->success  = [];
		$this->failures = [];
	}

	/**
	 * @param int $signal
	 *
	 * @throws Throwable
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnNetworkSocket( int $signal ) : void
	{
		$this->assertFailureCallbackGetsCalledIfOneProcessGetsInterrupted(
			$this->getNetworkSocketConnection(),
			$signal
		);
	}

	/**
	 * @param int $signal
	 *
	 * @throws Throwable
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnUnixDomainSocket( int $signal ) : void
	{
		$this->assertFailureCallbackGetsCalledIfOneProcessGetsInterrupted(
			$this->getUnixDomainSocketConnection(),
			$signal
		);
	}

	/**
	 * @param int $signal
	 *
	 * @throws Throwable
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnNetworkSocket( int $signal ) : void
	{
		$this->assertFailureCallbackGetsCalledIfAllProcessesGetInterrupted(
			$this->getNetworkSocketConnection(),
			$signal
		);
	}

	/**
	 * @param int $signal
	 *
	 * @throws Throwable
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnUnixDomainSocket( int $signal ) : void
	{
		$this->assertFailureCallbackGetsCalledIfAllProcessesGetInterrupted(
			$this->getUnixDomainSocketConnection(),
			$signal
		);
	}

	/**
	 * @return array<array<string, int>>
	 */
	public function signalProvider() : array
	{
		return [
			[
				# SIGHUP
				'signal' => 1,
			],
			[
				# SIGINT
				'signal' => 2,
			],
			[
				# SIGKILL
				'signal' => 9,
			],
			[
				# SIGTERM
				'signal' => 15,
			],
		];
	}

	/**
	 * @throws Throwable
	 */
	public function testBrokenSocketGetsRemovedIfWritingRequestFailed() : void
	{
		$client     = new Client();
		$request    = new PostRequest( $this->getWorkerPath( 'pidWorker.php' ) );
		$connection = $this->getUnixDomainSocketConnection();

		$socketId1 = $client->sendAsyncRequest( $connection, $request );
		$pid1      = (int)$client->readResponse( $socketId1 )->getBody();

		# This request should use the same socket and same PHP-FPM child process
		$socketId2 = $client->sendAsyncRequest( $connection, $request );
		$pid2      = (int)$client->readResponse( $socketId2 )->getBody();

		self::assertSame( $socketId1, $socketId2 );
		self::assertSame( $pid1, $pid2 );

		$this->killProcess( $pid2, 9 );
		$this->waitUntil( static fn() : bool => !file_exists( '/proc/' . $pid2 ) );

		try
		{
			$socketId3 = $client->sendAsyncRequest( $connection, $request );
		}
		catch ( WriteFailedException $e )
		{
			# Writing to the socket of the killed process failed and the socket was removed,
			# so this request uses a new socket
			$socketId3 = $client->sendAsyncRequest( $connection, $request );
		}

		$pid3 = (int)$client->readResponse( $socketId3 )->getBody();

		self::assertNotSame( $socketId2, $socketId3 );
		self::assertNotSame( $pid2, $pid3 );
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param int                        $signal
	 *
	 * @throws Throwable
	 */
	private function assertFailureCallbackGetsCalledIfOneProcessGetsInterrupted(
		ConfiguresSocketConnection $connection,
		int $signal
	) : void
	{
		$client = new Client();

		$client->sendAsyncRequest( $connection, $this->getRequest( 1 ) );
		$client->sendAsyncRequest( $connection, $this->getInterruptedRequest( 2, $signal ) );
		$client->sendAsyncRequest( $connection, $this->getRequest( 3 ) );

		$client->waitForResponses();

		sort( $this->success );

		self::assertSame( [1, 3], $this->success );
		self::assertCount( 1, $this->failures );
		self::assertContainsOnlyInstancesOf( ReadFailedException::class, $this->failures );
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param int                        $signal
	 *
	 * @throws Throwable
	 */
	private function assertFailureCallbackGetsCalledIfAllProcessesGetInterrupted(
		ConfiguresSocketConnection $connection,
		int $signal
	) : void
	{
		$client = new Client();

		for ( $i = 1; $i <= 3; $i++ )
		{
			$client->sendAsyncRequest( $connection, $this->getInterruptedRequest( $i, $signal ) );
		}

		$client->waitForResponses();

		self::assertSame( [], $this->success );
		self::assertCount( 3, $this->failures );
		self::assertContainsOnlyInstancesOf( ReadFailedException::class, $this->failures );
	}

	private function getRequest( int $testKey ) : PostRequest
	{
		$request = new PostRequest(
			$this->getWorkerPath( 'worker.php' ),
			new UrlEncodedFormData( ['test-key' => $testKey] )
		);

		$this->addCallbacks( $request );

		return $request;
	}

	/**
	 * Returns a request to a worker that announces its process ID before it does its work.
	 * As soon as the client receives the process ID, it sends the signal to this process.
	 * This way exactly the process handling this request gets interrupted while it handles the request,
	 * regardless of how the requests are distributed across the pool and how fast the machine is.
	 */
	private function getInterruptedRequest( int $testKey, int $signal ) : PostRequest
	{
		$request = new PostRequest(
			$this->getWorkerPath( 'interruptibleWorker.php' ),
			new UrlEncodedFormData( ['test-key' => $testKey] )
		);

		$this->addCallbacks( $request );

		$request->addPassThroughCallbacks(
			function ( string $outputBuffer ) use ( $signal ) : void
			{
				if ( 1 === preg_match( '#PID:(\d+)#', $outputBuffer, $matches ) )
				{
					$this->killProcess( (int)$matches[1], $signal );
				}
			}
		);

		return $request;
	}

	private function addCallbacks( PostRequest $request ) : void
	{
		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response ) : void
			{
				$this->success[] = (int)$response->getBody();
			}
		);

		$request->addFailureCallbacks(
			function ( Throwable $e ) : void
			{
				$this->failures[] = $e;
			}
		);
	}

	private function killProcess( int $pid, int $signal ) : void
	{
		exec( sprintf( 'kill -%d %d', $signal, $pid ) );
	}

	private function getWorkerPath( string $workerFile ) : string
	{
		return sprintf( '%s/Workers/%s', dirname( __DIR__ ), $workerFile );
	}

	private function getNetworkSocketConnection() : NetworkSocket
	{
		return new NetworkSocket(
			$this->getNetworkSocketHost(),
			$this->getNetworkSocketPort()
		);
	}

	private function getUnixDomainSocketConnection() : UnixDomainSocket
	{
		return new UnixDomainSocket( $this->getUnixDomainSocket() );
	}
}
