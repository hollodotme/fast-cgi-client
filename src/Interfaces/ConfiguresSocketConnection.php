<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

/**
 * Interface ConfiguresSocketConnection
 * @package hollodotme\FastCGI\Interfaces
 */
interface ConfiguresSocketConnection
{
	public function getSocketAddress() : string;

	public function getConnectTimeout() : int;

	public function getReadWriteTimeout() : int;

	public function equals( ConfiguresSocketConnection $other ) : bool;
}
