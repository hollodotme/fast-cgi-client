<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Sockets\SocketCollection;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use function reset;
use const STDIN;

final class SocketCollectionTest extends TestCase
{
	use SocketDataProviding;

	/** @var SocketCollection */
	private $collection;

	protected function setUp() : void
	{
		$this->collection = new SocketCollection();
	}

	protected function tearDown() : void
	{
		$this->collection = null;
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testCollectResources() : void
	{
		$connection           = $this->getSocketConnectionMock();
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

		$connectMethod = (new ReflectionClass( $socketOne ))->getMethod( 'connect' );
		$connectMethod->setAccessible( true );
		$connectMethod->invoke( $socketOne );

		$connectMethod = (new ReflectionClass( $socketTwo ))->getMethod( 'connect' );
		$connectMethod->setAccessible( true );
		$connectMethod->invoke( $socketTwo );

		$resources = [];
		$socketOne->collectResource( $resources );
		$socketTwo->collectResource( $resources );

		$this->assertSame( $resources, $this->collection->collectResources() );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testGetByResource() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$connectMethod = (new ReflectionClass( $socket ))->getMethod( 'connect' );
		$connectMethod->setAccessible( true );
		$connectMethod->invoke( $socket );

		$resources = [];
		$socket->collectResource( $resources );

		$checkSocket = $this->collection->getByResource( reset( $resources ) );

		$this->assertSame( $checkSocket, $socket );
	}

	/**
	 * @throws AssertionFailedError
	 * @throws ReadFailedException
	 */
	public function testThrowsExceptionIfSocketCannotBeFoundByResource() : void
	{
		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Socket not found for resource' );

		/** @noinspection UnusedFunctionResultInspection */
		$this->collection->getByResource( STDIN );

		$this->fail( 'Expected a ReadFailedException for not found socket by resource.' );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testGetSocketIdsByResources() : void
	{
		$connection           = $this->getSocketConnectionMock();
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

		$connectMethod = (new ReflectionClass( $socketOne ))->getMethod( 'connect' );
		$connectMethod->setAccessible( true );
		$connectMethod->invoke( $socketOne );

		$connectMethod = (new ReflectionClass( $socketTwo ))->getMethod( 'connect' );
		$connectMethod->setAccessible( true );
		$connectMethod->invoke( $socketTwo );

		$resources = [];
		$socketOne->collectResource( $resources );
		$socketTwo->collectResource( $resources );

		$expectedSocketIds = [$socketOne->getId(), $socketTwo->getId()];

		$this->assertSame( $expectedSocketIds, $this->collection->getSocketIdsByResources( $resources ) );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testEmptyCollectionHasNoIdleSocket() : void
	{
		$this->assertNull( $this->collection->getIdleSocket() );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testNewlyAddedSocketIsIdle() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->assertCount( 0, $this->collection );

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$this->assertSame( $socket, $this->collection->getIdleSocket() );
	}

	/**
	 * @throws ConnectException
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReadFailedException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testSocketWithResponseIsIdle() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->assertCount( 0, $this->collection );

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$socket->sendRequest(
			new PostRequest( '/some/script.php', '' )
		);

		/** @noinspection UnusedFunctionResultInspection */
		$socket->fetchResponse();

		$this->assertSame( $socket, $this->collection->getIdleSocket() );
	}

	/**
	 * @throws ConnectException
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testBusySocketIsNotIdle() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->assertCount( 0, $this->collection );

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$socket->sendRequest(
			new PostRequest( '/some/script.php', '' )
		);

		$this->assertNull( $this->collection->getIdleSocket() );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 * @throws ReadFailedException
	 */
	public function testGetById() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$checkSocket = $this->collection->getById( $socket->getId() );

		$this->assertSame( $checkSocket, $socket );
	}

	/**
	 * @throws ReadFailedException
	 * @throws AssertionFailedError
	 */
	public function testThrowsExceptionIfSocketCannotByFoundById() : void
	{
		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Socket not found for request ID: 123' );

		/** @noinspection UnusedFunctionResultInspection */
		$this->collection->getById( 123 );

		$this->fail( 'Expected a ReadFailedException to be thrown.' );
	}

	/**
	 * @return ConfiguresSocketConnection
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws Exception
	 */
	private function getSocketConnectionMock() : ConfiguresSocketConnection
	{
		$connection = $this->getMockBuilder( ConfiguresSocketConnection::class )->getMockForAbstractClass();
		$connection->method( 'getSocketAddress' )->willReturn( 'unix://' . $this->getUnixDomainSocket() );

		/** @var ConfiguresSocketConnection $connection */
		return $connection;
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testNew() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$socketOne = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$this->assertGreaterThan( 0, $socketOne->getId() );
		$this->assertCount( 1, $this->collection );
		$this->assertSame( 1, $this->collection->count() );

		$socketTwo = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$this->assertGreaterThan( 0, $socketTwo->getId() );
		$this->assertNotSame( $socketOne->getId(), $socketTwo->getId() );
		$this->assertCount( 2, $this->collection );
		$this->assertSame( 2, $this->collection->count() );
	}

	/**
	 * @throws Exception
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testThrowsExceptionIfNoNewSocketCanBeCreated() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->expectException( WriteFailedException::class );

		for ( $i = 0; $i < 66000; $i++ )
		{
			/** @noinspection UnusedFunctionResultInspection */
			$this->collection->new( $connection, $packetEncoder, $nameValuePairEncoder );
		}

		$this->fail( 'Expected WriteFailedException to be thrown.' );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 * @throws TimedoutException
	 */
	public function testHasBusySockets() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->assertCount( 0, $this->collection );
		$this->assertFalse( $this->collection->hasBusySockets() );

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$socket->sendRequest(
			new PostRequest( '/some/sctipt.php', '' )
		);

		$this->assertTrue( $this->collection->hasBusySockets() );
	}

	/**
	 * @throws Exception
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws ReflectionException
	 * @throws RuntimeException
	 * @throws WriteFailedException
	 */
	public function testRemove() : void
	{
		$connection           = $this->getSocketConnectionMock();
		$packetEncoder        = new PacketEncoder();
		$nameValuePairEncoder = new NameValuePairEncoder();

		$this->assertCount( 0, $this->collection );

		$socket = $this->collection->new(
			$connection,
			$packetEncoder,
			$nameValuePairEncoder
		);

		$this->assertCount( 1, $this->collection );

		$this->collection->remove( $socket->getId() );

		$this->assertCount( 0, $this->collection );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function testCount() : void
	{
		$this->assertSame( 0, $this->collection->count() );
		$this->assertCount( 0, $this->collection );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testIsEmpty() : void
	{
		$this->assertTrue( $this->collection->isEmpty() );
	}
}
