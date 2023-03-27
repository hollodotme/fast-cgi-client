<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Requests;

use hollodotme\FastCGI\Constants\RequestMethod;

/**
 * Class DeleteRequest
 * @package hollodotme\FastCGI\Requests
 */
class DeleteRequest extends AbstractRequest
{
    public function getRequestMethod(): string
    {
        return RequestMethod::DELETE;
    }
}
