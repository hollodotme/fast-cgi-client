<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\ServerProtocol;
use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use function strlen;

/**
 * Class AbstractRequest
 * @package hollodotme\FastCGI\Requests
 */
abstract class AbstractRequest implements ProvidesRequestData
{
	private string $gatewayInterface = 'FastCGI/1.0';

	private string $scriptFilename;

	private string $serverSoftware = 'hollodotme/fast-cgi-client';

	private string $remoteAddress = '192.168.0.1';

	private int $remotePort = 9985;

	private string $serverAddress = '127.0.0.1';

	private int $serverPort = 80;

	private string $serverName = 'localhost';

	private string $serverProtocol = ServerProtocol::HTTP_1_1;

	private string $contentType = 'application/x-www-form-urlencoded';

	private ?ComposesRequestContent $content;

	/** @var array<string, mixed> */
	private array $customVars = [];

	private string $requestUri = '';

	/** @var array<callable> */
	private array $responseCallbacks = [];

	/** @var array<callable> */
	private array $failureCallbacks = [];

	/** @var array<callable> */
	private array $passThroughCallbacks = [];

	public function __construct( string $scriptFilename, ?ComposesRequestContent $content = null )
	{
		$this->scriptFilename = $scriptFilename;
        $this->content = $content;

        if (null !== $content) {
            $this->contentType = $content->getContentType();
        }
	}

	public function getServerSoftware() : string
	{
		return $this->serverSoftware;
	}

	public function setServerSoftware( string $serverSoftware ) : void
	{
		$this->serverSoftware = $serverSoftware;
	}

	public function getRemoteAddress() : string
	{
		return $this->remoteAddress;
	}

	public function setRemoteAddress( string $remoteAddress ) : void
	{
		$this->remoteAddress = $remoteAddress;
	}

	public function getRemotePort() : int
	{
		return $this->remotePort;
	}

	public function setRemotePort( int $remotePort ) : void
	{
		$this->remotePort = $remotePort;
	}

	public function getServerAddress() : string
	{
		return $this->serverAddress;
	}

	public function setServerAddress( string $serverAddress ) : void
	{
		$this->serverAddress = $serverAddress;
	}

	public function getServerPort() : int
	{
		return $this->serverPort;
	}

	public function setServerPort( int $serverPort ) : void
	{
		$this->serverPort = $serverPort;
	}

	public function getServerName() : string
	{
		return $this->serverName;
	}

	public function setServerName( string $serverName ) : void
	{
		$this->serverName = $serverName;
	}

	public function getServerProtocol() : string
	{
		return $this->serverProtocol;
	}

	public function setServerProtocol( string $serverProtocol ) : void
	{
		$this->serverProtocol = $serverProtocol;
	}

	public function setContentType( string $contentType ) : void
	{
		$this->contentType = $contentType;
	}

	public function getContent() : ?ComposesRequestContent
	{
		return $this->content;
	}

    public function getContentLength() : int
    {
        return $this->content ? strlen($this->content->toString()) : 0;
    }

    public function getContentType() : string
    {
        return $this->contentType;
    }

	public function setCustomVar( string $key, mixed $value ) : void
	{
		$this->customVars[ $key ] = $value;
	}

	/**
	 * @param array<string, mixed> $vars
	 */
	public function addCustomVars( array $vars ) : void
	{
		$this->customVars = array_merge( $this->customVars, $vars );
	}

	public function resetCustomVars() : void
	{
		$this->customVars = [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getCustomVars() : array
	{
		return $this->customVars;
	}

	public function getGatewayInterface() : string
	{
		return $this->gatewayInterface;
	}

	public function getScriptFilename() : string
	{
		return $this->scriptFilename;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getParams() : array
	{
		return array_merge(
			$this->customVars,
			[
				'GATEWAY_INTERFACE' => $this->getGatewayInterface(),
				'REQUEST_METHOD'    => $this->getRequestMethod(),
				'REQUEST_URI'       => $this->getRequestUri(),
				'SCRIPT_FILENAME'   => $this->getScriptFilename(),
				'SERVER_SOFTWARE'   => $this->getServerSoftware(),
				'REMOTE_ADDR'       => $this->getRemoteAddress(),
				'REMOTE_PORT'       => $this->getRemotePort(),
				'SERVER_ADDR'       => $this->getServerAddress(),
				'SERVER_PORT'       => $this->getServerPort(),
				'SERVER_NAME'       => $this->getServerName(),
				'SERVER_PROTOCOL'   => $this->getServerProtocol(),
				'CONTENT_TYPE'      => $this->getContentType(),
				'CONTENT_LENGTH'    => $this->getContentLength(),
			]
		);
	}

	public function getRequestUri() : string
	{
		return $this->requestUri;
	}

	public function setRequestUri( string $requestUri ) : void
	{
		$this->requestUri = $requestUri;
	}

	/**
	 * @return array<callable>
	 */
	public function getResponseCallbacks() : array
	{
		return $this->responseCallbacks;
	}

	public function addResponseCallbacks( callable ...$callbacks ) : void
	{
		$this->responseCallbacks = array_merge( $this->responseCallbacks, $callbacks );
	}

	/**
	 * @return array<callable>
	 */
	public function getFailureCallbacks() : array
	{
		return $this->failureCallbacks;
	}

	public function addFailureCallbacks( callable  ...$callbacks ) : void
	{
		$this->failureCallbacks = array_merge( $this->failureCallbacks, $callbacks );
	}

	/**
	 * @return array<callable>
	 */
	public function getPassThroughCallbacks() : array
	{
		return $this->passThroughCallbacks;
	}

	public function addPassThroughCallbacks( callable ...$callbacks ) : void
	{
		$this->passThroughCallbacks = array_merge( $this->passThroughCallbacks, $callbacks );
	}
}
