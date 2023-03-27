<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Encoders;

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

final class NameValuePairEncoderTest extends TestCase
{
    /**
     * @param array<mixed, mixed> $pairs
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @dataProvider pairProvider
     */
    public function testCanEncodeAndDecodePairs(array $pairs): void
    {
        $nameValuePairEncoder = new NameValuePairEncoder();

        $encoded = $nameValuePairEncoder->encodePairs($pairs);
        $decoded = $nameValuePairEncoder->decodePairs($encoded);

        self::assertEquals($pairs, $decoded);
    }

    /**
     * @return array<array<string, array<mixed, mixed>>>
     */
    public function pairProvider(): array
    {
        return [
            [
                'pairs' => ['unit' => 'test'],
            ],
            # no strings
            [
                'pairs' => [10 => 12.3, 'null' => null],
            ],
            # name longer than 128 chars
            [
                'pairs' => [str_repeat('a', 129) => 'unit'],
            ],
            # value longer than 128 chars
            [
                'pairs' => ['unit' => str_repeat('b', 129)],
            ],
        ];
    }
}
