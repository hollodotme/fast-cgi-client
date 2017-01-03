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
use hollodotme\FastCGI\Timing\Timer;

/**
 * Class Client
 * @package hollodotme\FastCGI
 */
class Client
{
	const BEGIN_REQUEST     = 1;

	const END_REQUEST       = 3;

	const PARAMS            = 4;

	const STDIN             = 5;

	const STDOUT            = 6;

	const STDERR            = 7;

	const RESPONDER         = 1;

	const REQUEST_COMPLETE  = 0;

	const CANT_MPX_CONN     = 1;

	const OVERLOADED        = 2;

	const UNKNOWN_ROLE      = 3;

	const HEADER_LEN        = 8;

	const REQ_STATE_WRITTEN = 1;

	const REQ_STATE_OK      = 2;

	const REQ_STATE_ERR     = 3;

	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var resource */
	private $socket;

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
	}

	private function connect()
	{
		if ( $this->socket === null )
		{
			try
			{
				if ( $this->connection->isPersistent() )
				{
					$this->socket = pfsockopen(
						$this->connection->getHost(),
						$this->connection->getPort(),
						$errorNumber,
						$errorString,
						$this->connection->getConnectTimeout() / 1000
					);
				}
				else
				{
					$this->socket = fsockopen(
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

			if ( $this->socket === false )
			{
				throw new ConnectException( 'Unable to connect to FastCGI application: ' . $errorString );
			}

			if ( !$this->setStreamTimeout( $this->connection->getReadWriteTimeout() ) )
			{
				throw new ConnectException( 'Unable to set timeout on socket' );
			}
		}
	}

	private function setStreamTimeout( int $timeoutMs ) : bool
	{
		if ( !$this->socket )
		{
			return false;
		}

		return stream_set_timeout( $this->socket, (int)floor( $timeoutMs / 1000 ), ($timeoutMs % 1000) * 1000 );
	}

	/**
	 * Read a FastCGI PacketEncoder
	 * @return array|null
	 */
	private function readPacket()
	{
		if ( $packet = fread( $this->socket, self::HEADER_LEN ) )
		{
			$response            = $this->packetEncoder->decodeHeader( $packet );
			$response['content'] = '';

			if ( $response['contentLength'] )
			{
				$length = $response['contentLength'];

				while ( $length && ($buffer = fread( $this->socket, $length )) !== false )
				{
					$length -= strlen( $buffer );
					$response['content'] .= $buffer;
				}
			}

			if ( $response['paddingLength'] )
			{
				$response['content'] = fread( $this->socket, $response['paddingLength'] );
			}

			return $response;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Execute a request to the FastCGI application
	 *
	 * @param array  $params  Array of parameters
	 * @param string $content Content
	 *
	 * @return string
	 */
	public function sendRequest( array $params, string $content ) : string
	{
		$requestId = $this->sendAsyncRequest( $params, $content );

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
	 * @param array  $params  Array of parameters
	 * @param string $content Content
	 *
*@throws TimedoutException
	 * @throws WriteFailedException
	 * @return int
	 */
	public function sendAsyncRequest( array $params, string $content ) : int
	{
		$this->connect();

		// Pick random number between 1 and max 16 bit unsigned int 65535
		$requestId = mt_rand( 1, (1 << 16) - 1 );

		// Using persistent sockets implies you want them kept alive by server
		$keepAlive = intval( $this->connection->keepAlive() || $this->connection->isPersistent() );

		$request = $this->packetEncoder->encodePacket(
			self::BEGIN_REQUEST,
			chr( 0 ) . chr( self::RESPONDER ) . chr( $keepAlive ) . str_repeat( chr( 0 ), 5 ),
			$requestId
		);

		$paramsRequest = $this->nameValuePairEncoder->encodePairs( $params );

		if ( $paramsRequest )
		{
			$request .= $this->packetEncoder->encodePacket( self::PARAMS, $paramsRequest, $requestId );
		}

		$request .= $this->packetEncoder->encodePacket( self::PARAMS, '', $requestId );

		if ( $content )
		{
			$request .= $this->packetEncoder->encodePacket( self::STDIN, $content, $requestId );
		}

		$request .= $this->packetEncoder->encodePacket( self::STDIN, '', $requestId );

		if ( fwrite( $this->socket, $request ) === false || fflush( $this->socket ) === false )
		{
			$info = stream_get_meta_data( $this->socket );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Write timed out' );
			}

			// Broken pipe, tear down so future requests might succeed
			fclose( $this->socket );

			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}

		$this->requests[ $requestId ] = [
			'state'    => self::REQ_STATE_WRITTEN,
			'response' => null,
		];

		return $requestId;
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
	 * @return string
	 */
	public function waitForResponse( int $requestId, int $timeoutMs = 0 ) : string
	{
		if ( !isset( $this->requests[ $requestId ] ) )
		{
			throw new ReadFailedException( 'Invalid request id given' );
		}

		// If we already read the response during an earlier call for different id, just return it
		if ( in_array( $this->requests[ $requestId ]['state'], [ self::REQ_STATE_OK, self::REQ_STATE_ERR ] ) )
		{
			return $this->requests[ $requestId ]['response'];
		}

		if ( $timeoutMs > 0 )
		{
			// Reset timeout on socket for now
			$this->setStreamTimeout( $timeoutMs );
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
			$packet = $this->readPacket();

			if ( $packet['type'] == self::STDOUT || $packet['type'] == self::STDERR )
			{
				if ( $packet['type'] == self::STDERR )
				{
					$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_ERR;
				}

				$this->requests[ $packet['requestId'] ]['response'] .= $packet['content'];
			}

			if ( $packet['type'] == self::END_REQUEST )
			{
				$this->requests[ $packet['requestId'] ]['state'] = self::REQ_STATE_OK;

				if ( $packet['requestId'] == $requestId )
				{
					break;
				}
			}

			if ( $timer->timedOut() )
			{
				// Reset
				$this->setStreamTimeout( $this->connection->getReadWriteTimeout() );
				$timer->reset();

				throw new TimedoutException( 'Timed out' );
			}
		} while ( $packet );

		if ( $packet === null )
		{
			$info = stream_get_meta_data( $this->socket );

			// We must reset timeout but it must be AFTER we get info
			$this->setStreamTimeout( $this->connection->getReadWriteTimeout() );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Read timed out' );
			}

			if ( $info['unread_bytes'] == 0 && $info['blocked'] && $info['eof'] )
			{
				throw new ForbiddenException( 'Not in white list. Check listen.allowed_clients.' );
			}

			throw new ReadFailedException( 'Read failed' );
		}

		// Reset timeout
		$this->setStreamTimeout( $this->connection->getReadWriteTimeout() );

		switch ( ord( $packet['content']{4} ) )
		{
			case self::CANT_MPX_CONN:
				throw new WriteFailedException( 'This app can\'t multiplex [CANT_MPX_CONN]' );
				break;
			case self::OVERLOADED:
				throw new WriteFailedException( 'New request rejected; too busy [OVERLOADED]' );
				break;
			case self::UNKNOWN_ROLE:
				throw new WriteFailedException( 'Role value not known [UNKNOWN_ROLE]' );
				break;
			case self::REQUEST_COMPLETE:
				return $this->requests[ $requestId ]['response'];
				break;
			default:
				throw new ReadFailedException( 'Unknown content.' );
		}
	}
}
