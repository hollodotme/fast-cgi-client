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
use hollodotme\FastCGI\Timing\Timer;

/**
 * Class Client
 * @package hollodotme\FastCGI
 */
class Client
{
	private const BEGIN_REQUEST     = 1;

	private const END_REQUEST       = 3;

	private const PARAMS            = 4;

	private const STDIN             = 5;

	private const STDOUT            = 6;

	private const STDERR            = 7;

	private const RESPONDER         = 1;

	private const REQUEST_COMPLETE  = 0;

	private const CANT_MPX_CONN     = 1;

	private const OVERLOADED        = 2;

	private const UNKNOWN_ROLE      = 3;

	private const HEADER_LEN        = 8;

	private const REQ_STATE_WRITTEN = 1;

	private const REQ_STATE_OK      = 2;

	private const REQ_STATE_ERR     = 3;

	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var resource */
	private $socket;

	/** @var array */
	private $sockets;

	/** @var array */
	private $callbacks;

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
		$this->callbacks            = [];
	}

	/**
	 * Execute a request to the FastCGI application
	 *
	 * @param ProvidesRequestData $request
	 *
	 * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\ForbiddenException
	 * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
	 * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
	 * @return ProvidesResponseData
	 */
	public function sendRequest( ProvidesRequestData $request ) : ProvidesResponseData
	{
		$requestId = $this->sendAsyncRequest( $request );

		return $this->waitForResponse( $requestId );
	}

	/**
	 * Execute a request to the FastCGI application asyncronously
	 * This sends request to application and returns the assigned ID for that request.
	 * You should keep this id for later use with wait_for_response(). Ids are chosen randomly
	 * rather than seqentially to guard against false-positives when using persistent sockets.
	 * In that case it is possible that a delayed response to a request made by a previous script
	 * invocation comes back on this socket and is mistaken for response to request made with same ID
	 * during this request.
	 *
	 * @param ProvidesRequestData $request
	 *
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @return int
	 */
	public function sendAsyncRequest( ProvidesRequestData $request ) : int
	{
		// Pick random number between 1 and max 16 bit unsigned int 65535
		$requestId = random_int( 1, (1 << 16) - 1 );

		$this->connect( $requestId );

		$requestPackets = $this->getRequestPackets( $request, $requestId );
		$startTime      = microtime( true );

		$writeResult = fwrite( $this->sockets[ $requestId ], $requestPackets );
		$flushResult = fflush( $this->sockets[ $requestId ] );

		if ( $writeResult === false || $flushResult === false )
		{
			$info = stream_get_meta_data( $this->sockets[ $requestId ] );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Write timed out' );
			}

			// Broken pipe, tear down so future requests might succeed
			fclose( $this->sockets[ $requestId ] );

			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}

		$this->requests[ $requestId ] = [
			'state'     => self::REQ_STATE_WRITTEN,
			'response'  => null,
			'startTime' => $startTime,
			'duration'  => 0,
		];

		if ( null !== $request->getCallback() )
		{
			$this->callbacks[ $requestId ] = $request->getCallback();
		}

		return $requestId;
	}

	private function connect( int $requestId ) : void
	{
		if ( !isset( $this->sockets[ $requestId ] ) )
		{
			try
			{
				if ( $this->connection->isPersistent() )
				{
					$this->sockets[ $requestId ] = pfsockopen(
						$this->connection->getHost(),
						$this->connection->getPort(),
						$errorNumber,
						$errorString,
						$this->connection->getConnectTimeout() / 1000
					);
				}
				else
				{
					$this->sockets[ $requestId ] = fsockopen(
						$this->connection->getHost(),
						$this->connection->getPort(),
						$errorNumber,
						$errorString,
						$this->connection->getConnectTimeout() / 1000
					);
				}
			}
			catch ( \Throwable $e )
			{
				throw new ConnectException( $e->getMessage(), $e->getCode(), $e );
			}

			if ( $this->sockets[ $requestId ] === false )
			{
				throw new ConnectException( 'Unable to connect to FastCGI application: ' . $errorString );
			}

			if ( !$this->setStreamTimeout( $requestId, $this->connection->getReadWriteTimeout() ) )
			{
				throw new ConnectException( 'Unable to set timeout on socket' );
			}
		}
	}

	private function getRequestPackets( ProvidesRequestData $request, int $requestId ) : string
	{
		// Using persistent sockets implies you want them kept alive by server
		$keepAlive = (int)($this->connection->keepAlive() || $this->connection->isPersistent());

		$requestPackets = $this->packetEncoder->encodePacket(
			self::BEGIN_REQUEST,
			chr( 0 ) . chr( self::RESPONDER ) . chr( $keepAlive ) . str_repeat( chr( 0 ), 5 ),
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

	private function setStreamTimeout( int $requestId, int $timeoutMs ) : bool
	{
		if ( !isset( $this->sockets[ $requestId ] ) || !$this->sockets[ $requestId ] )
		{
			return false;
		}

		return stream_set_timeout(
			$this->sockets[ $requestId ],
			(int)floor( $timeoutMs / 1000 ),
			($timeoutMs % 1000) * 1000
		);
	}

	/**
	 * Read a FastCGI PacketEncoder
	 *
	 * @param $socket
	 *
	 * @return array|null
	 */
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
		else
		{
			return null;
		}
	}

	public function waitForResponses( int $timeout = 0 ) : void
	{
		while ( count( $this->callbacks ) > 0 )
		{
			$reads   = $this->sockets;
			$writes  = null;
			$excepts = null;

			$available = stream_select( $reads, $writes, $excepts, 0, 20000 );

			if ( $available === false )
			{
				break;
			}

			if ( $available === 0 )
			{
				# Nothing happened
				usleep( 200000 );
				continue;
			}

			print_r( array_keys( $reads ) );

			foreach ( $reads as $requestId => $socket )
			{
				echo 'Socket: ' . (int)$socket . " - {$requestId}\n";

				try
				{
					$response = $this->waitForResponse( $requestId, $timeout );

					if ( isset( $this->callbacks[ $requestId ] ) )
					{
						call_user_func( $this->callbacks[ $requestId ], $response );
					}
				}
				catch ( \Throwable $e )
				{
					throw $e;
				}
				finally
				{
					$this->removeStream( $requestId );
				}
				/*
				$packet = $this->readPacket( $socket );

				if ( $packet === null )
				{
					continue;

					$info = stream_get_meta_data( $socket );

					// We must reset timeout but it must be AFTER we get info
					$this->setStreamTimeout( $requestId, $this->connection->getReadWriteTimeout() );

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

				switch ( (int)$packet['type'] )
				{
					case self::STDOUT:
					{
						$this->requests[ $packet['requestId'] ]['response'] .= $packet['content'];
						break;
					}

					case self::STDERR:
					{
						$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_ERR;
						$this->requests[ $packet['requestId'] ]['response'] .= $packet['content'];
						break;
					}

					case self::END_REQUEST:
					{
						$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_OK;
						if ( $packet['requestId'] === $requestId )
						{
							switch ( ord( $packet['content']{4} ) )
							{
								case self::CANT_MPX_CONN:
									throw new WriteFailedException( 'This app can\'t multiplex [CANT_MPX_CONN]' );

								case self::OVERLOADED:
									throw new WriteFailedException( 'New request rejected; too busy [OVERLOADED]' );

								case self::UNKNOWN_ROLE:
									throw new WriteFailedException( 'Role value not known [UNKNOWN_ROLE]' );

								case self::REQUEST_COMPLETE:
									$duration = microtime( true ) - (float)$this->requests[ $requestId ]['startTime'];

									$this->requests[ $requestId ]['duration'] = $duration;

									$response = new Response(
										$requestId,
										(string)$this->requests[ $requestId ]['response'],
										(float)$this->requests[ $requestId ]['duration']
									);

									if ( isset( $this->callbacks[ $requestId ] ) )
									{
										call_user_func( $this->callbacks[ $requestId ], $response );
									}

									$this->removeStream( $requestId );
									break;
							}
						}
						break;
					}
				}
				*/
			}
		}
	}

	private function removeStream( $requestId )
	{
		if ( isset( $this->sockets[ $requestId ] ) )
		{
			if ( count( array_keys( $this->sockets, $this->sockets[ $requestId ], true ) ) === 1 )
			{
				fclose( $this->sockets[ $requestId ] );
			}

			unset( $this->sockets[ $requestId ] );
		}

		unset( $this->callbacks[ $requestId ] );
	}

	/**
	 * Blocking call that waits for response to specific request
	 *
	 * @param int $requestId     Request ID
	 * @param int $timeoutMs     [optional] the number of milliseconds to wait.
	 *                           Defaults to the ReadWriteTimeout value set.
	 *
	 * @throws ForbiddenException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @return ProvidesResponseData
	 */
	public function waitForResponse( int $requestId, int $timeoutMs = 0 ) : ProvidesResponseData
	{
		if ( !isset( $this->requests[ $requestId ] ) )
		{
			throw new ReadFailedException( 'Invalid request id given' );
		}

		// If we already read the response during an earlier call for different id, just return it
		if ( in_array( $this->requests[ $requestId ]['state'], [ self::REQ_STATE_OK, self::REQ_STATE_ERR ], true ) )
		{
			return new Response(
				$requestId,
				(string)$this->requests[ $requestId ]['response'],
				(float)$this->requests[ $requestId ]['duration']
			);
		}

		if ( $timeoutMs > 0 )
		{
			// Reset timeout on socket for now
			$this->setStreamTimeout( $requestId, $timeoutMs );
		}
		else
		{
			$timeoutMs = $this->connection->getReadWriteTimeout();
		}

		// Need to manually check since we might do several reads none of which timeout themselves
		// but still not get the response requested
		$timer = new Timer( $timeoutMs );
		$timer->start();

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

			if ( $timer->timedOut() )
			{
				// Reset
				$this->setStreamTimeout( $requestId, $this->connection->getReadWriteTimeout() );
				$timer->reset();

				throw new TimedoutException( 'Timed out' );
			}
		}
		while ( null !== $packet );

		if ( $packet === null )
		{
			$info = stream_get_meta_data( $this->socket );

			// We must reset timeout but it must be AFTER we get info
			$this->setStreamTimeout( $requestId, $this->connection->getReadWriteTimeout() );

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

		// Reset timeout
		$this->setStreamTimeout( $requestId, $this->connection->getReadWriteTimeout() );

		switch ( ord( $packet['content']{4} ) )
		{
			case self::CANT_MPX_CONN:
				throw new WriteFailedException( 'This app can\'t multiplex [CANT_MPX_CONN]' );

			case self::OVERLOADED:
				throw new WriteFailedException( 'New request rejected; too busy [OVERLOADED]' );

			case self::UNKNOWN_ROLE:
				throw new WriteFailedException( 'Role value not known [UNKNOWN_ROLE]' );

			case self::REQUEST_COMPLETE:
				$duration = microtime( true ) - (float)$this->requests[ $requestId ]['startTime'];

				$this->requests[ $requestId ]['duration'] = $duration;

				return new Response(
					$requestId,
					(string)$this->requests[ $requestId ]['response'],
					(float)$this->requests[ $requestId ]['duration']
				);

			default:
				throw new ReadFailedException( 'Unknown content.' );
		}
	}
}
