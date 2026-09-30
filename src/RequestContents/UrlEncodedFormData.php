<?php declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use function http_build_query;

final class UrlEncodedFormData implements ComposesRequestContent
{
	/**
	 * @param array<string, mixed> $formData
	 */
	public function __construct( private array $formData )
	{
	}

	public function getContentType() : string
	{
		return 'application/x-www-form-urlencoded';
	}

	public function getContent() : string
	{
		return http_build_query( $this->formData );
	}
}
