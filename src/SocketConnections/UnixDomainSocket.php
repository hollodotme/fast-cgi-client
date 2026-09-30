<?php declare(strict_types=1);

namespace hollodotme\FastCGI\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

/**
 * Class UnixDomainSocket
 * @package hollodotme\FastCGI\SocketConnections
 */
class UnixDomainSocket implements ConfiguresSocketConnection
{
	public function __construct(
		private string $socketPath,
		private int $connectTimeout = Defaults::CONNECT_TIMEOUT,
		private int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT,
		private int $streamSelectTimeout = Defaults::STREAM_SELECT_TIMEOUT
	)
	{
	}

	public function getSocketAddress() : string
	{
		return 'unix://' . $this->socketPath;
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
