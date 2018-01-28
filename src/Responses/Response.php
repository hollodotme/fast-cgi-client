<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2018 Holger Woltersdorf
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

namespace hollodotme\FastCGI\Responses;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;

/**
 * Class Response
 * @package hollodotme\FastCGI\Responses
 */
class Response implements ProvidesResponseData
{
	private const HEADER_PATTERN = '#^([^\:]+):(.*)$#';

	/** @var int */
	private $requestId;

	/** @var array */
	private $headers;

	/** @var string */
	private $body;

	/** @var string */
	private $rawResponse;

	/** @var float */
	private $duration;

	public function __construct( int $requestId, string $rawResponse, float $duration )
	{
		$this->requestId   = $requestId;
		$this->rawResponse = $rawResponse;
		$this->duration    = $duration;
		$this->headers     = [];
		$this->body        = '';

		$this->parseHeadersAndBody();
	}

	private function parseHeadersAndBody() : void
	{
		$lines  = explode( PHP_EOL, $this->rawResponse );
		$offset = 0;

		foreach ( $lines as $i => $line )
		{
			if ( preg_match( self::HEADER_PATTERN, $line, $matches ) )
			{
				$offset                               = $i;
				$this->headers[ trim( $matches[1] ) ] = trim( $matches[2] );
				continue;
			}

			break;
		}

		$this->body = implode( PHP_EOL, \array_slice( $lines, $offset + 2 ) );
	}

	public function getRequestId() : int
	{
		return $this->requestId;
	}

	public function getHeader( string $headerKey ) : string
	{
		return $this->headers[ $headerKey ] ?? '';
	}

	public function getHeaders() : array
	{
		return $this->headers;
	}

	public function getBody() : string
	{
		return $this->body;
	}

	public function getRawResponse() : string
	{
		return $this->rawResponse;
	}

	public function getDuration() : float
	{
		return $this->duration;
	}
}
