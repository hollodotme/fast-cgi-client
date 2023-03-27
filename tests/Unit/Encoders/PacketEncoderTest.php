<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Unit\Encoders;

use hollodotme\FastCGI\Encoders\PacketEncoder;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

use function strlen;

final class PacketEncoderTest extends TestCase
{
    /**
     * @param int                $type
     * @param string             $content
     * @param int                $requestId
     * @param array<string, int> $expectedHeader
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @dataProvider packetContentProvider
     */
    public function testCanEncodeAndDecodePacket(
        int $type,
        string $content,
        int $requestId,
        array $expectedHeader
    ): void {
        $packetEncoder = new PacketEncoder();

        $packet = $packetEncoder->encodePacket($type, $content, $requestId);

        $header = $packetEncoder->decodeHeader($packet);

        self::assertEquals($expectedHeader, $header);
        self::assertEquals(substr($packet, -1 * strlen($content)), $content);
    }

    /**
     * @return array<array<string, int|string|array<string, int>>>
     */
    public function packetContentProvider(): array
    {
        return [
            [
                'type'           => 4,
                'content'        => 'test',
                'requestId'      => 1,
                'expectedHeader' => [
                    'version'       => 1,
                    'type'          => 4,
                    'requestId'     => 1,
                    'contentLength' => 4,
                    'paddingLength' => 0,
                    'reserved'      => 0,
                ],
            ],
            [
                'type'           => 5,
                'content'        => 'çélinö~ß',
                'requestId'      => 12,
                'expectedHeader' => [
                    'version'       => 1,
                    'type'          => 5,
                    'requestId'     => 12,
                    'contentLength' => 12,
                    'paddingLength' => 0,
                    'reserved'      => 0,
                ],
            ],
        ];
    }
}
