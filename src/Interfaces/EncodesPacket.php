<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

/**
 * Interface PacketEncoder
 * @package hollodotme\FastCGI\Encoders
 */
interface EncodesPacket
{
    public function encodePacket(int $type, string $content, int $requestId): string;

    /**
     * @param string $data
     *
     * @return array<string, int>
     */
    public function decodeHeader(string $data): array;
}
