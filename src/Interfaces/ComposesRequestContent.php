<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

interface ComposesRequestContent
{
	public function getContentType() : string;

	public function getContent() : string;
}