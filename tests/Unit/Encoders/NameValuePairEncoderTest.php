<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2019 Holger Woltersdorf & Contributors
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

final class NameValuePairEncoderTest extends TestCase
{
	/**
	 * @param array $pairs
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

		$this->assertEquals( $pairs, $decoded );
	}

	public function pairProvider() : array
	{
		return [
			[
				['unit' => 'test'],
			],
			# no strings
			[
				[10 => 12.3, 'null' => null],
			],
			# name longer than 128 chars
			[
				[str_repeat( 'a', 129 ) => 'unit'],
			],
			# value longer than 128 chars
			[
				['unit' => str_repeat( 'b', 129 )],
			],
		];
	}
}
