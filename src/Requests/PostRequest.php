<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\RequestMethod;

/**
 * Class PostRequest
 * @package hollodotme\FastCGI\Requests
 */
class PostRequest extends AbstractRequest
{
    public function getRequestMethod(): string
    {
        return RequestMethod::POST;
    }
}
