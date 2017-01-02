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

namespace hollodotme\FastCGI;

use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ForbiddenException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedOutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;

/**
 * Class Client
 * @package hollodotme\FastCGI
 */
class Client
{
	const VERSION_1         = 1;

	const BEGIN_REQUEST     = 1;

	const END_REQUEST       = 3;

	const PARAMS            = 4;

	const STDIN             = 5;

	const STDOUT            = 6;

	const STDERR            = 7;

	const GET_VALUES        = 9;

	const GET_VALUES_RESULT = 10;

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
	private $socketConnection;

	/** @var resource */
	private $socket;

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

	public function __construct( ConfiguresSocketConnection $socketConnection )
	{
		$this->socketConnection = $socketConnection;
	}

	private function connect()
	{
		if ( $this->socket === null )
		{
			if ( $this->socketConnection->isPersistent() )
			{
				$this->socket = pfsockopen(
					$this->socketConnection->getHost(),
					$this->socketConnection->getPort(),
					$errorNumber,
					$errorString,
					$this->socketConnection->getConnectTimeout() / 1000
				);
			}
			else
			{
				$this->socket = fsockopen(
					$this->socketConnection->getHost(),
					$this->socketConnection->getPort(),
					$errorNumber,
					$errorString,
					$this->socketConnection->getConnectTimeout() / 1000
				);
			}

			if ( $this->socket === false )
			{
				throw new ConnectException( 'Unable to connect to FastCGI application: ' . $errorString );
			}

			if ( !$this->setStreamTimeout( $this->socketConnection->getReadWriteTimeout() ) )
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

		return stream_set_timeout( $this->socket, floor( $timeoutMs / 1000 ), ($timeoutMs % 1000) * 1000 );
	}

	/**
	 * Build a FastCGI packet
	 *
	 * @param int    $type      Type of the packet
	 * @param string $content   Content of the packet
	 * @param int    $requestId RequestId
	 *
	 * @return string
	 */
	private function buildPacket( int $type, string $content, int $requestId = 1 ) : string
	{
		$contentLength = strlen( $content );

		return chr( self::VERSION_1 )                   /* version */
		       . chr( $type )                           /* type */
		       . chr( ($requestId >> 8) & 0xFF )        /* requestIdB1 */
		       . chr( $requestId & 0xFF )               /* requestIdB0 */
		       . chr( ($contentLength >> 8) & 0xFF )    /* contentLengthB1 */
		       . chr( $contentLength & 0xFF )           /* contentLengthB0 */
		       . chr( 0 )                               /* paddingLength */
		       . chr( 0 )                               /* reserved */
		       . $content;                              /* content */
	}

	/**
	 * Build an FastCGI Name value pair
	 *
	 * @param string $name  Name
	 * @param string $value Value
	 *
	 * @return string FastCGI Name value pair
	 */
	private function buildNameValuePair( string $name, string $value ) : string
	{
		$nameLength  = strlen( $name );
		$valueLength = strlen( $value );

		if ( $nameLength < 128 )
		{
			/* nameLengthB0 */
			$nameValuePair = chr( $nameLength );
		}
		else
		{
			/* nameLengthB3 & nameLengthB2 & nameLengthB1 & nameLengthB0 */
			$nameValuePair = chr( ($nameLength >> 24) | 0x80 )
			                 . chr( ($nameLength >> 16) & 0xFF )
			                 . chr( ($nameLength >> 8) & 0xFF )
			                 . chr( $nameLength & 0xFF );
		}
		if ( $valueLength < 128 )
		{
			/* valueLengthB0 */
			$nameValuePair .= chr( $valueLength );
		}
		else
		{
			/* valueLengthB3 & valueLengthB2 & valueLengthB1 & valueLengthB0 */
			$nameValuePair .= chr( ($valueLength >> 24) | 0x80 )
			                  . chr( ($valueLength >> 16) & 0xFF )
			                  . chr( ($valueLength >> 8) & 0xFF )
			                  . chr( $valueLength & 0xFF );
		}

		/* nameData & valueData */

		return $nameValuePair . $name . $value;
	}

	/**
	 * Read a set of FastCGI Name value pairs
	 *
	 * @param string   $data Data containing the set of FastCGI NVPair
	 * @param int|null $length
	 *
	 * @return array of NVPair
	 */
	private function readNameValuePairs( string $data, ?int $length = null ) : array
	{
		$array = [];

		if ( $length === null )
		{
			$length = strlen( $data );
		}

		$p = 0;

		while ( $p != $length )
		{
			$nameLength = ord( $data{$p++} );
			if ( $nameLength >= 128 )
			{
				$nameLength = ($nameLength & 0x7F << 24);
				$nameLength |= (ord( $data{$p++} ) << 16);
				$nameLength |= (ord( $data{$p++} ) << 8);
				$nameLength |= (ord( $data{$p++} ));
			}

			$valueLength = ord( $data{$p++} );
			if ( $valueLength >= 128 )
			{
				$valueLength = ($nameLength & 0x7F << 24);
				$valueLength |= (ord( $data{$p++} ) << 16);
				$valueLength |= (ord( $data{$p++} ) << 8);
				$valueLength |= (ord( $data{$p++} ));
			}
			$array[ substr( $data, $p, $nameLength ) ] = substr( $data, $p + $nameLength, $valueLength );
			$p += ($nameLength + $valueLength);
		}

		return $array;
	}

	/**
	 * Decode a FastCGI Packet
	 *
	 * @param string $data String containing all the packet
	 *
	 * @return array
	 */
	private function decodePacketHeader( string $data ) : array
	{
		$ret                  = [];
		$ret['version']       = ord( $data{0} );
		$ret['type']          = ord( $data{1} );
		$ret['requestId']     = (ord( $data{2} ) << 8) + ord( $data{3} );
		$ret['contentLength'] = (ord( $data{4} ) << 8) + ord( $data{5} );
		$ret['paddingLength'] = ord( $data{6} );
		$ret['reserved']      = ord( $data{7} );

		return $ret;
	}

	/**
	 * Read a FastCGI Packet
	 * @return array|null
	 */
	private function readPacket() : ?array
	{
		if ( $packet = fread( $this->socket, self::HEADER_LEN ) )
		{
			$response            = $this->decodePacketHeader( $packet );
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
	 * Get Information on the FastCGI application
	 *
	 * @param array $requestedInfo information to retrieve
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function getValues( array $requestedInfo ) : array
	{
		$this->connect();

		$request = '';
		foreach ( $requestedInfo as $info )
		{
			$request .= $this->buildNameValuePair( $info, '' );
		}

		fwrite( $this->socket, $this->buildPacket( self::GET_VALUES, $request, 0 ) );

		$response = $this->readPacket();

		if ( $response !== null )
		{
			if ( isset( $response['type'] ) && $response['type'] == self::GET_VALUES_RESULT )
			{
				return $this->readNameValuePairs( $response['content'], $response['length'] );
			}
			else
			{
				throw new ReadFailedException( 'Unexpected response type, expecting GET_VALUES_RESULT' );
			}
		}

		throw new ReadFailedException( 'Got no response.' );
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
	 * @throws TimedOutException
	 * @throws WriteFailedException
	 * @return int
	 */
	public function sendAsyncRequest( array $params, string $content ) : int
	{
		$this->connect();

		// Pick random number between 1 and max 16 bit unsigned int 65535
		$requestId = mt_rand( 1, (1 << 16) - 1 );

		// Using persistent sockets implies you want them kept alive by server
		$keepAlive = intval( $this->socketConnection->keepAlive() || $this->socketConnection->isPersistent() );

		$request = $this->buildPacket(
			self::BEGIN_REQUEST,
			chr( 0 ) . chr( self::RESPONDER ) . chr( $keepAlive ) . str_repeat( chr( 0 ), 5 ),
			$requestId
		);

		$paramsRequest = '';

		foreach ( $params as $key => $value )
		{
			$paramsRequest .= $this->buildNameValuePair( $key, $value );
		}

		if ( $paramsRequest )
		{
			$request .= $this->buildPacket( self::PARAMS, $paramsRequest, $requestId );
		}

		$request .= $this->buildPacket( self::PARAMS, '', $requestId );

		if ( $content )
		{
			$request .= $this->buildPacket( self::STDIN, $content, $requestId );
		}

		$request .= $this->buildPacket( self::STDIN, '', $requestId );

		if ( fwrite( $this->socket, $request ) === false || fflush( $this->socket ) === false )
		{
			$info = stream_get_meta_data( $this->socket );

			if ( $info['timed_out'] )
			{
				throw new TimedOutException( 'Write timed out' );
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
	 * @throws TimedOutException
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
			$timeoutMs = $this->socketConnection->getReadWriteTimeout();
		}

		// Need to manually check since we might do several reads none of which timeout themselves
		// but still not get the response requested
		$startTime = microtime( true );

		do
		{
			$response = $this->readPacket();

			if ( $response['type'] == self::STDOUT || $response['type'] == self::STDERR )
			{
				if ( $response['type'] == self::STDERR )
				{
					$this->requests[ $response['requestId'] ]['state'] = self::REQ_STATE_ERR;
				}

				$this->requests[ $response['requestId'] ]['response'] .= $response['content'];
			}

			if ( $response['type'] == self::END_REQUEST )
			{
				$this->requests[ $response['requestId'] ]['state'] = self::REQ_STATE_OK;

				if ( $response['requestId'] == $requestId )
				{
					break;
				}
			}

			if ( microtime( true ) - $startTime >= ($timeoutMs * 1000) )
			{
				// Reset
				$this->setStreamTimeout( $this->socketConnection->getReadWriteTimeout() );

				throw new TimedOutException( 'Timed out' );
			}
		} while ( $response );

		if ( $response === null )
		{
			$info = stream_get_meta_data( $this->socket );

			// We must reset timeout but it must be AFTER we get info
			$this->setStreamTimeout( $this->socketConnection->getReadWriteTimeout() );

			if ( $info['timed_out'] )
			{
				throw new TimedOutException( 'Read timed out' );
			}

			if ( $info['unread_bytes'] == 0 && $info['blocked'] && $info['eof'] )
			{
				throw new ForbiddenException( 'Not in white list. Check listen.allowed_clients.' );
			}

			throw new ReadFailedException( 'Read failed' );
		}

		// Reset timeout
		$this->setStreamTimeout( $this->socketConnection->getReadWriteTimeout() );

		switch ( ord( $response['content']{4} ) )
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
