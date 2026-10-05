<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\RequestMethod;

/**
 * Class PutRequest
 * @package hollodotme\FastCGI\Requests
 */
class PutRequest extends AbstractRequest
{
	public function getRequestMethod() : string
	{
		return RequestMethod::PUT;
	}
}
