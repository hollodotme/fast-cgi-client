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

namespace hollodotme\FastCGI\Responses;

use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use function count;
use function implode;
use function preg_match;
use function preg_split;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const PREG_OFFSET_CAPTURE;

/**
 * Class Response
 * @package hollodotme\FastCGI\Responses
 */
class Response implements ProvidesResponseData
{
	private const HEADER_PATTERN     = '#^([^:\s][^:]*):(.*)$#';

	private const LINE_BREAK_PATTERN = '#\r?\n#';

	private const BLANK_LINE_PATTERN = '#\r?\n\r?\n#';

	/** @var array<string, array<int, string>> */
	private $normalizedHeaders;

	/** @var array<string, array<int, string>> */
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

	/**
	 * The headers are separated from the body by the first blank line, independent of the line endings.
	 * If the output does not start with a block of headers, the whole output is the body.
	 */
	private function parseHeadersAndBody() : void
	{
		$this->body = $this->output;

		if ( 1 !== preg_match( self::BLANK_LINE_PATTERN, $this->output, $matches, PREG_OFFSET_CAPTURE ) )
		{
			return;
		}

		$blankLinePosition = $matches[0][1];
		$headers           = $this->parseHeaderBlock( (string)substr( $this->output, 0, $blankLinePosition ) );

		if ( null === $headers )
		{
			return;
		}

		foreach ( $headers as [$headerKey, $headerValue] )
		{
			$this->addRawHeader( $headerKey, $headerValue );
			$this->addNormalizedHeader( $headerKey, $headerValue );
		}

		$this->body = (string)substr( $this->output, $blankLinePosition + strlen( $matches[0][0] ) );
	}

	/**
	 * @param string $headerBlock
	 *
	 * @return array<int, array{0: string, 1: string}>|null NULL, if the block contains a line that is not a header
	 */
	private function parseHeaderBlock( string $headerBlock ) : ?array
	{
		$headers = [];

		if ( '' === $headerBlock )
		{
			return $headers;
		}

		foreach ( (array)preg_split( self::LINE_BREAK_PATTERN, $headerBlock ) as $line )
		{
			$line = (string)$line;

			# A line starting with whitespace continues the value of the previous header (obsolete line folding)
			if ( [] !== $headers && ('' !== $line && ($line[0] === ' ' || $line[0] === "\t")) )
			{
				$lastIndex                = count( $headers ) - 1;
				$headers[ $lastIndex ][1] = trim( $headers[ $lastIndex ][1] . ' ' . trim( $line ) );
				continue;
			}

			if ( 1 !== preg_match( self::HEADER_PATTERN, $line, $matches ) )
			{
				return null;
			}

			$headers[] = [trim( $matches[1] ), trim( $matches[2] )];
		}

		return $headers;
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
