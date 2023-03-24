<?php declare(strict_types=1);

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
		       . chr( $type )                                /* type */
		       . chr( ($requestId >> 8) & 0xFF )        /* requestIdB1 */
		       . chr( $requestId & 0xFF )               /* requestIdB0 */
		       . chr( ($contentLength >> 8) & 0xFF )    /* contentLengthB1 */
		       . chr( $contentLength & 0xFF )           /* contentLengthB0 */
		       . chr( 0 )                               /* paddingLength */
		       . chr( 0 )                               /* reserved */
		       . $content;                                   /* content */
	}

	/**
	 * @param string $data
	 *
	 * @return array<string, int>
	 */
	public function decodeHeader( string $data ) : array
	{
		return [
			'version'       => ord( $data[0] ),
			'type'          => ord( $data[1] ),
			'requestId'     => (ord( $data[2] ) << 8) + ord( $data[3] ),
			'contentLength' => (ord( $data[4] ) << 8) + ord( $data[5] ),
			'paddingLength' => ord( $data[6] ),
			'reserved'      => ord( $data[7] ),
		];
	}
}
