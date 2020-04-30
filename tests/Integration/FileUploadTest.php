<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration;

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
		$this->connection = null;
		$this->client     = null;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws ExpectationFailedException
	 * @throws InvalidArgumentException
	 * @throws Throwable
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function testCanUploadFile() : void
	{
		$formData = [
			'testKey1' => 'value1',
			'testKey2' => 'value2',
		];

		$files = [
			'testFile1' => __DIR__ . '/_files/TestFile.txt',
			'testFile2' => __DIR__ . '/_files/TestFile.txt',
		];

		$multipartFormData = new MultipartFormData( $formData, $files );
		$postRequest       = new PostRequest(
			__DIR__ . '/Workers/fileUploadWorker.php',
			$multipartFormData->getContent()
		);
		$postRequest->setContentType( $multipartFormData->getContentType() );

		$response = $this->client->sendRequest( $this->connection, $postRequest );

		$expectedBody = "POST data:\n"
		                . "KEY: testKey1\n"
		                . "VALUE: value1\n\n"
		                . "KEY: testKey2\n"
		                . "VALUE: value2\n\n"
		                . "Uploaded files:\n"
		                . "KEY: testFile1\n"
		                . "FILENAME: TestFile.txt\n"
		                . "SIZE: 24\n\n"
		                . "KEY: testFile2\n"
		                . "FILENAME: TestFile.txt\n"
		                . "SIZE: 24\n\n";

		$this->assertSame( $expectedBody, $response->getBody() );
	}
}
