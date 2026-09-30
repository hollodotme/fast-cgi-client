<?php declare(strict_types=1);

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
	private const HEADER_PATTERN        = '#^([^:\s][^:]*):(.*)$#';

	private const STATUS_LINE_PATTERN   = '#^HTTP/\d+(?:\.\d+)?\s+(\d{3})(?:\s+(.*))?$#';

	private const STATUS_CODE_PATTERN   = '#^\s*(\d{3})(?:\s|$)#';

	private const LINE_BREAK_PATTERN    = '#\r?\n#';

	private const BLANK_LINE_PATTERN    = '#\r?\n\r?\n#';

	private const DEFAULT_STATUS_CODE   = 200;

	/** @var array<string, array<int, string>> */
	private array $normalizedHeaders = [];

	/** @var array<string, array<int, string>> */
	private array $headers = [];

	private string $body = '';

	public function __construct( private string $output, private string $error, private float $duration )
	{
		$this->parseHeadersAndBody();
	}

	/**
	 * The headers are separated from the body by the first blank line.
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
		$headers           = $this->parseHeaderBlock( substr( $this->output, 0, $blankLinePosition ) );

		if ( null === $headers )
		{
			return;
		}

		foreach ( $headers as [$headerKey, $headerValue] )
		{
			$this->addRawHeader( $headerKey, $headerValue );
			$this->addNormalizedHeader( $headerKey, $headerValue );
		}

		$this->body = substr( $this->output, $blankLinePosition + strlen( $matches[0][0] ) );
	}

	/**
	 * @return array<int, array{0: string, 1: string}>|null NULL, if the block contains a line that is not a header
	 */
	private function parseHeaderBlock( string $headerBlock ) : ?array
	{
		$headers = [];

		if ( '' === $headerBlock )
		{
			return $headers;
		}

		foreach ( (array)preg_split( self::LINE_BREAK_PATTERN, $headerBlock ) as $index => $line )
		{
			$line = (string)$line;

			# Some FastCGI servers start the response with a HTTP status line instead of a Status header
			if ( 0 === $index && 1 === preg_match( self::STATUS_LINE_PATTERN, $line, $matches ) )
			{
				$headers[] = ['Status', trim( $matches[1] . ' ' . ($matches[2] ?? '') )];
				continue;
			}

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

	/**
	 * Returns the status code of the Status header, or 200 if there is no Status header,
	 * as defined by the CGI specification.
	 */
	public function getStatusCode() : int
	{
		$status = $this->normalizedHeaders['status'][0] ?? '';

		if ( 1 === preg_match( self::STATUS_CODE_PATTERN, $status, $matches ) )
		{
			return (int)$matches[1];
		}

		return self::DEFAULT_STATUS_CODE;
	}
}
