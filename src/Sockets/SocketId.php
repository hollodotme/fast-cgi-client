<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Sockets;

use Exception;
use InvalidArgumentException;
use function random_int;

final class SocketId
{
	/** @var int */
	private $id;

	/**
	 * @param int $id
	 *
	 * @throws InvalidArgumentException
	 */
	private function __construct( int $id )
	{
		$this->guardValueIsValid( $id );

		$this->id = $id;
	}

	/**
	 * @param int $value
	 *
	 * @throws InvalidArgumentException
	 */
	private function guardValueIsValid( int $value ) : void
	{
		if ( $value < 1 || $value > ((1 << 16) - 1) )
		{
			throw new InvalidArgumentException( 'Invalid socket ID (out of range): ' . $value );
		}
	}

	/**
	 * @return SocketId
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public static function new() : self
	{
		return new self( random_int( 1, (1 << 16) - 1 ) );
	}

	/**
	 * @param int $id
	 *
	 * @return SocketId
	 * @throws InvalidArgumentException
	 */
	public static function fromInt( int $id ) : self
	{
		return new self( $id );
	}

	public function getValue() : int
	{
		return $this->id;
	}

	public function equals( SocketId $other ) : bool
	{
		return $this->id === $other->id;
	}
}