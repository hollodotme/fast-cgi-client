<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\RequestContents;

use hollodotme\FastCGI\RequestContents\JsonData;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class JsonDataTest extends TestCase
{
	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 */
	public function testGetContent() : void
	{
		self::assertSame( 'application/json', (new JsonData( '' ))->getContentType() );
	}

	/**
	 * @param mixed  $data
	 * @param string $expectedContent
	 *
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 *
	 * @dataProvider jsonDataProvider
	 */
	public function testGetContentType( $data, string $expectedContent ) : void
	{
		self::assertSame( $expectedContent, (new JsonData( $data ))->toString() );
	}

	/**
	 * @return array<array<string, mixed>>
	 */
	public function jsonDataProvider() : array
	{
		return [
			[
				'data'            => 1,
				'expectecContent' => '1',
			],
			[
				'data'            => ['value' => 1],
				'expectedContent' => '{"value":1}',
			],
			[
				'data'            => true,
				'expectedContent' => 'true',
			],
			[
				'data'            => 'Lorem ipsum',
				'expectedContent' => '"Lorem ipsum"',
			],
		];
	}

	/**
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function testGetContentThrowsExceptionIfDataCannotBeEncodedAsJson() : void
	{
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Could not encode data to JSON.' );

		$data = ['unit' => ['test' => ['level' => ['three' => ['and' => ['more']]]]]];

		self::assertSame( '', (new JsonData( $data, 0, 3 ))->toString() );
	}
}
