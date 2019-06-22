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
use function http_build_query;
use function parse_ini_file;
use function range;
use function sort;

final class AsyncRequestsTest extends TestCase
{
	use SocketDataProviding;

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testAsyncRequestsWillRespondToCallbackIfRequestsExceedPhpFpmMaxChildrenSettingOnNetworkSocket(
	) : void
	{
		$maxChildren = $this->getMaxChildrenSettingFromNetworkSocket();
		$limit       = $maxChildren + 5;

		$this->assertTrue( $limit > 5 );

		$client          = new Client();
		$results         = [];
		$expectedResults = range( 0, $limit - 1 );

		$request = new PostRequest( __DIR__ . '/Workers/worker.php', '' );
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$results )
			{
				$results[] = (int)$response->getBody();
			}
		);

		for ( $i = 0; $i < $limit; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getNetworkSocketConnection(), $request );
		}

		$client->waitForResponses();

		sort( $results );

		$this->assertSame( $expectedResults, $results );
	}

	private function getMaxChildrenSettingFromNetworkSocket() : int
	{
		$iniSettings = parse_ini_file(
			__DIR__ . '/../../.docker/php/network-socket.pool.conf',
			true
		);

		return (int)$iniSettings['network']['pm.max_children'];
	}

	private function getNetworkSocketConnection() : NetworkSocket
	{
		return new NetworkSocket(
			$this->getNetworkSocketHost(),
			$this->getNetworkSocketPort()
		);
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testAsyncRequestsWillRespondToCallbackIfRequestsExceedPhpFpmMaxChildrenSettingOnUnixDomainSocket(
	) : void
	{
		$maxChildren = $this->getMaxChildrenSettingFromUnixDomainSocket();
		$limit       = $maxChildren + 5;

		$this->assertTrue( $limit > 5 );

		$client          = new Client();
		$results         = [];
		$expectedResults = range( 0, $limit - 1 );

		$request = new PostRequest( __DIR__ . '/Workers/worker.php', '' );
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$results )
			{
				$results[] = (int)$response->getBody();
			}
		);

		for ( $i = 0; $i < $limit; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getUnixDomainSocketConnection(), $request );
		}

		$client->waitForResponses();

		sort( $results );

		$this->assertSame( $expectedResults, $results );
	}

	private function getMaxChildrenSettingFromUnixDomainSocket() : int
	{
		$iniSettings = parse_ini_file(
			__DIR__ . '/../../.docker/php/unix-domain-socket.pool.conf',
			true
		);

		return (int)$iniSettings['uds']['pm.max_children'];
	}

	private function getUnixDomainSocketConnection() : UnixDomainSocket
	{
		return new UnixDomainSocket( $this->getUnixDomainSocket() );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReadResponsesOfAsyncRequestsIfRequestsExceedPhpFpmMaxChildrenSettingOnNetworkSocket() : void
	{
		$maxChildren = $this->getMaxChildrenSettingFromNetworkSocket();
		$limit       = $maxChildren + 5;

		$this->assertTrue( $limit > 5 );

		$client          = new Client();
		$results         = [];
		$expectedResults = range( 0, $limit - 1 );

		$request = new PostRequest( __DIR__ . '/Workers/worker.php', '' );

		for ( $i = 0; $i < $limit; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getNetworkSocketConnection(), $request );
		}

		while ( $client->hasUnhandledResponses() )
		{
			foreach ( $client->readReadyResponses() as $response )
			{
				$results[] = (int)$response->getBody();
			}
		}

		sort( $results );

		$this->assertSame( $expectedResults, $results );
	}

	/**
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanReadResponsesOfAsyncRequestsIfRequestsExceedPhpFpmMaxChildrenSettingOnUnixDomainSocket(
	) : void
	{
		$maxChildren = $this->getMaxChildrenSettingFromUnixDomainSocket();
		$limit       = $maxChildren + 5;

		$this->assertTrue( $limit > 5 );

		$client          = new Client();
		$results         = [];
		$expectedResults = range( 0, $limit - 1 );

		$request = new PostRequest( __DIR__ . '/Workers/worker.php', '' );
		$request->addResponseCallbacks(
			static function ( ProvidesResponseData $response ) use ( &$results )
			{
				$results[] = (int)$response->getBody();
			}
		);

		for ( $i = 0; $i < $limit; $i++ )
		{
			$request->setContent( http_build_query( ['test-key' => $i] ) );

			$client->sendAsyncRequest( $this->getUnixDomainSocketConnection(), $request );
		}

		while ( $client->hasUnhandledResponses() )
		{
			foreach ( $client->readReadyResponses() as $response )
			{
				$results[] = (int)$response->getBody();
			}
		}

		sort( $results );

		$this->assertSame( $expectedResults, $results );
	}
}
