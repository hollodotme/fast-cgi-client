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

			self::assertGreaterThanOrEqual( 1, $socketId->getValue() );
			self::assertLessThanOrEqual( (1 << 16) - 1, $socketId->getValue() );
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

		self::assertGreaterThanOrEqual( 1, $socketId->getValue() );
		self::assertLessThanOrEqual( (1 << 16) - 1, $socketId->getValue() );
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

			self::assertSame( $i, $socketId->getValue() );
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

	/**
	 * @return array<array<string, int>>
	 */
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

		self::assertNotSame( $socketId, $otherEquals );
		self::assertNotSame( $socketId, $otherEqualsNot );
		self::assertNotSame( $otherEquals, $otherEqualsNot );

		self::assertTrue( $socketId->equals( $otherEquals ) );
		self::assertTrue( $otherEquals->equals( $socketId ) );

		self::assertFalse( $socketId->equals( $otherEqualsNot ) );
		self::assertFalse( $otherEqualsNot->equals( $socketId ) );

		self::assertFalse( $otherEquals->equals( $otherEqualsNot ) );
		self::assertFalse( $otherEqualsNot->equals( $otherEquals ) );
	}
}
