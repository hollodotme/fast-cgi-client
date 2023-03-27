<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

/**
 * Class UnixDomainSocket
 * @package hollodotme\FastCGI\SocketConnections
 */
class UnixDomainSocket implements ConfiguresSocketConnection
{
    private string $socketPath;

    private int $connectTimeout;

    private int $readWriteTimeout;

    public function __construct(
        string $socketPath,
        int $connectTimeout = Defaults::CONNECT_TIMEOUT,
        int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT
    ) {
        $this->socketPath       = $socketPath;
        $this->connectTimeout   = $connectTimeout;
        $this->readWriteTimeout = $readWriteTimeout;
    }

    public function getSocketAddress(): string
    {
        return 'unix://' . $this->socketPath;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getReadWriteTimeout(): int
    {
        return $this->readWriteTimeout;
    }

    public function equals(ConfiguresSocketConnection $other): bool
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        /** @noinspection PhpNonStrictObjectEqualityInspection */
        return $this == $other;
    }
}
