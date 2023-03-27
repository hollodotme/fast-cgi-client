<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\SocketConnections;

use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

class NetworkSocket implements ConfiguresSocketConnection
{
    private string $host;

    private int $port;

    private int $connectTimeout;

    private int $readWriteTimeout;

    public function __construct(
        string $host,
        int $port,
        int $connectTimeout = Defaults::CONNECT_TIMEOUT,
        int $readWriteTimeout = Defaults::READ_WRITE_TIMEOUT
    ) {
        $this->host             = $host;
        $this->port             = $port;
        $this->connectTimeout   = $connectTimeout;
        $this->readWriteTimeout = $readWriteTimeout;
    }

    public function getSocketAddress(): string
    {
        return sprintf('tcp://%s:%d', $this->host, $this->port);
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
