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

use hollodotme\FastCGI\Interfaces\EncodesPacket;
use function chr;
use function ord;
use function strlen;

/**
 * Class PacketEncoder
 * @package hollodotme\FastCGI\Encoders
 */
final class PacketEncoder implements EncodesPacket
{
	private const VERSION = 1;

	public function encodePacket( int $type, string $content, int $requestId ) : string
	{
		$contentLength = strlen( $content );

		return chr( self::VERSION )                     /* version */
		       . chr( $type )                           /* type */
		       . chr( ($requestId >> 8) & 0xFF )        /* requestIdB1 */
		       . chr( $requestId & 0xFF )               /* requestIdB0 */
		       . chr( ($contentLength >> 8) & 0xFF )    /* contentLengthB1 */
		       . chr( $contentLength & 0xFF )           /* contentLengthB0 */
		       . chr( 0 )                               /* paddingLength */
		       . chr( 0 )                               /* reserved */
			   . $content;                              /* content */
	}

	public function decodeHeader( string $data ) : array
	{
		$header                  = [];
		$header['version']       = ord( $data[0] );
		$header['type']          = ord( $data[1] );
		$header['requestId']     = (ord( $data[2] ) << 8) + ord( $data[3] );
		$header['contentLength'] = (ord( $data[4] ) << 8) + ord( $data[5] );
		$header['paddingLength'] = ord( $data[6] );
		$header['reserved']      = ord( $data[7] );

		return $header;
	}
}
