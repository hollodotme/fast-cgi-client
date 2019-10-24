<?php declare(strict_types = 1);
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

namespace hollodotme\FastCGI\Encoders;

use hollodotme\FastCGI\Interfaces\EncodesNameValuePair;
use function chr;
use function ord;
use function strlen;

/**
 * Class NameValuePairEncoder
 * @package hollodotme\FastCGI\Encoders
 */
final class NameValuePairEncoder implements EncodesNameValuePair
{
	public function encodePairs( array $pairs ) : string
	{
		$encoded = '';

		foreach ( $pairs as $key => $value )
		{
			$encoded .= $this->encodePair( (string)$key, (string)$value );
		}

		return $encoded;
	}

	public function encodePair( string $name, string $value ) : string
	{
		$nameLength  = strlen( $name );
		$valueLength = strlen( $value );

		if ( $nameLength < 128 )
		{
			/* nameLengthB0 */
			$nameValuePair = chr( $nameLength );
		}
		else
		{
			/* nameLengthB3 & nameLengthB2 & nameLengthB1 & nameLengthB0 */
			$nameValuePair = chr( ($nameLength >> 24) | 0x80 )
			                 . chr( ($nameLength >> 16) & 0xFF )
			                 . chr( ($nameLength >> 8) & 0xFF )
			                 . chr( $nameLength & 0xFF );
		}
		if ( $valueLength < 128 )
		{
			/* valueLengthB0 */
			$nameValuePair .= chr( $valueLength );
		}
		else
		{
			/* valueLengthB3 & valueLengthB2 & valueLengthB1 & valueLengthB0 */
			$nameValuePair .= chr( ($valueLength >> 24) | 0x80 )
			                  . chr( ($valueLength >> 16) & 0xFF )
			                  . chr( ($valueLength >> 8) & 0xFF )
			                  . chr( $valueLength & 0xFF );
		}

		return $nameValuePair . $name . $value;
	}

	public function decodePairs( string $data, int $length = -1 ) : array
	{
		$array = [];

		if ( $length === -1 )
		{
			$length = strlen( $data );
		}

		$p = 0;

		while ( $p !== $length )
		{
			$nameLength = ord( $data[$p++] );
			if ( $nameLength >= 128 )
			{
				$nameLength &= (0x7F << 24);
				$nameLength |= (ord( $data[$p++] ) << 16);
				$nameLength |= (ord( $data[$p++] ) << 8);
				$nameLength |= ord( $data[$p++] );
			}

			$valueLength = ord( $data[$p++] );
			if ( $valueLength >= 128 )
			{
				$valueLength = ($nameLength & 0x7F << 24);
				$valueLength |= (ord( $data[$p++] ) << 16);
				$valueLength |= (ord( $data[$p++] ) << 8);
				$valueLength |= ord( $data[$p++] );
			}
			$array[ substr( $data, $p, $nameLength ) ] = substr( $data, $p + $nameLength, $valueLength );
			$p += ($nameLength + $valueLength);
		}

		return $array;
	}
}
