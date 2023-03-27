<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\RequestContents;

use hollodotme\FastCGI\RequestContents\MultipartFormData;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use function file_get_contents;

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
		$multipartFormData->addFile( 'textFile', __DIR__ . '/_files/TestFile.txt' );
		$multipartFormData->addFile( 'image', __DIR__ . '/_files/php-logo.png' );

		$expectedContent = "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"unit\"\r\n\r\n"
		                   . "test\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"textFile\"; filename=\"TestFile.txt\"\r\n"
		                   . "Content-Type: text/plain\r\n\r\n"
		                   . "This is a testfile\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"image\"; filename=\"php-logo.png\"\r\n"
		                   . "Content-Type: image/png\r\n\r\n"
		                   . file_get_contents( __DIR__ . '/_files/php-logo.png' ) . "\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__--\r\n\r\n";

		self::assertSame( $expectedContent, $multipartFormData->toString() );
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testGetContent() : void
	{
		$formData = ['unit' => 'test'];
		$files    = [
			'textFile' => __DIR__ . '/_files/TestFile.txt',
			'image'    => __DIR__ . '/_files/php-logo.png',
		];

		$multipartFormData = new MultipartFormData( $formData, $files );

		$expectedContent = "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"unit\"\r\n\r\n"
		                   . "test\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"textFile\"; filename=\"TestFile.txt\"\r\n"
		                   . "Content-Type: text/plain\r\n\r\n"
		                   . "This is a testfile\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__\r\n"
		                   . "Content-Disposition: form-data; name=\"image\"; filename=\"php-logo.png\"\r\n"
		                   . "Content-Type: image/png\r\n\r\n"
		                   . file_get_contents( __DIR__ . '/_files/php-logo.png' ) . "\r\n"
		                   . "--__X_FASTCGI_CLIENT_BOUNDARY__--\r\n\r\n";

		self::assertSame( $expectedContent, $multipartFormData->toString() );
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
		self::assertSame(
			'multipart/form-data; boundary=__X_FASTCGI_CLIENT_BOUNDARY__',
			(new MultipartFormData( [], [] ))->getContentType()
		);
	}
}
