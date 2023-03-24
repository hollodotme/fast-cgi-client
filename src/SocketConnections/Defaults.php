<?php declare(strict_types = 1);

namespace hollodotme\FastCGI\SocketConnections;

/**
 * Class Defaults
 * @package hollodotme\FastCGI\SocketConnections
 */
abstract class Defaults
{
	public const CONNECT_TIMEOUT    = 5000;

	public const READ_WRITE_TIMEOUT = 5000;
}
