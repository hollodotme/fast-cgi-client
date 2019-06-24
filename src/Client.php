<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2019 Holger Woltersdorf & Contributors
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

namespace hollodotme\FastCGI;

use Generator;
use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\EncodesNameValuePair;
use hollodotme\FastCGI\Interfaces\EncodesPacket;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Sockets\Socket;
use hollodotme\FastCGI\Sockets\SocketCollection;
use Throwable;
use function count;
use function stream_select;

class Client
{
	/** @var SocketCollection */
	private $sockets;

	/** @var EncodesPacket */
	private $packetEncoder;

	/** @var EncodesNameValuePair */
	private $nameValuePairEncoder;

	public function __construct()
	{
		$this->packetEncoder        = new PacketEncoder();
		$this->nameValuePairEncoder = new NameValuePairEncoder();
		$this->sockets              = new SocketCollection();
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param ProvidesRequestData        $request
	 *
	 * @return ProvidesResponseData
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 */
	public function sendRequest(
		ConfiguresSocketConnection $connection,
		ProvidesRequestData $request
	) : ProvidesResponseData
	{
		$socketId = $this->sendAsyncRequest( $connection, $request );

		return $this->readResponse( $socketId );
	}

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param ProvidesRequestData        $request
	 *
	 * @return int SocketId
	 *
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 */
	public function sendAsyncRequest( ConfiguresSocketConnection $connection, ProvidesRequestData $request ) : int
	{
		$socket = $this->sockets->getIdleSocket( $connection );

		if ( null !== $socket )
		{
			$socket->sendRequest( $request );

			return $socket->getId();
		}

		$socket = $this->sockets->new(
			$connection,
			$this->packetEncoder,
			$this->nameValuePairEncoder
		);

		$socket->sendRequest( $request );

		return $socket->getId();
	}

	/**
	 * @param int      $socketId
	 * @param int|null $timeoutMs
	 *
	 * @return ProvidesResponseData
	 * @throws Throwable
	 */
	public function readResponse( int $socketId, ?int $timeoutMs = null ) : ProvidesResponseData
	{
		try
		{
			return $this->sockets->getById( $socketId )->fetchResponse( $timeoutMs );
		}
		catch ( Throwable $e )
		{
			$this->sockets->remove( $socketId );

			throw $e;
		}
	}

	/**
	 * @param int      $socketId
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function waitForResponse( int $socketId, ?int $timeoutMs = null ) : void
	{
		$socket = $this->sockets->getById( $socketId );

		while ( true )
		{
			if ( $socket->hasResponse() )
			{
				$this->fetchResponseAndNotifyCallback( $socket, $timeoutMs );
				break;
			}
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 * @throws Throwable
	 */
	public function waitForResponses( ?int $timeoutMs = null ) : void
	{
		if ( $this->sockets->isEmpty() )
		{
			throw new ReadFailedException( 'No pending requests found.' );
		}

		while ( $this->hasUnhandledResponses() )
		{
			$this->handleReadyResponses( $timeoutMs );
		}
	}

	/**
	 * @param Socket   $socket
	 * @param int|null $timeoutMs
	 */
	private function fetchResponseAndNotifyCallback( Socket $socket, ?int $timeoutMs = null ) : void
	{
		try
		{
			$response = $socket->fetchResponse( $timeoutMs );

			$socket->notifyResponseCallbacks( $response );
		}
		catch ( Throwable $e )
		{
			$socket->notifyFailureCallbacks( $e );
		}
		finally
		{
			$this->sockets->remove( $socket->getId() );
		}
	}

	/**
	 * @return bool
	 */
	public function hasUnhandledResponses() : bool
	{
		return $this->sockets->hasBusySockets();
	}

	/**
	 * @param int $socketId
	 *
	 * @return bool
	 * @throws ReadFailedException
	 */
	public function hasResponse( int $socketId ) : bool
	{
		return $this->sockets->getById( $socketId )->hasResponse();
	}

	/**
	 * @return array<int>
	 * @throws ReadFailedException
	 */
	public function getSocketIdsHavingResponse() : array
	{
		if ( $this->sockets->isEmpty() )
		{
			return [];
		}

		$reads  = $this->sockets->collectResources();
		$writes = $excepts = null;

		$result = @stream_select( $reads, $writes, $excepts, 0, Socket::STREAM_SELECT_USEC );

		if ( false === $result || 0 === count( $reads ) )
		{
			return [];
		}

		return $this->sockets->getSocketIdsByResources( $reads );
	}

	/**
	 * @param int|null $timeoutMs
	 * @param int      ...$socketIds
	 *
	 * @return Generator|ProvidesResponseData[]
	 */
	public function readResponses( ?int $timeoutMs = null, int ...$socketIds ) : Generator
	{
		foreach ( $socketIds as $socketId )
		{
			try
			{
				yield $this->sockets->getById( $socketId )->fetchResponse( $timeoutMs );
			}
			catch ( Throwable $e )
			{
				# Skip unknown socket ids
			}
			finally
			{
				$this->sockets->remove( $socketId );
			}
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @return Generator|ProvidesResponseData[]
	 * @throws ReadFailedException
	 */
	public function readReadyResponses( ?int $timeoutMs = null ) : Generator
	{
		$socketIds = $this->getSocketIdsHavingResponse();

		if ( [] !== $socketIds )
		{
			yield from $this->readResponses( $timeoutMs, ...$socketIds );
		}
	}

	/**
	 * @param int      $socketId
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function handleResponse( int $socketId, ?int $timeoutMs = null ) : void
	{
		$this->fetchResponseAndNotifyCallback(
			$this->sockets->getById( $socketId ),
			$timeoutMs
		);
	}

	/**
	 * @param int|null $timeoutMs
	 * @param int      ...$socketIds
	 *
	 * @throws ReadFailedException
	 */
	public function handleResponses( ?int $timeoutMs = null, int ...$socketIds ) : void
	{
		foreach ( $socketIds as $socketId )
		{
			$this->handleResponse( $socketId, $timeoutMs );
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function handleReadyResponses( ?int $timeoutMs = null ) : void
	{
		$socketIds = $this->getSocketIdsHavingResponse();

		$this->handleResponses( $timeoutMs, ...$socketIds );
	}
}
