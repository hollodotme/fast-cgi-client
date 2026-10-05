<?php declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;

final class PlainText implements ComposesRequestContent
{
	public function __construct( private string $plainText )
	{
	}

	public function getContentType() : string
	{
		return 'text/plain';
	}

	public function getContent() : string
	{
		return $this->plainText;
	}
}
