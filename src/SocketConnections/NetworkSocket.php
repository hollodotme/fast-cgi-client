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

namespace hollodotme\FastCGI\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

class NetworkSocket implements ConfiguresSocketConnection
{
	/** @var string */
	private $host;

	/** @var int */
	private $port;

	/** @var int */
	private $connectTimeout;

	/** @var int */
	private $readWriteTimeout;

	public function __construct(
		string $host,
		int $port,
		int $connectTimeout = Defaults::CONNECT_TIMEOUT,
		int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT
	)
	{
		$this->host             = $host;
		$this->port             = $port;
		$this->connectTimeout   = $connectTimeout;
		$this->readWriteTimeout = $readWriteTimeout;
	}

	public function getSocketAddress() : string
	{
		return sprintf( 'tcp://%s:%d', $this->host, $this->port );
	}

	public function getConnectTimeout() : int
	{
		return $this->connectTimeout;
	}

	public function getReadWriteTimeout() : int
	{
		return $this->readWriteTimeout;
	}

	public function equals( ConfiguresSocketConnection $other ) : bool
	{
		/** @noinspection TypeUnsafeComparisonInspection */
		/** @noinspection PhpNonStrictObjectEqualityInspection */
		return $this == $other;
	}
}
