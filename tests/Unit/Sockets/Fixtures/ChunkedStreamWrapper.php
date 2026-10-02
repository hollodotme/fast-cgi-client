<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2020 Holger Woltersdorf & Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace hollodotme\FastCGI\Tests\Unit\Sockets\Fixtures;

use function min;
use function strlen;
use function substr;

/**
 * A stream that delivers its data in chunks of a fixed size, like a socket that receives the data in parts.
 * Everything written to the stream is discarded.
 */
final class ChunkedStreamWrapper
{
	public const PROTOCOL = 'fcgi-chunked';

	/** @var string */
	public static $data = '';

	/** @var int */
	public static $chunkSize = 1;

	/** @var resource|null */
	public $context;

	/** @var int */
	private $position = 0;

	/**
	 * @param string      $path
	 * @param string      $mode
	 * @param int         $options
	 * @param string|null $openedPath
	 *
	 * @return bool
	 */
	public function stream_open( string $path, string $mode, int $options, ?string &$openedPath ) : bool
	{
		$this->position = 0;

		return true;
	}

	public function stream_read( int $count ) : string
	{
		$chunk          = (string)substr( self::$data, $this->position, min( $count, self::$chunkSize ) );
		$this->position += strlen( $chunk );

		return $chunk;
	}

	public function stream_write( string $data ) : int
	{
		return strlen( $data );
	}

	public function stream_flush() : bool
	{
		return true;
	}

	public function stream_eof() : bool
	{
		return $this->position >= strlen( self::$data );
	}

	/**
	 * @param int $option
	 * @param int $arg1
	 * @param int $arg2
	 *
	 * @return bool
	 */
	public function stream_set_option( int $option, int $arg1, int $arg2 ) : bool
	{
		return true;
	}
}
