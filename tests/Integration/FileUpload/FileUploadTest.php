<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\FileUpload;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\RequestContents\MultipartFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;
use function basename;
use function dirname;
use function filesize;
use function sys_get_temp_dir;
use function unlink;

final class FileUploadTest extends TestCase
{
	use SocketDataProviding;

	/** @var NetworkSocket */
	private $connection;

	/** @var Client */
	private $client;

	protected function setUp() : void
	{
		$this->connection = new NetworkSocket( $this->getNetworkSocketHost(), $this->getNetworkSocketPort() );
		$this->client     = new Client();
	}

	protected function tearDown() : void
	{
	}

	/**
	 * @param array<string, string> $files
	 *
	 * @throws ConnectException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws \InvalidArgumentException
	 *
	 * @dataProvider filesProvider
	 */
	public function testCanUploadFiles( array $files ) : void
	{
		$formData = [
			'testKey1' => 'value1',
			'testKey2' => 'value2',
		];

		$multipartFormData = new MultipartFormData( $formData, $files );
		$postRequest       = PostRequest::newWithRequestContent(
			dirname( __DIR__ ) . '/Workers/fileUploadWorker.php',
			$multipartFormData
		);

		$response = $this->client->sendRequest( $this->connection, $postRequest );

		$expectedBody = "POST data:\n"
		                . "KEY: testKey1\n"
		                . "VALUE: value1\n\n"
		                . "KEY: testKey2\n"
		                . "VALUE: value2\n\n"
		                . "Uploaded files:\n";

		foreach ( $files as $key => $filePath )
		{
			$fileName   = basename( $filePath );
			$fileSize   = filesize( $filePath );
			$targetPath = sys_get_temp_dir() . '/' . $fileName;

			$expectedBody .= "KEY: {$key}\n"
			                 . "FILENAME: {$fileName}\n"
			                 . "SIZE: {$fileSize}\n"
			                 . "Moved to {$targetPath}\n\n";

			self::assertFileEquals( $targetPath, $filePath );

			@unlink( $targetPath );
		}

		self::assertSame( $expectedBody, $response->getBody() );
	}

	/**
	 * @return array<array<string,array<string, string>>>
	 */
	public function filesProvider() : array
	{
		return [
			[
				'files' => [
					'textFile' => dirname( __DIR__ ) . '/_files/TestFile.txt',
				],
			],
			[
				'files' => [
					'image' => dirname( __DIR__ ) . '/_files/php-logo.png',
				],
			],
			[
				'files' => [
					'textFile' => dirname( __DIR__ ) . '/_files/TestFile.txt',
					'image'    => dirname( __DIR__ ) . '/_files/php-logo.png',
				],
			],
		];
	}
}
