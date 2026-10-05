<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2020 Holger Woltersdorf & Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

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
