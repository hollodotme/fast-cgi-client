<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use hollodotme\FastCGI\Sockets\SocketId;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class SocketIdTest extends TestCase
{
	/**
	 * @throws \InvalidArgumentException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetNewInstance() : void
	{
		for ( $i = 0; $i < 100; $i++ )
		{
			$socketId = SocketId::new();

			$this->assertGreaterThanOrEqual( 1, $socketId->getValue() );
			$this->assertLessThanOrEqual( (1 << 16) - 1, $socketId->getValue() );
		}
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function testGetValue() : void
	{
		$socketId = SocketId::new();

		$this->assertGreaterThanOrEqual( 1, $socketId->getValue() );
		$this->assertLessThanOrEqual( (1 << 16) - 1, $socketId->getValue() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function testCanGetNewInstanceFromInt() : void
	{
		for ( $i = 1; $i < 10; $i++ )
		{
			$socketId = SocketId::fromInt( $i );

			$this->assertSame( $i, $socketId->getValue() );
		}
	}

	/**
	 * @param int $socketIdValue
	 *
	 * @throws \InvalidArgumentException
	 * @dataProvider outOfRangeSocketIdValueProvider
	 */
	public function testThrowsExceptionIfSocketIdValueIsOutOfRange( int $socketIdValue ) : void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid socket ID (out of range): ' . $socketIdValue );

		SocketId::fromInt( $socketIdValue );
	}

	public function outOfRangeSocketIdValueProvider() : array
	{
		return [
			[
				'socketIdValue' => 0,
			],
			[
				'socketIdValue' => 1 << 16,
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function testEquals() : void
	{
		$socketId       = SocketId::fromInt( 123 );
		$otherEquals    = SocketId::fromInt( 123 );
		$otherEqualsNot = SocketId::fromInt( 321 );

		$this->assertNotSame( $socketId, $otherEquals );
		$this->assertNotSame( $socketId, $otherEqualsNot );
		$this->assertNotSame( $otherEquals, $otherEqualsNot );

		$this->assertTrue( $socketId->equals( $otherEquals ) );
		$this->assertTrue( $otherEquals->equals( $socketId ) );

		$this->assertFalse( $socketId->equals( $otherEqualsNot ) );
		$this->assertFalse( $otherEqualsNot->equals( $socketId ) );

		$this->assertFalse( $otherEquals->equals( $otherEqualsNot ) );
		$this->assertFalse( $otherEqualsNot->equals( $otherEquals ) );
	}
}
