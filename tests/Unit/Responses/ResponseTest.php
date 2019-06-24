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
		$this->assertSame( $expectedHeaders, $response->getHeaders() );

		# Header values by keys
		$this->assertSame( ['PHP/7.3.0'], $response->getHeader( 'X-Powered-By' ) );
		$this->assertSame( ['Header'], $response->getHeader( 'X-Custom' ) );
		$this->assertSame(
			['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
			$response->getHeader( 'Set-Cookie' )
		);
		$this->assertSame( ['text/html; charset=UTF-8'], $response->getHeader( 'Content-type' ) );

		# Header lines by keys
		$this->assertSame( 'PHP/7.3.0', $response->getHeaderLine( 'X-Powered-By' ) );
		$this->assertSame( 'Header', $response->getHeaderLine( 'X-Custom' ) );
		$this->assertSame(
			'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
			$response->getHeaderLine( 'Set-Cookie' )
		);
		$this->assertSame( 'text/html; charset=UTF-8', $response->getHeaderLine( 'Content-type' ) );

		# Header values by case-insensitive keys
		$this->assertSame( ['PHP/7.3.0'], $response->getHeader( 'x-powered-by' ) );
		$this->assertSame( ['Header'], $response->getHeader( 'X-CUSTOM' ) );
		$this->assertSame(
			['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
			$response->getHeader( 'Set-cookie' )
		);
		$this->assertSame( ['text/html; charset=UTF-8'], $response->getHeader( 'Content-Type' ) );

		# Header lines by case-insensitive keys
		$this->assertSame( 'PHP/7.3.0', $response->getHeaderLine( 'x-powered-by' ) );
		$this->assertSame( 'Header', $response->getHeaderLine( 'X-CUSTOM' ) );
		$this->assertSame(
			'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
			$response->getHeaderLine( 'Set-cookie' )
		);
		$this->assertSame( 'text/html; charset=UTF-8', $response->getHeaderLine( 'Content-Type' ) );
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

		$this->assertSame( $expectedBody, $response->getBody() );
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

		$this->assertSame( $output, $response->getOutput() );
		$this->assertSame( $duration, $response->getDuration() );
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

		$this->assertSame( $output, $response->getOutput() );
		$this->assertSame( 'File not found.', $response->getBody() );
		$this->assertSame( $error, $response->getError() );
		$this->assertSame( $duration, $response->getDuration() );
	}
}
