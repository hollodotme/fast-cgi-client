<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2019 Holger Woltersdorf & Contributors
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
use function array_slice;
use function implode;
use function strtolower;
use function trim;

/**
 * Class Response
 * @package hollodotme\FastCGI\Responses
 */
class Response implements ProvidesResponseData
{
	private const HEADER_PATTERN = '#^([^\:]+):(.*)$#';

	/** @var array */
	private $normalizedHeaders;

	/** @var array */
	private $headers;

	/** @var string */
	private $body;

	/** @var string */
	private $output;

	/** @var string */
	private $error;

	/** @var float */
	private $duration;

	public function __construct( string $output, string $error, float $duration )
	{
		$this->output            = $output;
		$this->error             = $error;
		$this->duration          = $duration;
		$this->normalizedHeaders = [];
		$this->headers           = [];
		$this->body              = '';

		$this->parseHeadersAndBody();
	}

	private function parseHeadersAndBody() : void
	{
		$lines  = explode( PHP_EOL, $this->output );
		$offset = 0;

		foreach ( $lines as $i => $line )
		{
			$matches = [];
			if ( !preg_match( self::HEADER_PATTERN, $line, $matches ) )
			{
				break;
			}

			$offset      = $i;
			$headerKey   = trim( $matches[1] );
			$headerValue = trim( $matches[2] );

			$this->addRawHeader( $headerKey, $headerValue );
			$this->addNormalizedHeader( $headerKey, $headerValue );
		}

		$this->body = implode( PHP_EOL, array_slice( $lines, $offset + 2 ) );
	}

	private function addRawHeader( string $headerKey, string $headerValue ) : void
	{
		if ( !isset( $this->headers[ $headerKey ] ) )
		{
			$this->headers[ $headerKey ] = [$headerValue];

			return;
		}

		$this->headers[ $headerKey ][] = $headerValue;
	}

	private function addNormalizedHeader( string $headerKey, string $headerValue ) : void
	{
		$key = strtolower( $headerKey );

		if ( !isset( $this->normalizedHeaders[ $key ] ) )
		{
			$this->normalizedHeaders[ $key ] = [$headerValue];

			return;
		}

		$this->normalizedHeaders[ $key ][] = $headerValue;
	}

	public function getHeader( string $headerKey ) : array
	{
		return $this->normalizedHeaders[ strtolower( $headerKey ) ] ?? [];
	}

	public function getHeaderLine( string $headerKey ) : string
	{
		return implode( ', ', $this->getHeader( $headerKey ) );
	}

	public function getHeaders() : array
	{
		return $this->headers;
	}

	public function getBody() : string
	{
		return $this->body;
	}

	public function getOutput() : string
	{
		return $this->output;
	}

	public function getError() : string
	{
		return $this->error;
	}

	public function getDuration() : float
	{
		return $this->duration;
	}
}
