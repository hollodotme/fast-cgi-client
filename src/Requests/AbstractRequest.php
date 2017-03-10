<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016 Holger Woltersdorf
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

	/** @var array */
	private $customVars = [];

	/** @var array|callable[] */
	private $responseCallbacks = [];

	/** @var array|callable[] */
	private $failureCallbacks = [];

	public function __construct( string $scriptFilename, string $content )
	{
		$this->scriptFilename = $scriptFilename;
		$this->setContent( $content );
	}

	public function getServerSoftware() : string
	{
		return $this->serverSoftware;
	}

	public function setServerSoftware( string $serverSoftware )
	{
		$this->serverSoftware = $serverSoftware;
	}

	public function getRemoteAddress() : string
	{
		return $this->remoteAddress;
	}

	public function setRemoteAddress( string $remoteAddress )
	{
		$this->remoteAddress = $remoteAddress;
	}

	public function getRemotePort() : int
	{
		return $this->remotePort;
	}

	public function setRemotePort( int $remotePort )
	{
		$this->remotePort = $remotePort;
	}

	public function getServerAddress() : string
	{
		return $this->serverAddress;
	}

	public function setServerAddress( string $serverAddress )
	{
		$this->serverAddress = $serverAddress;
	}

	public function getServerPort() : int
	{
		return $this->serverPort;
	}

	public function setServerPort( int $serverPort )
	{
		$this->serverPort = $serverPort;
	}

	public function getServerName() : string
	{
		return $this->serverName;
	}

	public function setServerName( string $serverName )
	{
		$this->serverName = $serverName;
	}

	public function getServerProtocol() : string
	{
		return $this->serverProtocol;
	}

	public function setServerProtocol( string $serverProtocol )
	{
		$this->serverProtocol = $serverProtocol;
	}

	public function getContentType() : string
	{
		return $this->contentType;
	}

	public function setContentType( string $contentType )
	{
		$this->contentType = $contentType;
	}

	public function getContent() : string
	{
		return $this->content;
	}

	public function setContent( string $content )
	{
		$this->content       = $content;
		$this->contentLength = strlen( $content );
	}

	public function setCustomVar( string $key, $value )
	{
		$this->customVars[ $key ] = $value;
	}

	public function addCustomVars( array $vars )
	{
		$this->customVars = array_merge( $this->customVars, $vars );
	}

	public function resetCustomVars()
	{
		$this->customVars = [];
	}

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

	public function getParams() : array
	{
		return array_merge(
			$this->customVars,
			[
				'GATEWAY_INTERFACE' => $this->getGatewayInterface(),
				'REQUEST_METHOD'    => $this->getRequestMethod(),
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

	public function getResponseCallbacks() : array
	{
		return $this->responseCallbacks;
	}

	public function addResponseCallbacks( callable ...$callbacks )
	{
		$this->responseCallbacks = array_merge( $this->responseCallbacks, $callbacks );
	}

	public function getFailureCallbacks() : array
	{
		return $this->failureCallbacks;
	}

	public function addFailureCallbacks( callable  ...$callbacks )
	{
		$this->failureCallbacks = array_merge( $this->failureCallbacks, $callbacks );
	}
}
