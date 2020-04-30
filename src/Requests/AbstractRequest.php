<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2020 Holger Woltersdorf & Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\ServerProtocol;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use function strlen;

/**
 * Class AbstractRequest
 * @package hollodotme\FastCGI\Requests
 */
abstract class AbstractRequest implements ProvidesRequestData
{
	/** @var string */
	private $gatewayInterface = 'FastCGI/1.0';

	/** @var string */
	private $scriptFilename;

	/** @var string */
	private $serverSoftware = 'hollodotme/fast-cgi-client';

	/** @var string */
	private $remoteAddress = '192.168.0.1';

	/** @var int */
	private $remotePort = 9985;

	/** @var string */
	private $serverAddress = '127.0.0.1';

	/** @var int */
	private $serverPort = 80;

	/** @var string */
	private $serverName = 'localhost';

	/** @var string */
	private $serverProtocol = ServerProtocol::HTTP_1_1;

	/** @var string */
	private $contentType = 'application/x-www-form-urlencoded';

	/** @var int */
	private $contentLength = 0;

	/** @var string */
	private $content;

	/** @var array<string, mixed> */
	private $customVars = [];

	/** @var string */
	private $requestUri = '';

	/** @var array<callable> */
	private $responseCallbacks = [];

	/** @var array<callable> */
	private $failureCallbacks = [];

	/** @var array<callable> */
	private $passThroughCallbacks = [];

	public function __construct( string $scriptFilename, string $content )
	{
		$this->scriptFilename = $scriptFilename;
		$this->setContent( $content );
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

	public function getContentType() : string
	{
		return $this->contentType;
	}

	public function setContentType( string $contentType ) : void
	{
		$this->contentType = $contentType;
	}

	public function getContent() : string
	{
		return $this->content;
	}

	public function setContent( string $content ) : void
	{
		$this->content       = $content;
		$this->contentLength = strlen( $content );
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function setCustomVar( string $key, $value ) : void
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

	public function getContentLength() : int
	{
		return $this->contentLength;
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
