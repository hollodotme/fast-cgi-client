<?php declare(strict_types=1);

namespace hollodotme\FastCGI\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

class NetworkSocket implements ConfiguresSocketConnection
{
	public function __construct(
		private string $host,
		private int $port,
		private int $connectTimeout = Defaults::CONNECT_TIMEOUT,
		private int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT,
		private int $streamSelectTimeout = Defaults::STREAM_SELECT_TIMEOUT
	)
	{
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

	public function getStreamSelectTimeout() : int
	{
		return $this->streamSelectTimeout;
	}

	public function equals( ConfiguresSocketConnection $other ) : bool
	{
		/** @noinspection TypeUnsafeComparisonInspection */
		/** @noinspection PhpNonStrictObjectEqualityInspection */
		return $this == $other;
	}
}
