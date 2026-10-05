<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Sockets;

use Exception;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use hollodotme\FastCGI\RequestContents\PlainText;
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
use const PHP_VERSION_ID;

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
		$records = $this->getRecords( new PostRequest( '/path/to/script.php' ) );

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
		$request = new PostRequest( '/path/to/script.php' );

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
		$request = new PostRequest( '/path/to/script.php' );
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
			$this->getRecords( new PostRequest( '/path/to/script.php', new PlainText( $content ) ) ),
			self::STDIN
		);

		self::assertCount( 5, $contents );
		self::assertSame( '', $contents[4] );
		self::assertSame( $content, implode( '', $contents ) );
	}

	/**
	 * @throws Exception
	 */
	public function testContentLengthMatchesTheContentThatIsSent() : void
	{
		# A content that is composed differently each time
		$content = new class implements ComposesRequestContent
		{
			private int $calls = 0;

			public function getContentType() : string
			{
				return 'text/plain';
			}

			public function getContent() : string
			{
				return str_repeat( 'x', ++$this->calls * 10 );
			}
		};

		$records = $this->getRecords( new PostRequest( '/path/to/script.php', $content ) );
		$params  = (new NameValuePairEncoder())->decodePairs(
			implode( '', $this->getContents( $records, self::PARAMS ) )
		);

		self::assertSame(
			(string)strlen( implode( '', $this->getContents( $records, self::STDIN ) ) ),
			$params['CONTENT_LENGTH']
		);
	}

	/**
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
		if ( PHP_VERSION_ID < 80100 )
		{
			$method->setAccessible( true );
		}

		/** @var string $packets */
		$packets = $method->invoke( $socket, $request );
		$encoder = new PacketEncoder();
		$records = [];
		$offset  = 0;

		while ( $offset < strlen( $packets ) )
		{
			$header    = $encoder->decodeHeader( substr( $packets, $offset, 8 ) );
			$records[] = [$header['type'], substr( $packets, $offset + 8, $header['contentLength'] )];
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
