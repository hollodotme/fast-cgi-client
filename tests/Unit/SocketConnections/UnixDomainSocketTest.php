<?php

declare(strict_types=1);

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

final class UnixDomainSocketTest extends TestCase
{
    use SocketDataProviding;

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testImplementsConnectionInterface(): void
    {
        $connection = new UnixDomainSocket($this->getUnixDomainSocket());

        self::assertInstanceOf(ConfiguresSocketConnection::class, $connection);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetDefaultValues(): void
    {
        $connection = new UnixDomainSocket($this->getUnixDomainSocket());

        $expectedSocketAddress = sprintf('unix://%s', $this->getUnixDomainSocket());

        self::assertSame($expectedSocketAddress, $connection->getSocketAddress());
        self::assertSame(Defaults::CONNECT_TIMEOUT, $connection->getConnectTimeout());
        self::assertSame(Defaults::READ_WRITE_TIMEOUT, $connection->getReadWriteTimeout());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetSetValues(): void
    {
        $connection = new UnixDomainSocket($this->getUnixDomainSocket(), 2000, 3000);

        $expectedSocketAddress = sprintf('unix://%s', $this->getUnixDomainSocket());

        self::assertSame($expectedSocketAddress, $connection->getSocketAddress());
        self::assertSame(2000, $connection->getConnectTimeout());
        self::assertSame(3000, $connection->getReadWriteTimeout());
    }

    /**
     * @param ConfiguresSocketConnection $connection
     * @param bool                       $expectedEqual
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @dataProvider connectionProvider
     */
    public function testCanCheckForEquality(ConfiguresSocketConnection $connection, bool $expectedEqual): void
    {
        $unixDomainConnection = new UnixDomainSocket($this->getUnixDomainSocket());

        self::assertSame($expectedEqual, $unixDomainConnection->equals($connection));
        self::assertSame($expectedEqual, $connection->equals($unixDomainConnection));
    }

    /**
     * @return array<array<string, ConfiguresSocketConnection|bool>>
     */
    public function connectionProvider(): array
    {
        return [
            [
                'connection'    => new UnixDomainSocket($this->getUnixDomainSocket()),
                'expectedEqual' => true,
            ],
            [
                'connection'    => new UnixDomainSocket(
                    $this->getUnixDomainSocket(),
                    1000,
                    Defaults::READ_WRITE_TIMEOUT
                ),
                'expectedEqual' => false,
            ],
            [
                'connection'    => new UnixDomainSocket(
                    $this->getUnixDomainSocket(),
                    Defaults::CONNECT_TIMEOUT,
                    1000
                ),
                'expectedEqual' => false,
            ],
            [
                'connection'    => new NetworkSocket($this->getNetworkSocketHost(), $this->getNetworkSocketPort()),
                'expectedEqual' => false,
            ],
        ];
    }
}
