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

namespace hollodotme\FastCGI\Tests\Unit\Responses;

use hollodotme\FastCGI\Responses\Response;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class ResponseTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetHeaders() : void
	{
		$output = "X-Powered-By: PHP/7.3.0\r\n"
		          . "X-Custom: Header\r\n"
		          . "Set-Cookie: yummy_cookie=choco\r\n"
		          . "Set-Cookie: tasty_cookie=strawberry\r\n"
		          . "Set-cookie: delicious_cookie=cherry\r\n"
		          . "Content-type: text/html; charset=UTF-8\r\n"
		          . "\r\n"
		          . 'unit';

		$error    = '';
		$duration = 0.54321;
		$response = new Response( $output, $error, $duration );

		$expectedHeaders = [
			'X-Powered-By' => [
				'PHP/7.3.0',
			],
			'X-Custom'     => [
				'Header',
			],
			'Set-Cookie'   => [
				'yummy_cookie=choco',
				'tasty_cookie=strawberry',
			],
			'Set-cookie'   => [
				'delicious_cookie=cherry',
			],
			'Content-type' => [
				'text/html; charset=UTF-8',
			],
		];

		# All headers
		self::assertSame( $expectedHeaders, $response->getHeaders() );

		# Header values by keys
		self::assertSame( ['PHP/7.3.0'], $response->getHeader( 'X-Powered-By' ) );
		self::assertSame( ['Header'], $response->getHeader( 'X-Custom' ) );
		self::assertSame(
			['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
			$response->getHeader( 'Set-Cookie' )
		);
		self::assertSame( ['text/html; charset=UTF-8'], $response->getHeader( 'Content-type' ) );

		# Header lines by keys
		self::assertSame( 'PHP/7.3.0', $response->getHeaderLine( 'X-Powered-By' ) );
		self::assertSame( 'Header', $response->getHeaderLine( 'X-Custom' ) );
		self::assertSame(
			'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
			$response->getHeaderLine( 'Set-Cookie' )
		);
		self::assertSame( 'text/html; charset=UTF-8', $response->getHeaderLine( 'Content-type' ) );

		# Header values by case-insensitive keys
		self::assertSame( ['PHP/7.3.0'], $response->getHeader( 'x-powered-by' ) );
		self::assertSame( ['Header'], $response->getHeader( 'X-CUSTOM' ) );
		self::assertSame(
			['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
			$response->getHeader( 'Set-cookie' )
		);
		self::assertSame( ['text/html; charset=UTF-8'], $response->getHeader( 'Content-Type' ) );

		# Header lines by case-insensitive keys
		self::assertSame( 'PHP/7.3.0', $response->getHeaderLine( 'x-powered-by' ) );
		self::assertSame( 'Header', $response->getHeaderLine( 'X-CUSTOM' ) );
		self::assertSame(
			'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
			$response->getHeaderLine( 'Set-cookie' )
		);
		self::assertSame( 'text/html; charset=UTF-8', $response->getHeaderLine( 'Content-Type' ) );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetBody() : void
	{
		$output   = "X-Powered-By: PHP/7.1.0\r\n"
		            . "X-Custom: Header\r\n"
		            . "Content-type: text/html; charset=UTF-8\r\n"
		            . "\r\n"
		            . "unit\r\n"
		            . 'test';
		$error    = '';
		$duration = 0.54321;
		$response = new Response( $output, $error, $duration );

		$expectedBody = "unit\r\ntest";

		self::assertSame( $expectedBody, $response->getBody() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetOutput() : void
	{
		$output   = "X-Powered-By: PHP/7.1.0\r\n"
		            . "X-Custom: Header\r\n"
		            . "Content-type: text/html; charset=UTF-8\r\n"
		            . "\r\n"
		            . "unit\r\n"
		            . 'test';
		$error    = '';
		$duration = 0.54321;
		$response = new Response( $output, $error, $duration );

		self::assertSame( $output, $response->getOutput() );
		self::assertSame( $duration, $response->getDuration() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanGetError() : void
	{
		$output   = "Status: 404 Not Found\r\n"
		            . "X-Powered-By: PHP/7.1.0\r\n"
		            . "X-Custom: Header\r\n"
		            . "Content-type: text/html; charset=UTF-8\r\n"
		            . "\r\n"
		            . 'File not found.';
		$error    = 'Primary script unknown';
		$duration = 0.54321;
		$response = new Response( $output, $error, $duration );

		self::assertSame( $output, $response->getOutput() );
		self::assertSame( 'File not found.', $response->getBody() );
		self::assertSame( $error, $response->getError() );
		self::assertSame( $duration, $response->getDuration() );
	}

	/**
	 * @param string                            $output
	 * @param array<string, array<int, string>> $expectedHeaders
	 * @param string                            $expectedBody
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider outputProvider
	 */
	public function testCanParseHeadersAndBody( string $output, array $expectedHeaders, string $expectedBody ) : void
	{
		$response = new Response( $output, '', 0.1 );

		self::assertSame( $expectedHeaders, $response->getHeaders() );
		self::assertSame( $expectedBody, $response->getBody() );
		self::assertSame( $output, $response->getOutput() );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function outputProvider() : array
	{
		return [
			'CGI headers'                      => [
				'output'          => "Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\nLine 1\r\nLine 2",
				'expectedHeaders' => ['Status' => ['404 Not Found'], 'Content-Type' => ['text/plain']],
				'expectedBody'    => "Line 1\r\nLine 2",
			],
			# The status of a FastCGI response is a Status header, a HTTP status line is not a header
			'HTTP status line'                 => [
				'output'          => "HTTP/1.1 404 Not Found\r\nContent-Type: text/plain\r\n\r\nLine 1\r\nLine 2",
				'expectedHeaders' => [],
				'expectedBody'    => "HTTP/1.1 404 Not Found\r\nContent-Type: text/plain\r\n\r\nLine 1\r\nLine 2",
			],
			# Before, the first two lines of an output without headers were lost
			'no headers'                       => [
				'output'          => "Line 1\nLine 2\nLine 3",
				'expectedHeaders' => [],
				'expectedBody'    => "Line 1\nLine 2\nLine 3",
			],
			'no headers, but a blank line'     => [
				'output'          => "Line 1\n\nLine 3",
				'expectedHeaders' => [],
				'expectedBody'    => "Line 1\n\nLine 3",
			],
			'headers without blank line'       => [
				'output'          => 'Content-Type: text/plain',
				'expectedHeaders' => [],
				'expectedBody'    => 'Content-Type: text/plain',
			],
			'empty header block'               => [
				'output'          => "\r\n\r\nLine 1",
				'expectedHeaders' => [],
				'expectedBody'    => 'Line 1',
			],
			'headers only'                     => [
				'output'          => "Content-Type: text/plain\r\n\r\n",
				'expectedHeaders' => ['Content-Type' => ['text/plain']],
				'expectedBody'    => '',
			],
			# Before, the line endings had to match PHP_EOL
			'line feeds only'                  => [
				'output'          => "Content-Type: text/plain\n\nLine 1\nLine 2",
				'expectedHeaders' => ['Content-Type' => ['text/plain']],
				'expectedBody'    => "Line 1\nLine 2",
			],
			'body with colons and blank lines' => [
				'output'          => "Content-Type: text/plain\r\n\r\nTime: 12:00\r\n\r\nX-Not: a header",
				'expectedHeaders' => ['Content-Type' => ['text/plain']],
				'expectedBody'    => "Time: 12:00\r\n\r\nX-Not: a header",
			],
			'binary body'                      => [
				'output'          => "Content-Type: application/octet-stream\r\n\r\n\x00\x01\r\n\r\n\xff",
				'expectedHeaders' => ['Content-Type' => ['application/octet-stream']],
				'expectedBody'    => "\x00\x01\r\n\r\n\xff",
			],
			'folded header value'              => [
				'output'          => "X-Long: first part\r\n  second part\r\nContent-Type: text/plain\r\n\r\nLine 1",
				'expectedHeaders' => ['X-Long' => ['first part second part'], 'Content-Type' => ['text/plain']],
				'expectedBody'    => 'Line 1',
			],
			'header without value'             => [
				'output'          => "X-Empty:\r\n\r\nLine 1",
				'expectedHeaders' => ['X-Empty' => ['']],
				'expectedBody'    => 'Line 1',
			],
		];
	}
}
