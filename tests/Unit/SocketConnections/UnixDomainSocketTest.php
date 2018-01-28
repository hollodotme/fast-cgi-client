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

namespace hollodotme\FastCGI\Tests\Unit\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\SocketConnections\Defaults;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use PHPUnit\Framework\TestCase;

class UnixDomainSocketTest extends TestCase
{
	/**
	 * @throws \PHPUnit\Framework\Exception
	 */
	public function testImplementsConnectionInterface() : void
	{
		$connection = new UnixDomainSocket( '/var/run/php/php7.1-fpm.sock' );

		$this->assertInstanceOf( ConfiguresSocketConnection::class, $connection );
	}

	public function testCanGetDefaultValues() : void
	{
		$connection = new UnixDomainSocket( '/var/run/php/php7.1-fpm.sock' );

		$this->assertSame( 'unix:///var/run/php/php7.1-fpm.sock', $connection->getSocketAddress() );
		$this->assertSame( Defaults::CONNECT_TIMEOUT, $connection->getConnectTimeout() );
		$this->assertSame( Defaults::READ_WRITE_TIMEOUT, $connection->getReadWriteTimeout() );
	}

	public function testCanGetSetValues() : void
	{
		$connection = new UnixDomainSocket( '/var/run/php/php7.1-fpm.sock', 2000, 3000 );

		$this->assertSame( 'unix:///var/run/php/php7.1-fpm.sock', $connection->getSocketAddress() );
		$this->assertSame( 2000, $connection->getConnectTimeout() );
		$this->assertSame( 3000, $connection->getReadWriteTimeout() );
	}
}
