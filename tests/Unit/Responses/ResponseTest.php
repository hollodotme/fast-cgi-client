<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Responses;

use hollodotme\FastCGI\Responses\Response;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class ResponseTest extends TestCase
{
    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetHeaders(): void
    {
        $output = "X-Powered-By: PHP/7.3.0\r\n"
                  . "X-Custom: Header\r\n"
                  . "Set-Cookie: yummy_cookie=choco\r\n"
                  . "Set-Cookie: tasty_cookie=strawberry\r\n"
                  . "Set-cookie: delicious_cookie=cherry\r\n"
                  . "Content-type: text/html; charset=UTF-8\r\n"
                  . "\r\n"
                  . 'unit';

        $error    = '';
        $duration = 0.54321;
        $response = new Response($output, $error, $duration);

        $expectedHeaders = [
            'X-Powered-By' => [
                'PHP/7.3.0',
            ],
            'X-Custom'     => [
                'Header',
            ],
            'Set-Cookie'   => [
                'yummy_cookie=choco',
                'tasty_cookie=strawberry',
            ],
            'Set-cookie'   => [
                'delicious_cookie=cherry',
            ],
            'Content-type' => [
                'text/html; charset=UTF-8',
            ],
        ];

        # All headers
        self::assertSame($expectedHeaders, $response->getHeaders());

        # Header values by keys
        self::assertSame(['PHP/7.3.0'], $response->getHeader('X-Powered-By'));
        self::assertSame(['Header'], $response->getHeader('X-Custom'));
        self::assertSame(
            ['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
            $response->getHeader('Set-Cookie')
        );
        self::assertSame(['text/html; charset=UTF-8'], $response->getHeader('Content-type'));

        # Header lines by keys
        self::assertSame('PHP/7.3.0', $response->getHeaderLine('X-Powered-By'));
        self::assertSame('Header', $response->getHeaderLine('X-Custom'));
        self::assertSame(
            'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
            $response->getHeaderLine('Set-Cookie')
        );
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-type'));

        # Header values by case-insensitive keys
        self::assertSame(['PHP/7.3.0'], $response->getHeader('x-powered-by'));
        self::assertSame(['Header'], $response->getHeader('X-CUSTOM'));
        self::assertSame(
            ['yummy_cookie=choco', 'tasty_cookie=strawberry', 'delicious_cookie=cherry'],
            $response->getHeader('Set-cookie')
        );
        self::assertSame(['text/html; charset=UTF-8'], $response->getHeader('Content-Type'));

        # Header lines by case-insensitive keys
        self::assertSame('PHP/7.3.0', $response->getHeaderLine('x-powered-by'));
        self::assertSame('Header', $response->getHeaderLine('X-CUSTOM'));
        self::assertSame(
            'yummy_cookie=choco, tasty_cookie=strawberry, delicious_cookie=cherry',
            $response->getHeaderLine('Set-cookie')
        );
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetBody(): void
    {
        $output   = "X-Powered-By: PHP/7.1.0\r\n"
                    . "X-Custom: Header\r\n"
                    . "Content-type: text/html; charset=UTF-8\r\n"
                    . "\r\n"
                    . "unit\r\n"
                    . 'test';
        $error    = '';
        $duration = 0.54321;
        $response = new Response($output, $error, $duration);

        $expectedBody = "unit\r\ntest";

        self::assertSame($expectedBody, $response->getBody());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetOutput(): void
    {
        $output   = "X-Powered-By: PHP/7.1.0\r\n"
                    . "X-Custom: Header\r\n"
                    . "Content-type: text/html; charset=UTF-8\r\n"
                    . "\r\n"
                    . "unit\r\n"
                    . 'test';
        $error    = '';
        $duration = 0.54321;
        $response = new Response($output, $error, $duration);

        self::assertSame($output, $response->getOutput());
        self::assertSame($duration, $response->getDuration());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testCanGetError(): void
    {
        $output   = "Status: 404 Not Found\r\n"
                    . "X-Powered-By: PHP/7.1.0\r\n"
                    . "X-Custom: Header\r\n"
                    . "Content-type: text/html; charset=UTF-8\r\n"
                    . "\r\n"
                    . 'File not found.';
        $error    = 'Primary script unknown';
        $duration = 0.54321;
        $response = new Response($output, $error, $duration);

        self::assertSame($output, $response->getOutput());
        self::assertSame('File not found.', $response->getBody());
        self::assertSame($error, $response->getError());
        self::assertSame($duration, $response->getDuration());
    }
}
