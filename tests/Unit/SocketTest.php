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

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Socket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use PHPUnit\Framework\TestCase;

/**
 * Class SocketTest
 * @package hollodotme\FastCGI\Tests\Unit
 */
final class SocketTest extends TestCase
{
	/**
	 * @throws \Exception
	 */
	public function testCanGetIdAfterConstruction() : void
	{
		$socket = $this->getSocket();

		$this->assertGreaterThanOrEqual( 1, $socket->getId() );
		$this->assertLessThanOrEqual( (1 << 16) - 1, $socket->getId() );
	}

	/**
	 * @return Socket
	 * @throws \Exception
	 */
	private function getSocket() : Socket
	{
		$nameValuePairEncoder = new NameValuePairEncoder();
		$packetEncoder        = new PacketEncoder();
		$connection           = new UnixDomainSocket( '/var/run/php-uds.sock' );

		return new Socket( $connection, $packetEncoder, $nameValuePairEncoder );
	}

	/**
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanSendRequestAndFetchResponse() : void
	{
		$socket  = $this->getSocket();
		$data    = ['test-key' => 'unit'];
		$request = new PostRequest(
			\dirname( __DIR__ ) . '/Integration/Workers/worker.php',
			http_build_query( $data )
		);

		$socket->sendRequest( $request );

		$response = $socket->fetchResponse();

		$this->assertSame( 'unit', $response->getBody() );

		$response2 = $socket->fetchResponse();

		$this->assertSame( $response, $response2 );
	}

	/**
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\AssertionFailedError
	 * @throws \PHPUnit\Framework\Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanCollectResource() : void
	{
		$resources = [];
		$socket    = $this->getSocket();
		$data      = ['test-key' => 'unit'];
		$request   = new PostRequest(
			\dirname( __DIR__ ) . '/Integration/Workers/worker.php',
			http_build_query( $data )
		);

		$socket->collectResource( $resources );

		$this->assertEmpty( $resources );

		$socket->sendRequest( $request );

		$socket->collectResource( $resources );

		$this->assertInternalType( 'resource', $resources[ $socket->getId() ] );
	}

	/**
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanNotifyResponseCallback() : void
	{
		$socket  = $this->getSocket();
		$data    = ['test-key' => 'unit'];
		$request = new PostRequest(
			\dirname( __DIR__ ) . '/Integration/Workers/worker.php',
			http_build_query( $data )
		);
		$request->addResponseCallbacks(
			function ( ProvidesResponseData $response )
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
	 * @throws \Exception
	 * @throws \PHPUnit\Framework\Exception
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 */
	public function testCanNotifyFailureCallback() : void
	{
		$socket  = $this->getSocket();
		$data    = ['test-key' => 'unit'];
		$request = new PostRequest(
			\dirname( __DIR__ ) . '/Integration/Workers/worker.php',
			http_build_query( $data )
		);
		$request->addFailureCallbacks(
			function ( \Throwable $throwable )
			{
				echo $throwable->getMessage();
			}
		);
		$throwable = new \RuntimeException( 'Something went wrong.' );

		$socket->sendRequest( $request );
		$socket->notifyFailureCallbacks( $throwable );

		$this->expectOutputString( 'Something went wrong.' );
	}
}
