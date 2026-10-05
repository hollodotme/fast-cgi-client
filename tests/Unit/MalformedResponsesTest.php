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

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use function chr;
use function explode;
use function fclose;
use function fwrite;
use function is_resource;
use function microtime;
use function str_repeat;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function strlen;
use function substr;

/**
 * Tests how the client deals with responses that are not valid FastCGI responses.
 * The test acts as the server itself: it accepts the connection of the client and writes the response to it.
 */
final class MalformedResponsesTest extends TestCase
{
	private const END_REQUEST  = 3;

	private const STDOUT       = 6;

	private const STDERR       = 7;

	private const UNKNOWN_TYPE = 11;

	private const READ_TIMEOUT = 200;

	/** @var resource */
	private $server;

	/** @var resource|null */
	private $connectionToClient;

	/** @var Client */
	private $client;

	protected function setUp() : void
	{
		$server = stream_socket_server( 'tcp://127.0.0.1:0' );

		if ( false === $server )
		{
			throw new RuntimeException( 'Could not start server.' );
		}

		$this->server = $server;
		$this->client = new Client();
	}

	protected function tearDown() : void
	{
		if ( is_resource( $this->connectionToClient ) )
		{
			fclose( $this->connectionToClient );
		}

		fclose( $this->server );
	}

	/**
	 * These cases ended up in an endless loop, because reading from a closed connection was repeated forever.
	 *
	 * @param callable(int) : string $response
	 *
	 * @throws Throwable
	 * @dataProvider incompleteResponseProvider
	 */
	public function testReadingFailsIfConnectionIsClosedBeforeResponseIsComplete( callable $response ) : void
	{
		$socketId = $this->sendRequestAndRespond( $response );

		try
		{
			/** @noinspection UnusedFunctionResultInspection */
			$this->client->readResponse( $socketId );

			self::fail( 'Expected ReadFailedException to be thrown.' );
		}
		catch ( ReadFailedException $e )
		{
			self::assertFalse( $this->client->hasUnhandledResponses() );
		}
	}

	/**
	 * @return array<string, array<int, callable>>
	 */
	public function incompleteResponseProvider() : array
	{
		return [
			'no response at all'          => [
				static function ( int $id ) : string
				{
					return '';
				},
			],
			# The header of the HTTP response is decoded to a content length that is never sent
			'response of a HTTP server'   => [
				static function ( int $id ) : string
				{
					return "HTTP/1.1 400 Bad Request\r\nContent-Length: 11\r\n\r\nBad Request";
				},
			],
			'incomplete header'           => [
				function ( int $id ) : string
				{
					return substr( $this->header( self::STDOUT, $id, 100 ), 0, 4 );
				},
			],
			'incomplete content'          => [
				function ( int $id ) : string
				{
					return $this->header( self::STDOUT, $id, 100 ) . 'only 10 by';
				},
			],
			'missing padding'             => [
				function ( int $id ) : string
				{
					return $this->header( self::STDOUT, $id, 4, 5 ) . 'unit';
				},
			],
			'missing end-request record'  => [
				function ( int $id ) : string
				{
					return $this->record( self::STDOUT, $id, 'unit' );
				},
			],
			'incomplete end-request body' => [
				function ( int $id ) : string
				{
					return $this->record( self::STDOUT, $id, 'unit' )
						   . $this->header( self::END_REQUEST, $id, 8 ) . chr( 0 );
				},
			],
		];
	}

	/**
	 * The protocol status is the fifth byte of an end-request record. Before, a shorter record caused an
	 * "Uninitialized string offset" notice and was treated as a completed request.
	 *
	 * @throws Throwable
	 */
	public function testEndRequestRecordWithoutProtocolStatusFails() : void
	{
		$socketId = $this->sendRequestAndRespond(
			function ( int $id ) : string
			{
				return $this->record( self::STDOUT, $id, 'unit' )
					   . $this->record( self::END_REQUEST, $id, str_repeat( chr( 0 ), 3 ) );
			}
		);

		$this->expectException( ReadFailedException::class );
		$this->expectExceptionMessage( 'Invalid end-request record: missing protocol status' );

		/** @noinspection UnusedFunctionResultInspection */
		$this->client->readResponse( $socketId );
	}

