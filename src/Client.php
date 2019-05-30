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

use Exception;
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
	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var SocketCollection */
	private $sockets;

	/** @var EncodesPacket */
	private $packetEncoder;

	/** @var EncodesNameValuePair */
	private $nameValuePairEncoder;

	/**
	 * @param ConfiguresSocketConnection $connection
	 */
	public function __construct( ConfiguresSocketConnection $connection )
	{
		$this->connection           = $connection;
		$this->packetEncoder        = new PacketEncoder();
		$this->nameValuePairEncoder = new NameValuePairEncoder();
		$this->sockets              = new SocketCollection();
	}

	/**
	 * @param ProvidesRequestData $request
	 *
	 * @return ProvidesResponseData
	 * @throws ConnectException
	 * @throws Exception
	 * @throws Throwable
	 * @throws WriteFailedException
	 * @throws TimedoutException
	 */
	public function sendRequest( ProvidesRequestData $request ) : ProvidesResponseData
	{
		$requestId = $this->sendAsyncRequest( $request );

		return $this->readResponse( $requestId );
	}

	/**
	 * @param ProvidesRequestData $request
	 *
	 * @return int
	 *
	 * @throws ConnectException
	 * @throws WriteFailedException
	 * @throws TimedoutException
	 * @throws Exception
	 */
	public function sendAsyncRequest( ProvidesRequestData $request ) : int
	{
		$socket = $this->sockets->getIdleSocket();

		if ( null !== $socket )
		{
			$socket->sendRequest( $request );

			return $socket->getId();
		}

		$socket = $this->sockets->new(
			$this->connection,
			$this->packetEncoder,
			$this->nameValuePairEncoder
		);

		$socket->sendRequest( $request );

		return $socket->getId();
	}

	/**
	 * @param int      $requestId
	 * @param int|null $timeoutMs
	 *
	 * @return ProvidesResponseData
	 * @throws Throwable
	 */
	public function readResponse( int $requestId, ?int $timeoutMs = null ) : ProvidesResponseData
	{
		try
		{
			return $this->sockets->getById( $requestId )->fetchResponse( $timeoutMs );
		}
		catch ( Throwable $e )
		{
			$this->sockets->remove( $requestId );

			throw $e;
		}
	}

	/**
	 * @param int      $requestId
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function waitForResponse( int $requestId, ?int $timeoutMs = null ) : void
	{
		$socket = $this->sockets->getById( $requestId );

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
	 * @param int $requestId
	 *
	 * @return bool
	 * @throws ReadFailedException
	 */
	public function hasResponse( int $requestId ) : bool
	{
		return $this->sockets->getById( $requestId )->hasResponse();
	}

	/**
	 * @return array
	 * @throws ReadFailedException
	 */
	public function getRequestIdsHavingResponse() : array
	{
		if ( $this->sockets->isEmpty() )
		{
			return [];
		}

		$reads  = $this->sockets->collectResources();
		$writes = $excepts = null;

		@stream_select( $reads, $writes, $excepts, 0, Socket::STREAM_SELECT_USEC );

		return $this->sockets->getSocketIdsByResources( $reads );
	}

	/**
	 * @param int|null $timeoutMs
	 * @param int      ...$requestIds
	 *
	 * @return Generator|ProvidesResponseData[]
	 */
	public function readResponses( ?int $timeoutMs = null, int ...$requestIds ) : Generator
	{
		foreach ( $requestIds as $requestId )
		{
			try
			{
				yield $this->sockets->getById( $requestId )->fetchResponse( $timeoutMs );
			}
			catch ( Throwable $e )
			{
				# Skip unknown request ids
			}
			finally
			{
				$this->sockets->remove( $requestId );
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
		$requestIds = $this->getRequestIdsHavingResponse();

		if ( count( $requestIds ) > 0 )
		{
			yield from $this->readResponses( $timeoutMs, ...$requestIds );
		}
	}

	/**
	 * @param int      $requestId
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function handleResponse( int $requestId, ?int $timeoutMs = null ) : void
	{
		$this->fetchResponseAndNotifyCallback(
			$this->sockets->getById( $requestId ),
			$timeoutMs
		);
	}

	/**
	 * @param int|null $timeoutMs
	 * @param int      ...$requestIds
	 *
	 * @throws ReadFailedException
	 */
	public function handleResponses( ?int $timeoutMs = null, int ...$requestIds ) : void
	{
		foreach ( $requestIds as $requestId )
		{
			$this->handleResponse( $requestId, $timeoutMs );
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @throws ReadFailedException
	 */
	public function handleReadyResponses( ?int $timeoutMs = null ) : void
	{
		$requestIds = $this->getRequestIdsHavingResponse();

		$this->handleResponses( $timeoutMs, ...$requestIds );
	}
}
