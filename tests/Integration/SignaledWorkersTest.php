<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;
use function escapeshellarg;
use function exec;
use function http_build_query;
use function implode;
use function preg_match;
use function shell_exec;
use function sleep;
use function sprintf;

final class SignaledWorkersTest extends TestCase
{
	use SocketDataProviding;

	/**
	 * @param int $signal
	 *
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 *
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnNetworkSocket( int $signal ) : void
	{
		$client   = new Client();
		$request  = new PostRequest( __DIR__ . '/Workers/worker.php', '' );
		$success  = [];
		$failures = [];

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$success )
			{
				$success[] = (int)$response->getBody();
			}
		);

		$request->addFailureCallbacks(
			static function ( Throwable $e ) use ( &$failures )
			{
				$failures[] = $e;
			}
		);

		for ( $i = 0; $i < 3; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getNetworkSocketConnection(), $request );
		}

		$pids = $this->getPoolWorkerPIDs( 'pool network' );

		$this->killPoolWorker( (int)$pids[0], $signal );

		$client->waitForResponses();

		$this->assertCount( 2, $success );
		$this->assertCount( 1, $failures );
		$this->assertContainsOnlyInstancesOf( ReadFailedException::class, $failures );

		sleep( 1 );
	}

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

	private function getNetworkSocketConnection() : NetworkSocket
	{
		return new NetworkSocket(
			$this->getNetworkSocketHost(),
			$this->getNetworkSocketPort()
		);
	}

	private function getPoolWorkerPIDs( string $poolName ) : array
	{
		$command = sprintf(
			'ps -o pid,args | grep %s | grep -v "grep"',
			escapeshellarg( $poolName )
		);
		$list    = shell_exec( $command );

		return array_map(
			static function ( string $item )
			{
				preg_match( '#^(\d+)\s.+$#', trim( $item ), $matches );

				return (int)$matches[1];
			},
			explode( "\n", trim( $list ) )
		);
	}

	private function killPoolWorker( int $PID, int $signal ) : void
	{
		$command = sprintf( 'kill -%d %d', $signal, $PID );
		exec( $command );
	}

	/**
	 * @param int $signal
	 *
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnUnixDomainSocket( int $signal ) : void
	{
		$client   = new Client();
		$request  = new PostRequest( __DIR__ . '/Workers/worker.php', '' );
		$success  = [];
		$failures = [];

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$success )
			{
				$success[] = (int)$response->getBody();
			}
		);

		$request->addFailureCallbacks(
			static function ( Throwable $e ) use ( &$failures )
			{
				$failures[] = $e;
			}
		);

		for ( $i = 0; $i < 3; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getUnixDomainSocketConnection(), $request );
		}

		$pids = $this->getPoolWorkerPIDs( 'pool uds' );

		$this->killPoolWorker( (int)$pids[0], $signal );

		$client->waitForResponses();

		$this->assertCount( 2, $success );
		$this->assertCount( 1, $failures );
		$this->assertContainsOnlyInstancesOf( ReadFailedException::class, $failures );

		sleep( 1 );
	}

	private function getUnixDomainSocketConnection() : UnixDomainSocket
	{
		return new UnixDomainSocket( $this->getUnixDomainSocket() );
	}

	/**
	 * @param int $signal
	 *
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws \InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 *
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnNetworkSocket( int $signal ) : void
	{
		$client   = new Client();
		$request  = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', '' );
		$success  = [];
		$failures = [];

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$success )
			{
				$success[] = (int)$response->getBody();
			}
		);

		$request->addFailureCallbacks(
			static function ( Throwable $e ) use ( &$failures )
			{
				$failures[] = $e;
			}
		);

		for ( $i = 0; $i < 3; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i, 'sleep' => 1] ) );

			$client->sendAsyncRequest( $this->getNetworkSocketConnection(), $request );
		}

		$this->killPhpFpmChildProcesses( 'pool network', $signal );

		$client->waitForResponses();

		$this->assertCount( 0, $success );
		$this->assertCount( 3, $failures );
		$this->assertContainsOnlyInstancesOf( ReadFailedException::class, $failures );

		sleep( 1 );
	}

	private function killPhpFpmChildProcesses( string $poolName, int $signal ) : void
	{
		$PIDs = $this->getPoolWorkerPIDs( $poolName );
		$this->killPoolWorkers( $PIDs, $signal );
	}

	private function killPoolWorkers( array $PIDs, int $signal ) : void
	{
		$command = sprintf( 'kill -%d %s', $signal, implode( ' ', $PIDs ) );
		exec( $command );
	}

	/**
	 * @param int $signal
	 *
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws \InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 *
	 * @dataProvider signalProvider
	 */
	public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnUnixDomainSocket( int $signal ) : void
	{
		$client   = new Client();
		$request  = new PostRequest( __DIR__ . '/Workers/sleepWorker.php', '' );
		$success  = [];
		$failures = [];

		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$success )
			{
				$success[] = (int)$response->getBody();
			}
		);

		$request->addFailureCallbacks(
			static function ( Throwable $e ) use ( &$failures )
			{
				$failures[] = $e;
			}
		);

		for ( $i = 0; $i < 3; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i, 'sleep' => 1] ) );

			$client->sendAsyncRequest( $this->getUnixDomainSocketConnection(), $request );
		}

		$this->killPhpFpmChildProcesses( 'pool uds', $signal );

		$client->waitForResponses();

		$this->assertCount( 0, $success );
		$this->assertCount( 3, $failures );
		$this->assertContainsOnlyInstancesOf( ReadFailedException::class, $failures );

		sleep( 1 );
	}
}
