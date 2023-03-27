<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

use function dirname;
use function fclose;
use function feof;
use function fread;
use function reset;

use const STDIN;

final class SocketCollectionTest extends TestCase
{
    use SocketDataProviding;

    /** @var SocketCollection */
    private $collection;

    protected function setUp(): void
    {
        $this->collection = new SocketCollection();
    }

    protected function tearDown(): void
    {
        $this->collection = new SocketCollection();
    }

    /**
     * @return ConfiguresSocketConnection
     * @throws \Exception
     */
    private function getSocketConnection(): ConfiguresSocketConnection
    {
        return new UnixDomainSocket($this->getUnixDomainSocket());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testCollectResources(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $socketOne = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $socketTwo = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $connectMethod = (new ReflectionClass($socketOne))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socketOne);

        $connectMethod = (new ReflectionClass($socketTwo))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socketTwo);

        $resources = [];
        $socketOne->collectResource($resources);
        $socketTwo->collectResource($resources);

        self::assertSame($resources, $this->collection->collectResources());
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReadFailedException
     * @throws ReflectionException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testGetByResource(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $connectMethod = (new ReflectionClass($socket))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socket);

        $resources = [];
        $socket->collectResource($resources);

        $checkSocket = $this->collection->getByResource(reset($resources));

        self::assertSame($checkSocket, $socket);
    }

    /**
     * @throws AssertionFailedError
     * @throws ReadFailedException
     */
    public function testThrowsExceptionIfSocketCannotBeFoundByResource(): void
    {
        $this->expectException(ReadFailedException::class);
        $this->expectExceptionMessage('Socket not found for resource');

        /** @noinspection UnusedFunctionResultInspection */
        $this->collection->getByResource(STDIN);

        self::fail('Expected a ReadFailedException for not found socket by resource.');
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReadFailedException
     * @throws ReflectionException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testGetSocketIdsByResources(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $socketOne = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $socketTwo = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $connectMethod = (new ReflectionClass($socketOne))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socketOne);

        $connectMethod = (new ReflectionClass($socketTwo))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socketTwo);

        $resources = [];
        $socketOne->collectResource($resources);
        $socketTwo->collectResource($resources);

        $expectedSocketIds = [$socketOne->getId(), $socketTwo->getId()];

        self::assertSame($expectedSocketIds, $this->collection->getSocketIdsByResources($resources));
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function testEmptyCollectionHasNoIdleSocket(): void
    {
        $connection = $this->getSocketConnection();

        self::assertNull($this->collection->getIdleSocket($connection));
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testNewlyAddedSocketIsIdle(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        self::assertSame($socket, $this->collection->getIdleSocket($connection));
    }

    /**
     * @throws ConnectException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReadFailedException
     * @throws TimedoutException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testSocketWithResponseIsIdle(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $request = new PostRequest(
            dirname(__DIR__, 2) . '/Integration/Workers/sleepWorker.php',
            ''
        );
        $socket->sendRequest($request);

        /** @noinspection UnusedFunctionResultInspection */
        $socket->fetchResponse(2000);

        self::assertSame($socket, $this->collection->getIdleSocket($connection));
    }

    /**
     * @throws ConnectException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws TimedoutException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testBusySocketIsNotIdle(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $socket->sendRequest(
            new PostRequest('/some/script.php', '')
        );

        self::assertNull($this->collection->getIdleSocket($connection));
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testNotUsableSocketIsNotIdle(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $connectMethod = (new ReflectionClass($socket))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socket);

        foreach ($this->collection->collectResources() as $resource) {
            while (!feof($resource)) {
                /** @noinspection UnusedFunctionResultInspection */
                fread($resource, 1024);
            }
        }

        self::assertNull($this->collection->getIdleSocket($connection));

        # Socket should also be removed from collection
        self::assertCount(0, $this->collection);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testClosedSocketIsNotIdle(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $connectMethod = (new ReflectionClass($socket))->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($socket);

        foreach ($this->collection->collectResources() as $resource) {
            fclose($resource);
        }

        self::assertNull($this->collection->getIdleSocket($connection));

        # Socket should also be removed from collection
        self::assertCount(0, $this->collection);
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     * @throws ReadFailedException
     * @throws \Exception
     */
    public function testGetById(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $checkSocket = $this->collection->getById($socket->getId());

        self::assertSame($checkSocket, $socket);
    }

    /**
     * @throws ReadFailedException
     * @throws AssertionFailedError
     */
    public function testThrowsExceptionIfSocketCannotByFoundById(): void
    {
        $this->expectException(ReadFailedException::class);
        $this->expectExceptionMessage('Socket not found for socket ID: 123');

        /** @noinspection UnusedFunctionResultInspection */
        $this->collection->getById(123);

        self::fail('Expected a ReadFailedException to be thrown.');
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testNew(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $socketOne = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        self::assertGreaterThan(0, $socketOne->getId());
        self::assertCount(1, $this->collection);
        self::assertSame(1, $this->collection->count());

        $socketTwo = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        self::assertGreaterThan(0, $socketTwo->getId());
        self::assertNotSame($socketOne->getId(), $socketTwo->getId());
        self::assertCount(2, $this->collection);
        self::assertSame(2, $this->collection->count());
    }

    /**
     * @throws AssertionFailedError
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testThrowsExceptionIfNoNewSocketCanBeCreated(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $this->expectException(WriteFailedException::class);

        for ($i = 0; $i < 66000; $i++) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->collection->new($connection, $packetEncoder, $nameValuePairEncoder);
        }

        self::fail('Expected WriteFailedException to be thrown.');
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     * @throws ConnectException
     * @throws TimedoutException
     * @throws \Exception
     */
    public function testHasBusySockets(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);
        self::assertFalse($this->collection->hasBusySockets());

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $socket->sendRequest(
            new PostRequest('/some/sctipt.php', '')
        );

        self::assertTrue($this->collection->hasBusySockets());
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     * @throws \Exception
     */
    public function testRemove(): void
    {
        $connection           = $this->getSocketConnection();
        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        self::assertCount(0, $this->collection);

        $socket = $this->collection->new(
            $connection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        self::assertCount(1, $this->collection);

        $this->collection->remove($socket->getId());

        self::assertCount(0, $this->collection);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testCount(): void
    {
        self::assertSame(0, $this->collection->count());
        self::assertCount(0, $this->collection);
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testIsEmpty(): void
    {
        self::assertTrue($this->collection->isEmpty());
    }

    /**
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws WriteFailedException
     */
    public function testIdleSocketsAreIdentifiedByConnection(): void
    {
        $unixDomainConnection = new UnixDomainSocket($this->getUnixDomainSocket());
        $networkConnection    = new NetworkSocket($this->getNetworkSocketHost(), $this->getNetworkSocketPort());

        $packetEncoder        = new PacketEncoder();
        $nameValuePairEncoder = new NameValuePairEncoder();

        $unixDomainSocket = $this->collection->new(
            $unixDomainConnection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        $networkSocket = $this->collection->new(
            $networkConnection,
            $packetEncoder,
            $nameValuePairEncoder
        );

        self::assertCount(2, $this->collection);

        self::assertSame($unixDomainSocket, $this->collection->getIdleSocket($unixDomainConnection));
        self::assertNotSame($unixDomainSocket, $this->collection->getIdleSocket($networkConnection));

        self::assertSame($networkSocket, $this->collection->getIdleSocket($networkConnection));
        self::assertNotSame($networkSocket, $this->collection->getIdleSocket($unixDomainConnection));
    }
}
