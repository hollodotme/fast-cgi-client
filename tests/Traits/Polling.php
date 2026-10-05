<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Traits;

use function microtime;
use function sprintf;
use function usleep;

trait Polling
{
	/**
	 * Waits until the condition is met, instead of sleeping for a fixed time that may be too short on slow machines.
	 *
	 * @param callable() : bool $condition
	 * @param float             $timeoutSeconds
	 */
	final protected function waitUntil( callable $condition, float $timeoutSeconds = 5.0 ) : void
	{
		$deadline = microtime( true ) + $timeoutSeconds;

		while ( !$condition() )
		{
			if ( microtime( true ) >= $deadline )
			{
				self::fail( sprintf( 'Condition was not met within %.1f seconds.', $timeoutSeconds ) );
			}

			usleep( 1000 );
		}
	}
}
