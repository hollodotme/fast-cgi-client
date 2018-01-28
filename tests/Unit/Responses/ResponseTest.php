<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2018 Holger Woltersdorf
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
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
	public function testCanGetHeaders() : void
	{
		$rawResponse = "X-Powered-By: PHP/7.1.0\r\n"
		               . "X-Custom: Header\r\n"
		               . "Content-type: text/html; charset=UTF-8\r\n"
		               . "\r\n"
		               . 'unit';

		$duration = 0.54321;
		$response = new Response( 1234, $rawResponse, $duration );

		$expectedHeaders = [
			'X-Powered-By' => 'PHP/7.1.0',
			'X-Custom'     => 'Header',
			'Content-type' => 'text/html; charset=UTF-8',
		];

		$this->assertSame( $expectedHeaders, $response->getHeaders() );
		$this->assertSame( 'PHP/7.1.0', $response->getHeader( 'X-Powered-By' ) );
		$this->assertSame( 'Header', $response->getHeader( 'X-Custom' ) );
		$this->assertSame( 'text/html; charset=UTF-8', $response->getHeader( 'Content-type' ) );
	}

	public function testCanGetBody() : void
	{
		$rawResponse = "X-Powered-By: PHP/7.1.0\r\n"
		               . "X-Custom: Header\r\n"
		               . "Content-type: text/html; charset=UTF-8\r\n"
		               . "\r\n"
		               . "unit\r\n"
		               . 'test';

		$duration = 0.54321;
		$response = new Response( 1234, $rawResponse, $duration );

		$expectedBody = "unit\r\ntest";

		$this->assertSame( $expectedBody, $response->getBody() );
	}

	public function testCanGetRawResponse() : void
	{
		$rawResponse = "X-Powered-By: PHP/7.1.0\r\n"
		               . "X-Custom: Header\r\n"
		               . "Content-type: text/html; charset=UTF-8\r\n"
		               . "\r\n"
		               . "unit\r\n"
		               . 'test';

		$duration = 0.54321;
		$response = new Response( 1234, $rawResponse, $duration );

		$this->assertSame( $rawResponse, $response->getRawResponse() );
		$this->assertSame( $duration, $response->getDuration() );
		$this->assertSame( 1234, $response->getRequestId() );
	}
}
