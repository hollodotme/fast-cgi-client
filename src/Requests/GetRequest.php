<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\RequestMethod;

/**
 * Class GetRequest
 * @package hollodotme\FastCGI\Requests
 */
class GetRequest extends AbstractRequest
{
	public function getRequestMethod() : string
	{
		return RequestMethod::GET;
	}
}
