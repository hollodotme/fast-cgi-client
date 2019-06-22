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

namespace hollodotme\FastCGI\Tests\Unit\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use function sprintf;

final class NetworkSocketTest extends TestCase
{
	use SocketDataProviding;

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function testImplementsConnectionInterface() : void
	{
		$connection = new NetworkSocket( 'localhost', 9000 );

		$this->assertInstanceOf( ConfiguresSocketConnection::class, $connection );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetDefaultValues() : void
	{
		$connection = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );

		$expectedSocketAddress = sprintf( 'tcp://%s:%d', $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );

		$this->assertSame( $expectedSocketAddress, $connection->getSocketAddress() );
		$this->assertSame( Defaults::CONNECT_TIMEOUT, $connection->getConnectTimeout() );
		$this->assertSame( Defaults::READ_WRITE_TIMEOUT, $connection->getReadWriteTimeout() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetSetValues() : void
	{
		$connection = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort(), 2000, 3000 );

		$expectedSocketAddress = sprintf( 'tcp://%s:%d', $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );

		$this->assertSame( $expectedSocketAddress, $connection->getSocketAddress() );
		$this->assertSame( 2000, $connection->getConnectTimeout() );
		$this->assertSame( 3000, $connection->getReadWriteTimeout() );
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param bool                       $expectedEqual
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider connectionProvider
	 */
	public function testCanCheckForEquality( ConfiguresSocketConnection $connection, bool $expectedEqual ) : void
	{
		$networkConnection = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );

		$this->assertSame( $expectedEqual, $networkConnection->equals( $connection ) );
		$this->assertSame( $expectedEqual, $connection->equals( $networkConnection ) );
	}

	public function connectionProvider() : array
	{
		return [
			[
				'connection'    => new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() ),
				'expectedEqual' => true,
			],
			[
				'connection'    => new NetworkSocket(
					$this->getNetworkSocketHost(),
					$this->getNetworkSocketPort(),
					1000,
					Defaults::READ_WRITE_TIMEOUT
				),
				'expectedEqual' => false,
			],
			[
				'connection'    => new NetworkSocket(
					$this->getNetworkSocketHost(),
					$this->getNetworkSocketPort(),
					Defaults::CONNECT_TIMEOUT,
					1000
				),
				'expectedEqual' => false,
			],
			[
				'connection'    => new UnixDomainSocket( $this->getUnixDomainSocket() ),
				'expectedEqual' => false,
			],
		];
	}
}
