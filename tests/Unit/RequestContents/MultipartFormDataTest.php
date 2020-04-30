<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\RequestContents;

use hollodotme\FastCGI\RequestContents\MultipartFormData;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class MultipartFormDataTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function testAddFile() : void
	{
		$formData = ['unit' => 'test'];

		$multipartFormData = new MultipartFormData( $formData, [] );
		$multipartFormData->addFile( 'testFile', __DIR__ . '/_files/TestFile.txt' );

		$expectedContent = "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"unit\"\r\n\r\n"
		                   . "test\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"testFile\"; filename=\"TestFile.txt\"\r\n"
		                   . "Content-Type: application/octet-stream\r\n"
		                   . "Content-Transfer-Encoding: base64\r\n\r\n"
		                   . "VGhpcyBpcyBhIHRlc3RmaWxl\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__--\r\n\r\n";

		$this->assertSame( $expectedContent, $multipartFormData->getContent() );
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testGetContent() : void
	{
		$formData = ['unit' => 'test'];
		$files    = ['testFile' => __DIR__ . '/_files/TestFile.txt'];

		$multipartFormData = new MultipartFormData( $formData, $files );

		$expectedContent = "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"unit\"\r\n\r\n"
		                   . "test\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"testFile\"; filename=\"TestFile.txt\"\r\n"
		                   . "Content-Type: application/octet-stream\r\n"
		                   . "Content-Transfer-Encoding: base64\r\n\r\n"
		                   . "VGhpcyBpcyBhIHRlc3RmaWxl\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__--\r\n\r\n";

		$this->assertSame( $expectedContent, $multipartFormData->getContent() );
	}

	public function testConstructorThrowsExceptionIfFileDoesNotExist() : void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'File does not exist: /unknown/file.txt' );

		new MultipartFormData( [], ['testFile' => '/unknown/file.txt'] );
	}

	public function testAddFileThrowsExceptionIfFileDoesNotExist() : void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'File does not exist: /unknown/file.txt' );

		$multipartFormData = new MultipartFormData( [], [] );
		$multipartFormData->addFile( 'testFile', '/unknown/file.txt' );
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function testGetContentType() : void
	{
		$this->assertSame(
			'multipart/form-data; boundary=__X_FASTCGI_CLIENT_BOUNDARY__',
			(new MultipartFormData( [], [] ))->getContentType()
		);
	}
}
