<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

/**
 * Interface ProvidesRequestData
 * @package hollodotme\FastCGI\Interfaces
 */
interface ProvidesRequestData
{
	public function getGatewayInterface() : string;

	public function getRequestMethod() : string;

	public function getScriptFilename() : string;

	public function getServerSoftware() : string;

	public function getRemoteAddress() : string;

	public function getRemotePort() : int;

	public function getServerAddress() : string;

	public function getServerPort() : int;

	public function getServerName() : string;

	public function getServerProtocol() : string;

	public function getContentType() : string;

	public function getContentLength() : int;

	public function getContent() : string;

	/**
	 * @return array<string, mixed>
	 */
	public function getCustomVars() : array;

	/**
	 * @return array<string, mixed>
	 */
	public function getParams() : array;

	public function getRequestUri() : string;

	/**
	 * @return array<callable>
	 */
	public function getResponseCallbacks() : array;

	/**
	 * @return array<callable>
	 */
	public function getFailureCallbacks() : array;

	/**
	 * @return array<callable>
	 */
	public function getPassThroughCallbacks() : array;
}
