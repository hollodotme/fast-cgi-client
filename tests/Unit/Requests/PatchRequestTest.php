<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Requests;

use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PatchRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class PatchRequestTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testRequestMethodIsGet() : void
	{
		$request = new PatchRequest( '/path/to/script.php', 'Unit-Test' );

		self::assertSame( 'PATCH', $request->getRequestMethod() );
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

		$request = PatchRequest::newWithRequestContent( '/path/to/script.php', $urlEncodedContent );

		self::assertSame( 'application/x-www-form-urlencoded', $request->getContentType() );
		self::assertSame( 'unit=test&test=unit', $request->getContent() );
	}
}
