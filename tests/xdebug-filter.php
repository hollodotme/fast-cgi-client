<?php

declare(strict_types=1);

if (!function_exists('xdebug_set_filter')) {
    return;
}

if (defined('XDEBUG_PATH_WHITELIST')) {
    xdebug_set_filter(
        XDEBUG_FILTER_CODE_COVERAGE,
        XDEBUG_PATH_WHITELIST,
        [dirname(__DIR__) . '/src']
    );
}

if (defined('XDEBUG_PATH_INCLUDE')) {
    xdebug_set_filter(
        XDEBUG_FILTER_CODE_COVERAGE,
        XDEBUG_PATH_INCLUDE,
        [dirname(__DIR__) . '/src']
    );
}
