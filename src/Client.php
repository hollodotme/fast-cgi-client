<?php declare(strict_types = 1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2017 Holger Woltersdorf
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
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ForbiddenException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Responses\Response;

/**
 * Class Client
 * @package hollodotme\FastCGI
 */
class Client
{
	private const BEGIN_REQUEST      = 1;

	private const END_REQUEST        = 3;

	private const PARAMS             = 4;

	private const STDIN              = 5;

	private const STDOUT             = 6;

	private const STDERR             = 7;

	private const RESPONDER          = 1;

	private const REQUEST_COMPLETE   = 0;

	private const CANT_MPX_CONN      = 1;

	private const OVERLOADED         = 2;

	private const UNKNOWN_ROLE       = 3;

	private const HEADER_LEN         = 8;

	private const REQ_STATE_WRITTEN  = 1;

	private const REQ_STATE_OK       = 2;

	private const REQ_STATE_ERR      = 3;

	private const STREAM_SELECT_USEC = 20000;

	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var resource */
	private $socket;

	/** @var array */
	private $sockets;

	/** @var array|callable[][] */
	private $responseCallbacks;

	/** @var array|callable[][] */
	private $failureCallbacks;

	/** @var PacketEncoder */
	private $packetEncoder;

	/** @var NameValuePairEncoder */
	private $nameValuePairEncoder;

	/**
	 * Outstanding request status keyed by request id
	 * Each request is an array with following form:
	 *  array(
	 *    'state' => REQ_STATE_*
	 *    'response' => null | string
	 *  )
	 * @var array
	 */
	private $requests = [];

	public function __construct( ConfiguresSocketConnection $connection )
	{
		$this->connection           = $connection;
		$this->packetEncoder        = new PacketEncoder();
		$this->nameValuePairEncoder = new NameValuePairEncoder();
		$this->sockets              = [];
		$this->responseCallbacks    = [];
		$this->failureCallbacks     = [];
	}

	public function sendRequest( ProvidesRequestData $request ) : ProvidesResponseData
	{
		$requestId = $this->sendAsyncRequest( $request );

		return $this->waitForResponse( $requestId );
	}

	public function sendAsyncRequest( ProvidesRequestData $request ) : int
	{
		// Pick random number between 1 and max 16 bit unsigned int 65535
		$requestId = random_int( 1, (1 << 16) - 1 );

		$this->connect( $requestId );

		$requestPackets = $this->getRequestPackets( $request, $requestId );
		$startTime      = microtime( true );

		$socket = $this->sockets[ $requestId ];

		$writeResult = fwrite( $socket, $requestPackets );
		$flushResult = fflush( $socket );

		if ( $writeResult === false || $flushResult === false )
		{
			$info = stream_get_meta_data( $this->sockets[ $requestId ] );

			$this->removeStream( $requestId );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Write timed out' );
			}

			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}

		$this->requests[ $requestId ] = [
			'state'     => self::REQ_STATE_WRITTEN,
			'response'  => null,
			'startTime' => $startTime,
			'duration'  => 0,
		];

		if ( $request->getResponseCallbacks() )
		{
			$this->responseCallbacks[ $requestId ] = $request->getResponseCallbacks();
		}

		$this->failureCallbacks[ $requestId ] = $request->getFailureCallbacks();

		return $requestId;
	}

	private function connect( int $requestId ) : void
	{
		if ( !isset( $this->sockets[ $requestId ] ) )
		{
			try
			{
				$this->sockets[ $requestId ] = fsockopen(
					$this->connection->getHost(),
					$this->connection->getPort(),
					$errorNumber,
					$errorString,
					$this->connection->getConnectTimeout() / 1000
				);
			}
			catch ( \Throwable $e )
			{
				throw new ConnectException( $e->getMessage(), $e->getCode(), $e );
			}

			if ( $this->sockets[ $requestId ] === false )
			{
				throw new ConnectException( 'Unable to connect to FastCGI application: ' . $errorString );
			}

			if ( !$this->setStreamTimeout( $this->sockets[ $requestId ], $this->connection->getReadWriteTimeout() ) )
			{
				throw new ConnectException( 'Unable to set timeout on socket' );
			}
		}
	}

	private function getRequestPackets( ProvidesRequestData $request, int $requestId ) : string
	{
		# Keep alive bit always set to 1
		$requestPackets = $this->packetEncoder->encodePacket(
			self::BEGIN_REQUEST,
			chr( 0 ) . chr( self::RESPONDER ) . chr( 1 ) . str_repeat( chr( 0 ), 5 ),
			$requestId
		);

		$paramsRequest = $this->nameValuePairEncoder->encodePairs( $request->getParams() );

		if ( $paramsRequest )
		{
			$requestPackets .= $this->packetEncoder->encodePacket( self::PARAMS, $paramsRequest, $requestId );
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::PARAMS, '', $requestId );

		if ( $request->getContent() )
		{
			$requestPackets .= $this->packetEncoder->encodePacket( self::STDIN, $request->getContent(), $requestId );
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::STDIN, '', $requestId );

		return $requestPackets;
	}

	private function setStreamTimeout( $socket, int $timeoutMs ) : bool
	{
		return stream_set_timeout( $socket, (int)floor( $timeoutMs / 1000 ), ($timeoutMs % 1000) * 1000 );
	}

	private function readPacket( $socket ) : ?array
	{
		if ( $packet = fread( $socket, self::HEADER_LEN ) )
		{
			$packet            = $this->packetEncoder->decodeHeader( $packet );
			$packet['content'] = '';

			if ( $packet['contentLength'] )
			{
				$length = $packet['contentLength'];

				while ( $length && ($buffer = fread( $socket, $length )) !== false )
				{
					$length -= strlen( $buffer );
					$packet['content'] .= $buffer;
				}
			}

			if ( $packet['paddingLength'] )
			{
				fread( $socket, $packet['paddingLength'] );
			}

			return $packet;
		}

		return null;
	}

	public function waitForResponses( ?int $timeout = null ) : void
	{
		while ( count( $this->responseCallbacks ) > 0 )
		{
			# only select streams that have callbacks
			$reads = array_intersect_key( $this->sockets, $this->responseCallbacks );

			if ( count( $reads ) === 0 )
			{
				break;
			}

			$available = $this->checkAvailableStreams( $reads );

			if ( $available === false )
			{
				# Nothing happened
				usleep( 2000 );
				continue;
			}

			foreach ( $reads as $requestId => $socket )
			{
				echo 'Socket: ' . (int)$socket . " - {$requestId}\n";

				try
				{
					$response = $this->fetchResponse( $requestId, $timeout );

					foreach ( (array)$this->responseCallbacks[ $requestId ] as $callback )
					{
						if ( is_callable( $callback ) )
						{
							$callback( $response );
						}
					}
				}
				catch ( \Throwable $e )
				{
					foreach ( (array)$this->failureCallbacks[ $requestId ] as $callback )
					{
						if ( is_callable( $callback ) )
						{
							$callback( $e );
						}
					}
				}
				finally
				{
					$this->removeStream( $requestId );
				}
			}
		}
	}

	private function checkAvailableStreams( array &$reads ) : bool
	{
		$writes  = null;
		$excepts = null;

		return (bool)stream_select( $reads, $writes, $excepts, 0, self::STREAM_SELECT_USEC );
	}

	public function readResponse( int $requestId, ?int $timeoutMs = null ) : ProvidesResponseData
	{
		try
		{
			return $this->fetchResponse( $requestId, $timeoutMs );
		}
		catch ( \Throwable $e )
		{
			throw $e;
		}
		finally
		{
			$this->removeStream( $requestId );
		}
	}

	private function fetchResponse( int $requestId, ?int $timeoutMs ) : ProvidesResponseData
	{
		$this->guardRequestIdExists( $requestId );
		$this->guardSocketExists( $requestId );

		// If we already read the response during an earlier call for different id, just return it
		if ( in_array( $this->requests[ $requestId ]['state'], [ self::REQ_STATE_OK, self::REQ_STATE_ERR ], true ) )
		{
			return new Response(
				$requestId,
				(string)$this->requests[ $requestId ]['response'],
				(float)$this->requests[ $requestId ]['duration']
			);
		}

		$socket = $this->sockets[ $requestId ];

		// Reset timeout on socket for now
		$this->setStreamTimeout( $socket, $timeoutMs ?? $this->connection->getReadWriteTimeout() );

		do
		{
			$packet = $this->readPacket( $this->sockets[ $requestId ] );

			switch ( (int)$packet['type'] )
			{
				case self::STDOUT:
					$this->requests[ $packet['requestId'] ]['response'] .= $packet['content'];
					break;

				case self::STDERR:
					$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_ERR;
					$this->requests[ $packet['requestId'] ]['response'] .= $packet['content'];
					break;

				case self::END_REQUEST:
					$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_OK;
					if ( $packet['requestId'] === $requestId )
					{
						break 2;
					}
					break;
			}
		}
		while ( null !== $packet );

		if ( $packet === null )
		{
			$info = stream_get_meta_data( $this->socket );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Read timed out' );
			}

			if ( $info['unread_bytes'] === 0 && $info['blocked'] && $info['eof'] )
			{
				throw new ForbiddenException( 'Not in white list. Check listen.allowed_clients.' );
			}

			throw new ReadFailedException( 'Read failed' );
		}

		$this->guardRequestCompleted( ord( $packet['content']{4} ) );

		$duration = microtime( true ) - (float)$this->requests[ $requestId ]['startTime'];

		$this->requests[ $requestId ]['duration'] = $duration;

		return new Response(
			$requestId,
			(string)$this->requests[ $requestId ]['response'],
			(float)$this->requests[ $requestId ]['duration']
		);
	}

	private function guardRequestIdExists( int $requestId )
	{
		if ( !isset( $this->requests[ $requestId ] ) )
		{
			throw new ReadFailedException( 'Invalid request id given' );
		}
	}

	private function guardRequestCompleted( int $flag )
	{
		if ( $flag === self::REQUEST_COMPLETE )
		{
			return;
		}

		switch ( $flag )
		{
			case self::CANT_MPX_CONN:
				throw new WriteFailedException( 'This app can\'t multiplex [CANT_MPX_CONN]' );

			case self::OVERLOADED:
				throw new WriteFailedException( 'New request rejected; too busy [OVERLOADED]' );

			case self::UNKNOWN_ROLE:
				throw new WriteFailedException( 'Role value not known [UNKNOWN_ROLE]' );

			default:
				throw new ReadFailedException( 'Unknown content.' );
		}
	}

	private function removeStream( int $requestId )
	{
		if ( isset( $this->sockets[ $requestId ] ) )
		{
			fclose( $this->sockets[ $requestId ] );
			unset( $this->sockets[ $requestId ] );
		}

		unset( $this->responseCallbacks[ $requestId ] );
	}

	/**
	 * Blocking call that waits for response to specific request
	 *
	 * @param int $requestId     Request ID
	 * @param int $timeoutMs     [optional] the number of milliseconds to wait.
	 *                           Defaults to the ReadWriteTimeout value set.
	 *
	 * @throws \Throwable
	 * @return ProvidesResponseData
	 */
	public function waitForResponse( int $requestId, ?int $timeoutMs = null ) : ProvidesResponseData
	{
		$response = null;

		while ( true )
		{
			if ( $this->hasResponse( $requestId ) )
			{
				$response = $this->readResponse( $requestId, $timeoutMs );
				break;
			}

			usleep( 2000 );
		}

		return $response;
	}

	public function hasResponse( int $requestId ) : bool
	{
		$this->guardSocketExists( $requestId );

		$reads = [ $this->sockets[ $requestId ] ];

		return $this->checkAvailableStreams( $reads );
	}

	private function guardSocketExists( int $requestId )
	{
		if ( !is_resource( $this->sockets[ $requestId ] ?? null ) )
		{
			throw new ReadFailedException( 'Socket not found for request ID: ' . $requestId );
		}
	}

	public function getReadyRequestIds() : array
	{
		$reads     = $this->sockets;
		$available = $this->checkAvailableStreams( $reads );

		return $available ? array_keys( $reads ) : [];
	}

	public function readResponses( int ...$requestIds ) : array
	{
		$responses = [];
		
		foreach ( $requestIds as $requestId )
		{
			try
			{
				$responses[ $requestId ] = $this->fetchResponse( $requestId, null );
			}
			catch ( \Throwable $e )
			{
				continue;
			}
			finally
			{
				$this->removeStream( $requestId );
			}
		}

		return $responses;
	}
}
