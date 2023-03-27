<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use RuntimeException;

use function json_encode;

use const PHP_INT_MAX;

final class JsonData implements ComposesRequestContent
{
    private mixed $data;

    private int $encodingOptions;

    /** @var int<1, max> */
    private int $encodingDepth;

    /**
     * @param mixed $data
     * @param int   $options
     * @param int<1, max>   $depth
     */
    public function __construct(mixed $data, int $options = 0, int $depth = 512)
    {
        $this->data            = $data;
        $this->encodingOptions = $options;
        $this->encodingDepth   = max(1, min($depth, PHP_INT_MAX));
    }

    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    public function getContent(): string
    {
        $json = json_encode($this->data, $this->encodingOptions, $this->encodingDepth);

        if (false === $json) {
            throw new RuntimeException('Could not encode data to JSON.');
        }

        return $json;
    }
}
