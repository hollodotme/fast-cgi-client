<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Interfaces;

/**
 * Class NameValuePairEncoder
 * @package hollodotme\FastCGI\Encoders
 */
interface EncodesNameValuePair
{
	/**
	 * @param array<mixed, mixed> $pairs
	 */
	public function encodePairs( array $pairs ) : string;

	public function encodePair( string $name, string $value ) : string;

	/**
	 * @return array<string, string>
	 */
	public function decodePairs( string $data, int $length = -1 ) : array;
}
