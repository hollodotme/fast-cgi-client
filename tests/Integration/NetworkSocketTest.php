<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016 Holger Woltersdorf
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

namespace hollodotme\FastCGI\Tests\Integration;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

/**
 * Class NetworkSocketTest
 * @package hollodotme\FastCGI\Tests\Integration
 */
class NetworkSocketTest extends \PHPUnit\Framework\TestCase
{
	public function testCanSendAsyncRequestAndReceiveRequestId()
	{
		$connection = new NetworkSocket( '127.0.0.1', 9000 );
		$client     = new Client( $connection );
		$content    = http_build_query( [ 'test-key' => 'unit' ] );
		$request    = new PostRequest( __DIR__ . '/Workers/worker.php', $content );

		$requestId = $client->sendAsyncRequest( $request );

		$this->assertGreaterThanOrEqual( 1, $requestId );
		$this->assertLessThanOrEqual( 65535, $requestId );
	}

	public function testCanSendAsyncRequestAndWaitForResponse()
	{
		$connection       = new NetworkSocket( '127.0.0.1', 9000 );
		$client           = new Client( $connection );
		$content          = http_build_query( [ 'test-key' => 'unit' ] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$requestId = $client->sendAsyncRequest( $request );
		$response  = $client->waitForResponse( $requestId );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );
		$this->assertSame( $requestId, $response->getRequestId() );

		$this->assertEquals( $response, $client->waitForResponse( $requestId ) );
	}

	public function testCanSendSyncRequestAndReceiveResponse()
	{
		$connection       = new NetworkSocket( '127.0.0.1', 9000 );
		$client           = new Client( $connection );
		$content          = http_build_query( [ 'test-key' => 'unit' ] );
		$request          = new PostRequest( __DIR__ . '/Workers/worker.php', $content );
		$expectedResponse =
			"X-Powered-By: PHP/7.1.0\r\nX-Custom: Header\r\nContent-type: text/html; charset=UTF-8\r\n\r\nunit";

		$response = $client->sendRequest( $request );

		$this->assertEquals( $expectedResponse, $response->getRawResponse() );
		$this->assertSame( 'unit', $response->getBody() );
		$this->assertGreaterThan( 0, $response->getDuration() );

		$this->assertGreaterThanOrEqual( 1, $response->getRequestId() );
		$this->assertLessThanOrEqual( 65535, $response->getRequestId() );

		$this->assertEquals( $response, $client->waitForResponse( $response->getRequestId() ) );
	}
}
