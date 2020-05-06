<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\RequestContents;

use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class UrlEncodedFormDataTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testGetContentType() : void
	{
		$this->assertSame( 'application/x-www-form-urlencoded', (new UrlEncodedFormData( [] ))->getContentType() );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testGetContent() : void
	{
		$formData        = ['unit' => 'test', 'test' => 'unit'];
		$expectedContent = 'unit=test&test=unit';

		$this->assertSame( $expectedContent, (new UrlEncodedFormData( $formData ))->getContent() );
	}
}
