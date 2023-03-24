<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

/**
 * Interface ProvidesResponseData
 * @package hollodotme\FastCGI\Interfaces
 */
interface ProvidesResponseData
{
	/**
	 * @return array<string, array<int,string>>
	 */
	public function getHeaders() : array;

	/**
	 * @param string $headerKey
	 *
	 * @return array<int, string>
	 */
	public function getHeader( string $headerKey ) : array;

	public function getHeaderLine( string $headerKey ) : string;

	public function getBody() : string;

	public function getOutput() : string;

	public function getError() : string;

	public function getDuration() : float;
}