	/**
	 * @throws Throwable
	 */
	public function testReadingTimesOutIfResponseIsNotCompleted() : void
	{
		$socketId = $this->sendRequestAndRespond(
			function ( int $id ) : string
			{
				return $this->header( self::STDOUT, $id, 100 ) . 'only 10 by';
			},
			false
		);

		$start = microtime( true );

		try
		{
			/** @noinspection UnusedFunctionResultInspection */
			$this->client->readResponse( $socketId );

			self::fail( 'Expected TimedoutException to be thrown.' );
		}
		catch ( TimedoutException $e )
		{
			$duration = (microtime( true ) - $start) * 1000;

			self::assertSame( 'Read timed out', $e->getMessage() );

			# The read must not wait for the timeout more than once
			self::assertGreaterThanOrEqual( self::READ_TIMEOUT - 10, $duration );
			self::assertLessThan( self::READ_TIMEOUT * 2, $duration );
		}
	}

	/**
	 * @throws Throwable
	 */
	public function testValidResponseIsRead() : void
	{
		$socketId = $this->sendRequestAndRespond(
			function ( int $id ) : string
			{
				return $this->record( self::STDOUT, $id, "X-Unit: Test\r\n\r\nunit" ) . $this->endRequest( $id );
			}
		);

		$response = $this->client->readResponse( $socketId );

		self::assertSame( 'Test', $response->getHeaderLine( 'X-Unit' ) );
		self::assertSame( 'unit', $response->getBody() );
		self::assertSame( '', $response->getError() );
	}

	/**
	 * @throws Throwable
	 */
	public function testPaddingAndManagementRecordsAreSkipped() : void
	{
		$socketId = $this->sendRequestAndRespond(
			function ( int $id ) : string
			{
				return $this->record( self::STDOUT, $id, "X-Unit: Test\r\n\r\n", 3 )
					   . $this->record( self::UNKNOWN_TYPE, 0, str_repeat( chr( 0 ), 8 ) )
					   . $this->record( self::STDERR, $id, 'error', 7 )
					   . $this->record( self::STDOUT, $id, 'unit', 4 )
					   . $this->endRequest( $id );
			}
		);

		$response = $this->client->readResponse( $socketId );

		self::assertSame( 'unit', $response->getBody() );
		self::assertSame( 'error', $response->getError() );
	}

	/**
	 * Sends a request to the server of this test and lets the server write the given response to the client.
	 *
	 * @param callable(int) : string $response Gets the ID of the request and returns the response of the server
	 * @param bool                   $closeConnection
	 *
	 * @return int Socket ID
	 * @throws Throwable
	 */
	private function sendRequestAndRespond( callable $response, bool $closeConnection = true ) : int
	{
		[$host, $port] = explode( ':', (string)stream_socket_get_name( $this->server, false ) );

		$socketId = $this->client->sendAsyncRequest(
			new NetworkSocket( $host, (int)$port, self::READ_TIMEOUT, self::READ_TIMEOUT ),
			new PostRequest( '/path/to/script.php', '' )
		);

		$connectionToClient = stream_socket_accept( $this->server, 1 );

		if ( false === $connectionToClient )
		{
			throw new RuntimeException( 'Client did not connect to server.' );
		}

		# The client derives the ID of the request from the ID of the socket
		fwrite( $connectionToClient, $response( $socketId ) );

		if ( $closeConnection )
		{
			fclose( $connectionToClient );

			return $socketId;
		}

		$this->connectionToClient = $connectionToClient;

		return $socketId;
	}

	private function header(
		int $type,
		int $requestId,
		int $contentLength,
		int $paddingLength = 0,
		int $version = 1
	) : string
	{
		return chr( $version & 0xFF )
			   . chr( $type & 0xFF )
			   . chr( ($requestId >> 8) & 0xFF )
			   . chr( $requestId & 0xFF )
			   . chr( ($contentLength >> 8) & 0xFF )
			   . chr( $contentLength & 0xFF )
			   . chr( $paddingLength & 0xFF )
			   . chr( 0 );
	}

	private function record( int $type, int $requestId, string $content, int $paddingLength = 0 ) : string
	{
		return $this->header( $type, $requestId, strlen( $content ), $paddingLength )
			   . $content
			   . str_repeat( chr( 0 ), $paddingLength );
	}

	private function endRequest( int $requestId ) : string
	{
		return $this->record( self::END_REQUEST, $requestId, str_repeat( chr( 0 ), 8 ) );
	}
}
