<?php declare(strict_types=1);

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

		self::assertSame( 'FastCGI/1.0', $request->getGatewayInterface() );
		self::assertSame( '/path/to/script.php', $request->getScriptFilename() );
		self::assertSame( 'Unit-Test', $request->getContent() );
		self::assertSame( 9, $request->getContentLength() );
		self::assertSame( '127.0.0.1', $request->getServerAddress() );
		self::assertSame( 'localhost', $request->getServerName() );
		self::assertSame( 'hollodotme/fast-cgi-client', $request->getServerSoftware() );
		self::assertSame( 80, $request->getServerPort() );
		self::assertSame( 'HTTP/1.1', $request->getServerProtocol() );
		self::assertSame( '192.168.0.1', $request->getRemoteAddress() );
		self::assertSame( 9985, $request->getRemotePort() );
		self::assertSame( $requestMethod, $request->getRequestMethod() );
		self::assertSame( 'application/x-www-form-urlencoded', $request->getContentType() );
		self::assertSame( [], $request->getCustomVars() );
		self::assertSame( '', $request->getRequestUri() );
	}

	/**
	 * @param string $requestMethod
	 * @param string $scriptFilename
	 * @param string $content
	 *
	 * @return AbstractRequest
	 */
	private function getRequest( string $requestMethod, string $scriptFilename, string $content ) : AbstractRequest
	{
		return new class($requestMethod, $scriptFilename, $content) extends AbstractRequest {
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

	/**
	 * @return array<array<string, string>>
	 */
	public function requestMethodProvider() : array
	{
		return [
			[
				'requestMethod' => 'GET',
			],
			[
				'requestMethod' => 'POST',
			],
			[
				'requestMethod' => 'PUT',
			],
			[
				'requestMethod' => 'PATCH',
			],
			[
				'requestMethod' => 'DELETE',
			],
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

		self::assertSame( $expectedParams, $request->getParams() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testContentLengthChangesWithContent() : void
	{
		$request = $this->getRequest( 'GET', '/path/to/script.php', 'Some content' );

		self::assertSame( 12, $request->getContentLength() );

		$request->setContent( 'Some new content' );

		self::assertSame( 16, $request->getContentLength() );
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

		self::assertSame( $expectedParams, $request->getParams() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanResetCustomVars() : void
	{
		$request = $this->getRequest( 'POST', '/path/to/script.php', 'Unit-Test' );
		$request->setCustomVar( 'UNIT', 'Test' );

		self::assertSame( ['UNIT' => 'Test'], $request->getCustomVars() );

		$request->resetCustomVars();

		self::assertSame( [], $request->getCustomVars() );
	}
}
