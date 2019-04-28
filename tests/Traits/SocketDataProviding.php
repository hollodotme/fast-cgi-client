<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Traits;

trait SocketDataProviding
{
	final protected function getNetworkSocketHost() : string
	{
		return (string)$_ENV['network-socket-host'];
	}

	final protected function getNetworkSocketPort() : int
	{
		return (int)$_ENV['network-socket-port'];
	}

	final protected function getUnixDomainSocket() : string
	{
		return (string)$_ENV['unix-domain-socket'];
	}

	final protected function getRestrictedUnixDomainSocket() : string
	{
		return (string)$_ENV['restricted-unix-domain-socket'];
	}

	final protected function getNonExistingUnixDomainSocket() : string
	{
		return (string)$_ENV['non-existing-unix-domain-socket'];
	}

	final protected function getInvalidUnixDomainSocket() : string
	{
		return (string)$_ENV['invalid-unix-domain-socket'];
	}
}