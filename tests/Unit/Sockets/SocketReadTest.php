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

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketId;
use hollodotme\FastCGI\Tests\Unit\Sockets\Fixtures\ChunkedStreamWrapper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use function chr;
use function fopen;
use function in_array;
use function str_repeat;
use function stream_get_wrappers;
use function stream_wrapper_register;

/**
 * The socket reads from a stream that delivers the response in small parts.
 */
final class SocketReadTest extends TestCase
{
	private const STDOUT      = 6;

	private const STDERR      = 7;

	private const END_REQUEST = 3;

	protected function setUp() : void
	{
		if ( !in_array( ChunkedStreamWrapper::PROTOCOL, stream_get_wrappers(), true ) )
		{
			stream_wrapper_register( ChunkedStreamWrapper::PROTOCOL, ChunkedStreamWrapper::class );
		}
	}

	/**
	 * Before, a packet header that did not arrive at once was decoded from the bytes received so far.
	 *
	 * @param int $chunkSize
	 *
	 * @throws Throwable
	 * @dataProvider chunkSizeProvider
	 */
	public function testResponseArrivingInPartsIsReadCompletely( int $chunkSize ) : void
	{
		$socketId = SocketId::new();
		$encoder  = new PacketEncoder();

		ChunkedStreamWrapper::$chunkSize = $chunkSize;
		ChunkedStreamWrapper::$data      =
			$encoder->encodePacket( self::STDOUT, "Content-Type: text/plain\r\n\r\n", $socketId->getValue() )
			. $encoder->encodePacket( self::STDERR, 'error', $socketId->getValue() )
			. $encoder->encodePacket( self::STDOUT, 'unit', $socketId->getValue() )
			. $encoder->encodePacket( self::END_REQUEST, str_repeat( chr( 0 ), 8 ), $socketId->getValue() );

		$socket = $this->getSocketReadingFromChunkedStream( $socketId );
		$socket->sendRequest( new PostRequest( '/path/to/script.php', '' ) );

		$response = $socket->fetchResponse();

		self::assertSame( 'text/plain', $response->getHeaderLine( 'Content-Type' ) );
		self::assertSame( 'unit', $response->getBody() );
		self::assertSame( 'error', $response->getError() );
	}

	/**
	 * @return array<string, array<int, int>>
	 */
	public function chunkSizeProvider() : array
	{
		return [
			'1 byte'  => [1],
			'3 bytes' => [3],
			'7 bytes' => [7],
		];
	}

	/**
	 * @param SocketId $socketId
	 *
	 * @return Socket
	 * @throws Throwable
	 */
	private function getSocketReadingFromChunkedStream( SocketId $socketId ) : Socket
	{
		$stream = fopen( ChunkedStreamWrapper::PROTOCOL . '://response', 'r+b' );

		if ( false === $stream )
		{
			throw new RuntimeException( 'Could not open chunked stream.' );
		}

		$socket = new Socket(
			$socketId,
			new UnixDomainSocket( '/not/used.sock' ),
			new PacketEncoder(),
			new NameValuePairEncoder()
		);

		$property = new ReflectionProperty( $socket, 'resource' );
		$property->setAccessible( true );
		$property->setValue( $socket, $stream );

		return $socket;
	}
}
