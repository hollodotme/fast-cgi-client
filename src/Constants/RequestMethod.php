<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Constants;

/**
 * Class RequestMethod
 * @package hollodotme\FastCGI\Constants
 */
abstract class RequestMethod
{
    public const GET    = 'GET';

    public const POST   = 'POST';

    public const PUT    = 'PUT';

    public const PATCH  = 'PATCH';

    public const DELETE = 'DELETE';
}
