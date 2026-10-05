<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Encoders;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use function array_keys;
use function str_repeat;
use function strlen;

final class NameValuePairEncoderTest extends TestCase
{
	/**
	 * @param array<mixed, mixed> $pairs
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider pairProvider
	 */
	public function testCanEncodeAndDecodePairs( array $pairs ) : void
	{
		$nameValuePairEncoder = new NameValuePairEncoder();

		$encoded = $nameValuePairEncoder->encodePairs( $pairs );
		$decoded = $nameValuePairEncoder->decodePairs( $encoded );

		self::assertEquals( $pairs, $decoded );
	}

	/**
	 * @return array<array<string, array<mixed, mixed>>>
	 */
	public function pairProvider() : array
	{
		return [
			[
				'pairs' => ['unit' => 'test'],
			],
			# no strings
			[
				'pairs' => [10 => 12.3, 'null' => null],
			],
			# name longer than 128 chars
			[
				'pairs' => [str_repeat( 'a', 129 ) => 'unit'],
			],
			# value longer than 128 chars
			[
				'pairs' => ['unit' => str_repeat( 'b', 129 )],
			],
		];
	}

	/**
	 * Lengths of 16 MiB and more use the highest byte of the 4-byte length encoding.
	 *
	 * @param string $name
	 * @param string $value
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider longPairProvider
	 */
	public function testCanDecodePairsWithLengthsOf16MiBAndMore( string $name, string $value ) : void
	{
		$encoder = new NameValuePairEncoder();
		$encoded = $encoder->encodePair( $name, $value ) . $encoder->encodePair( 'next', 'pair' );
		$decoded = $encoder->decodePairs( $encoded );

		self::assertSame( [$name, 'next'], array_keys( $decoded ) );
		self::assertSame( strlen( $value ), strlen( $decoded[ $name ] ) );
		self::assertSame( 'pair', $decoded['next'] );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function longPairProvider() : array
	{
		return [
			'long value' => ['name', str_repeat( 'v', (1 << 24) + 5 )],
			'long name'  => [str_repeat( 'n', (1 << 24) + 5 ), 'value'],
		];
	}
}
