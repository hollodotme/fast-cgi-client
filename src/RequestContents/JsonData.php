<?php declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use RuntimeException;
use function json_encode;
use const PHP_INT_MAX;

final class JsonData implements ComposesRequestContent
{
	/** @var int<1, max> */
	private int $encodingDepth;

	/**
	 * @param int<1, max> $depth
	 */
	public function __construct( private mixed $data, private int $options = 0, int $depth = 512 )
	{
		$this->encodingDepth = max( 1, min( $depth, PHP_INT_MAX ) );
	}

	public function getContentType() : string
	{
		return 'application/json';
	}

	/**
	 * @throws RuntimeException
	 */
	public function getContent() : string
	{
		$json = json_encode( $this->data, $this->options, $this->encodingDepth );

		if ( false === $json )
		{
			throw new RuntimeException( 'Could not encode data to JSON.' );
		}

		return $json;
	}
}
