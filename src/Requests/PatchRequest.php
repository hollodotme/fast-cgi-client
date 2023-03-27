<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\RequestMethod;

/**
 * Class PatchRequest
 * @package hollodotme\FastCGI\Requests
 */
class PatchRequest extends AbstractRequest
{
	public function getRequestMethod() : string
	{
		return RequestMethod::PATCH;
	}
}
