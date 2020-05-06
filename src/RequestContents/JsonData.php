<?php declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use RuntimeException;
use function json_encode;

final class JsonData implements ComposesRequestContent
{
	/** @var mixed */
	private $data;

	/** @var int */
	private $encodingOptions;

	/** @var int */
	private $encodingDepth;

	/**
	 * @param mixed $data
	 * @param int   $options
	 * @param int   $depth
	 */
	public function __construct( $data, int $options = 0, int $depth = 512 )
	{
		$this->data            = $data;
		$this->encodingOptions = $options;
		$this->encodingDepth   = $depth;
	}

	public function getContentType() : string
	{
		return 'application/json';
	}

	/**
	 * @return string
	 * @throws RuntimeException
	 */
	public function getContent() : string
	{
		$json = json_encode( $this->data, $this->encodingOptions, $this->encodingDepth );

		if ( false === $json )
		{
			throw new RuntimeException( 'Could not encode data to JSON.' );
		}

		return $json;
	}
}