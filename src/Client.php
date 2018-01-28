<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2018 Holger Woltersdorf
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

use hollodotme\FastCGI\Encoders\NameValuePairEncoder;
use hollodotme\FastCGI\Encoders\PacketEncoder;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\EncodesNameValuePair;
use hollodotme\FastCGI\Interfaces\EncodesPacket;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;

/**
 * Class Client
 * @package hollodotme\FastCGI
 */
class Client
{
	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var array|Socket[] */
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
		$this->sockets              = [];
	}

	/**
	 * @param ProvidesRequestData $request
	 *
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @throws \hollodotme\FastCGI\Exceptions\ConnectException
	 * @throws \Exception
	 * @throws \Throwable
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 * @return ProvidesResponseData
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
	 * @throws Exceptions\ConnectException
	 * @throws Exceptions\WriteFailedException
	 * @throws Exceptions\TimedoutException
	 * @throws \Exception
	 */
	public function sendAsyncRequest( ProvidesRequestData $request ) : int
	{
		for ( $i = 0; $i < 10; $i++ )
		{
			$socket = new Socket( $this->connection, $this->packetEncoder, $this->nameValuePairEncoder );

			if ( isset( $this->sockets[ $socket->getId() ] ) )
			{
				continue;
			}

			$this->sockets[ $socket->getId() ] = $socket;

			$socket->sendRequest( $request );

			return $socket->getId();
		}

		throw new WriteFailedException( 'Could not allocate a new request ID' );
	}

	/**
	 * @param int      $requestId
	 * @param int|null $timeoutMs
	 *
	 * @throws \Throwable
	 * @return ProvidesResponseData
	 */
	public function readResponse( int $requestId, ?int $timeoutMs = null ) : ProvidesResponseData
	{
		try
		{
			$socket = $this->getSocketWithId( $requestId );

			return $socket->fetchResponse( $timeoutMs );
		}
		catch ( \Throwable $e )
		{
			throw $e;
		}
		finally
		{
			$this->removeSocket( $requestId );
		}
	}

	/**
	 * @param int $requestId
	 *
	 * @return Socket
	 * @throws ReadFailedException
	 */
	private function getSocketWithId( int $requestId ) : Socket
	{
		$this->guardSocketExists( $requestId );

		return $this->sockets[ $requestId ];
	}

	/**
	 * @param int $requestId
	 *
	 * @throws ReadFailedException
	 */
	private function guardSocketExists( int $requestId ) : void
	{
		if ( !isset( $this->sockets[ $requestId ] ) )
		{
			throw new ReadFailedException( 'Socket not found for request ID: ' . $requestId );
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
		$socket = $this->getSocketWithId( $requestId );

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
	 * @throws \Throwable
	 */
	public function waitForResponses( ?int $timeoutMs = null ) : void
	{
		if ( \count( $this->sockets ) === 0 )
		{
			throw new ReadFailedException( 'No pending requests found.' );
		}

		while ( $this->hasUnhandledResponses() )
		{
			foreach ( $this->getSocketsHavingResponse() as $socket )
			{
				$this->fetchResponseAndNotifyCallback( $socket, $timeoutMs );
			}
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
		catch ( \Throwable $e )
		{
			$socket->notifyFailureCallbacks( $e );
		}
		finally
		{
			$this->removeSocket( $socket->getId() );
		}
	}

	/**
	 * @return bool
	 */
	public function hasUnhandledResponses() : bool
	{
		return (\count( $this->sockets ) > 0);
	}

	/**
	 * @return \Generator|Socket[]
	 * @throws ReadFailedException
	 */
	private function getSocketsHavingResponse() : \Generator
	{
		foreach ( $this->getRequestIdsHavingResponse() as $requestId )
		{
			yield $this->getSocketWithId( $requestId );
		}
	}

	/**
	 * @param int $requestId
	 */
	private function removeSocket( int $requestId ) : void
	{
		if ( isset( $this->sockets[ $requestId ] ) )
		{
			unset( $this->sockets[ $requestId ] );
		}
	}

	/**
	 * @param int $requestId
	 *
	 * @return bool
	 * @throws ReadFailedException
	 */
	public function hasResponse( int $requestId ) : bool
	{
		$socket = $this->getSocketWithId( $requestId );

		return $socket->hasResponse();
	}

	/**
	 * @return array
	 */
	public function getRequestIdsHavingResponse() : array
	{
		if ( \count( $this->sockets ) === 0 )
		{
			return [];
		}

		$resources = [];
		$writes    = $excepts = null;

		foreach ( $this->sockets as $socket )
		{
			$socket->collectResource( $resources );
		}

		stream_select( $resources, $writes, $excepts, 0, Socket::STREAM_SELECT_USEC );

		return array_keys( $resources );
	}

	/**
	 * @param int|null $timeoutMs
	 * @param int[]    ...$requestIds
	 *
	 * @return \Generator|ProvidesResponseData[]
	 */
	public function readResponses( ?int $timeoutMs = null, int ...$requestIds ) : \Generator
	{
		foreach ( $requestIds as $requestId )
		{
			try
			{
				$socket = $this->getSocketWithId( $requestId );

				yield $socket->fetchResponse( $timeoutMs );
			}
			catch ( \Throwable $e )
			{
				continue;
			}
			finally
			{
				$this->removeSocket( $requestId );
			}
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @return \Generator|ProvidesResponseData[]
	 */
	public function readReadyResponses( ?int $timeoutMs = null ) : \Generator
	{
		$requestIds = $this->getRequestIdsHavingResponse();

		if ( \count( $requestIds ) > 0 )
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
		$socket = $this->getSocketWithId( $requestId );

		$this->fetchResponseAndNotifyCallback( $socket, $timeoutMs );
	}

	/**
	 * @param int|null $timeoutMs
	 * @param \int[]   ...$requestIds
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
