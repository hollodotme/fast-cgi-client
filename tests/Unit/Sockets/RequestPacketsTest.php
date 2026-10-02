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

use Exception;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Requests\AbstractRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function array_filter;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Checks the records a request is encoded into, without sending them.
 */
final class RequestPacketsTest extends TestCase
{
	private const PARAMS = 4;

	private const STDIN  = 5;

	/**
	 * @throws Exception
	 */
	public function testParamsFitIntoOneRecord() : void
	{
		$records = $this->getRecords( new PostRequest( '/path/to/script.php', '' ) );

		self::assertCount( 2, $this->getContents( $records, self::PARAMS ) );
		self::assertSame( '', $this->getContents( $records, self::PARAMS )[1] );
	}

	/**
	 * php-fpm decodes the params of each record separately, so no name-value pair may be split.
	 *
	 * @throws Exception
	 */
	public function testParamsLongerThanOneRecordAreSplitBetweenNameValuePairs() : void
	{
		$request = new PostRequest( '/path/to/script.php', '' );

		for ( $i = 0; $i < 20; $i++ )
		{
			$request->setCustomVar( 'LARGE_PARAM_' . $i, str_repeat( (string)($i % 10), 5000 ) );
		}

		$contents = array_values( array_filter( $this->getContents( $this->getRecords( $request ), self::PARAMS ) ) );
		$decoded  = [];

		self::assertGreaterThan( 1, count( $contents ) );

		foreach ( $contents as $content )
		{
			self::assertLessThanOrEqual( 65535, strlen( $content ) );

			$decoded = array_merge( $decoded, (new NameValuePairEncoder())->decodePairs( $content ) );
		}

		self::assertEquals( $request->getParams(), $decoded );
	}

	/**
	 * A single name-value pair longer than one record spans consecutive records, as the specification allows.
	 *
	 * @throws Exception
	 */
	public function testNameValuePairLongerThanOneRecordSpansRecords() : void
	{
		$request = new PostRequest( '/path/to/script.php', '' );
		$request->setCustomVar( 'HUGE_PARAM', str_repeat( 'h', 70000 ) );

		$contents = array_values( array_filter( $this->getContents( $this->getRecords( $request ), self::PARAMS ) ) );
		$params   = (new NameValuePairEncoder())->decodePairs( implode( '', $contents ) );

		self::assertGreaterThan( 1, count( $contents ) );
		self::assertSame( str_repeat( 'h', 70000 ), $params['HUGE_PARAM'] );
	}

	/**
	 * @throws Exception
	 */
	public function testContentLongerThanOneRecordIsSplitIntoRecords() : void
	{
		$content  = str_repeat( 'abc', 70000 );
		$contents = $this->getContents(
			$this->getRecords( new PostRequest( '/path/to/script.php', $content ) ),
			self::STDIN
		);

		self::assertCount( 5, $contents );
		self::assertSame( '', $contents[4] );
		self::assertSame( $content, implode( '', $contents ) );
	}

	/**
	 * @throws Exception
	 */
	public function testEmptyContentIsSentAsOneEmptyRecord() : void
	{
		$contents = $this->getContents(
			$this->getRecords( new PostRequest( '/path/to/script.php', '' ) ),
			self::STDIN
		);

		self::assertSame( [''], $contents );
	}

	/**
	 * @param AbstractRequest $request
	 *
	 * @return array<int, array{0: int, 1: string}> Type and content of each record
	 * @throws Exception
	 */
	private function getRecords( AbstractRequest $request ) : array
	{
		$socket = new Socket(
			SocketId::new(),
			new UnixDomainSocket( '/not/used.sock' ),
			new PacketEncoder(),
			new NameValuePairEncoder()
		);

		$method = (new ReflectionClass( $socket ))->getMethod( 'getRequestPackets' );
		$method->setAccessible( true );

		/** @var string $packets */
		$packets = $method->invoke( $socket, $request );
		$encoder = new PacketEncoder();
		$records = [];
		$offset  = 0;

		while ( $offset < strlen( $packets ) )
		{
			$header    = $encoder->decodeHeader( substr( $packets, $offset, 8 ) );
			$records[] = [$header['type'], (string)substr( $packets, $offset + 8, $header['contentLength'] )];
			$offset    += 8 + $header['contentLength'] + $header['paddingLength'];
		}

		return $records;
	}

	/**
	 * @param array<int, array{0: int, 1: string}> $records
	 * @param int                                  $type
	 *
	 * @return array<int, string>
	 */
	private function getContents( array $records, int $type ) : array
	{
		$contents = [];

		foreach ( $records as [$recordType, $content] )
		{
			if ( $type === $recordType )
			{
				$contents[] = $content;
			}
		}

		return $contents;
	}
}
