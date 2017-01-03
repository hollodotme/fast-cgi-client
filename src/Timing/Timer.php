<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016 Holger Woltersdorf
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

namespace hollodotme\FastCGI\Timing;

use hollodotme\FastCGI\Timing\Exceptions\TimerNotStartedException;

/**
 * Class Timer
 * @package hollodotme\FastCGI\Timing
 */
final class Timer
{
	/** @var float */
	private $startTime;

	/** @var int */
	private $timeoutMs;

	public function __construct( int $timeoutMs )
	{
		$this->timeoutMs = $timeoutMs;
	}

	public function start() : void
	{
		$this->startTime = microtime( true );
	}

	public function timedOut() : bool
	{
		if ( null === $this->startTime )
		{
			throw new TimerNotStartedException( 'Timer not started.' );
		}

		return ((microtime( true ) - $this->startTime) > ($this->timeoutMs / 1000));
	}

	public function reset() : void
	{
		$this->startTime = null;
	}
}
