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

namespace hollodotme\FastCGI\Tests\Unit\Requests;

use hollodotme\FastCGI\Requests\AbstractRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class AbstractRequestTest extends TestCase
{
	/**
	 * @param string $requestMethod
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider requestMethodProvider
	 */
	public function testCanGetDefaultValues( string $requestMethod ) : void
	{
		$request = $this->getRequest( $requestMethod, '/path/to/script.php', 'Unit-Test' );

		$this->assertSame( 'FastCGI/1.0', $request->getGatewayInterface() );
		$this->assertSame( '/path/to/script.php', $request->getScriptFilename() );
		$this->assertSame( 'Unit-Test', $request->getContent() );
		$this->assertSame( 9, $request->getContentLength() );
		$this->assertSame( '127.0.0.1', $request->getServerAddress() );
		$this->assertSame( 'localhost', $request->getServerName() );
		$this->assertSame( 'hollodotme/fast-cgi-client', $request->getServerSoftware() );
		$this->assertSame( 80, $request->getServerPort() );
		$this->assertSame( 'HTTP/1.1', $request->getServerProtocol() );
		$this->assertSame( '192.168.0.1', $request->getRemoteAddress() );
		$this->assertSame( 9985, $request->getRemotePort() );
		$this->assertSame( $requestMethod, $request->getRequestMethod() );
		$this->assertSame( 'application/x-www-form-urlencoded', $request->getContentType() );
		$this->assertSame( [], $request->getCustomVars() );
		$this->assertSame( '', $request->getRequestUri() );
	}

	private function getRequest( string $requestMethod, string $scriptFilename, string $content ) : AbstractRequest
	{
		return new class($requestMethod, $scriptFilename, $content) extends AbstractRequest
		{
			/** @var string */
			private $requestMethod;

			public function __construct( string $requestMethod, string $scriptFilename, string $content )
			{
				parent::__construct( $scriptFilename, $content );
				$this->requestMethod = $requestMethod;
			}

			public function getRequestMethod() : string
			{
				return $this->requestMethod;
			}
		};
	}

	public function requestMethodProvider() : array
	{
		return [
			[ 'GET' ],
			[ 'POST' ],
			[ 'PUT' ],
			[ 'PATCH' ],
			[ 'DELETE' ],
		];
	}

	/**
	 * @param string $requestMethod
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @dataProvider requestMethodProvider
	 */
	public function testCanGetParametersArray( string $requestMethod ) : void
	{
		$request = $this->getRequest( $requestMethod, '/path/to/script.php', 'Unit-Test' );
		$request->setCustomVar( 'UNIT', 'Test' );
		$request->setRequestUri( '/unit/test/' );

		$expectedParams = [
			'UNIT'              => 'Test',
			'GATEWAY_INTERFACE' => 'FastCGI/1.0',
			'REQUEST_METHOD'    => $requestMethod,
			'REQUEST_URI'       => '/unit/test/',
			'SCRIPT_FILENAME'   => '/path/to/script.php',
			'SERVER_SOFTWARE'   => 'hollodotme/fast-cgi-client',
			'REMOTE_ADDR'       => '192.168.0.1',
			'REMOTE_PORT'       => 9985,
			'SERVER_ADDR'       => '127.0.0.1',
			'SERVER_PORT'       => 80,
			'SERVER_NAME'       => 'localhost',
			'SERVER_PROTOCOL'   => 'HTTP/1.1',
			'CONTENT_TYPE'      => 'application/x-www-form-urlencoded',
			'CONTENT_LENGTH'    => 9,
		];

		$this->assertSame( $expectedParams, $request->getParams() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testContentLengthChangesWithContent() : void
	{
		$request = $this->getRequest( 'GET', '/path/to/script.php', 'Some content' );

		$this->assertSame( 12, $request->getContentLength() );

		$request->setContent( 'Some new content' );

		$this->assertSame( 16, $request->getContentLength() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanOverwriteVars() : void
	{
		$request = $this->getRequest( 'POST', '/path/to/script.php', 'Unit-Test' );
		$request->setRemoteAddress( '10.100.10.1' );
		$request->setRemotePort( 8599 );
		$request->setServerSoftware( 'unit/test' );
		$request->setServerAddress( '127.0.0.2' );
		$request->setServerPort( 443 );
		$request->setServerName( 'www.fast-cgi-client.de' );
		$request->setServerProtocol( 'HTTP/1.0' );
		$request->setContentType( 'text/plain' );
		$request->setRequestUri( '/path/to/handler' );
		$request->setCustomVar( 'UNIT', 'Test' );
		$request->addCustomVars(
			[
				'UNIT' => 'Testing',
			]
		);

		$expectedParams = [
			'UNIT'              => 'Testing',
			'GATEWAY_INTERFACE' => 'FastCGI/1.0',
			'REQUEST_METHOD'    => 'POST',
			'REQUEST_URI'       => '/path/to/handler',
			'SCRIPT_FILENAME'   => '/path/to/script.php',
			'SERVER_SOFTWARE'   => 'unit/test',
			'REMOTE_ADDR'       => '10.100.10.1',
			'REMOTE_PORT'       => 8599,
			'SERVER_ADDR'       => '127.0.0.2',
			'SERVER_PORT'       => 443,
			'SERVER_NAME'       => 'www.fast-cgi-client.de',
			'SERVER_PROTOCOL'   => 'HTTP/1.0',
			'CONTENT_TYPE'      => 'text/plain',
			'CONTENT_LENGTH'    => 9,
		];

		$this->assertSame( $expectedParams, $request->getParams() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanResetCustomVars() : void
	{
		$request = $this->getRequest( 'POST', '/path/to/script.php', 'Unit-Test' );
		$request->setCustomVar( 'UNIT', 'Test' );

		$this->assertSame( [ 'UNIT' => 'Test' ], $request->getCustomVars() );

		$request->resetCustomVars();

		$this->assertSame( [], $request->getCustomVars() );
	}
}
