<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Requests;

use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\GetRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class GetRequestTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testRequestMethodIsGet() : void
	{
		$request = new GetRequest( '/path/to/script.php', 'Unit-Test' );

		self::assertSame( 'GET', $request->getRequestMethod() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testCanCreateInstanceWithRequestContent() : void
	{
		$urlEncodedContent = new UrlEncodedFormData(
			[
				'unit' => 'test',
				'test' => 'unit',
			]
		);

		$request = GetRequest::newWithRequestContent( '/path/to/script.php', $urlEncodedContent );

		self::assertSame( 'application/x-www-form-urlencoded', $request->getContentType() );
		self::assertSame( 'unit=test&test=unit', $request->getContent() );
	}
}
