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

use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ForbiddenException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\EncodesNameValuePair;
use hollodotme\FastCGI\Interfaces\EncodesPacket;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Responses\Response;

/**
 * Class Socket
 * @package hollodotme\FastCGI
 */
final class Socket
{
	private const BEGIN_REQUEST        = 1;

	private const END_REQUEST          = 3;

	private const PARAMS               = 4;

	private const STDIN                = 5;

	private const STDOUT               = 6;

	private const STDERR               = 7;

	private const RESPONDER            = 1;

	private const REQUEST_COMPLETE     = 0;

	private const CANT_MPX_CONN        = 1;

	private const OVERLOADED           = 2;

	private const UNKNOWN_ROLE         = 3;

	private const HEADER_LEN           = 8;

	private const REQ_STATE_WRITTEN    = 1;

	private const REQ_STATE_OK         = 2;

	private const REQ_STATE_ERR        = 3;

	private const REQ_STATE_UNKNOWN    = 4;

	private const REQ_MAX_CONTENT_SIZE = 65535;

	public const  STREAM_SELECT_USEC   = 20000;

	/** @var int */
	private $id;

	/** @var ConfiguresSocketConnection */
	private $connection;

	/** @var resource */
	private $resource;

	/** @var EncodesPacket */
	private $packetEncoder;

	/** @var EncodesNameValuePair */
	private $nameValuePairEncoder;

	/** @var callable[] */
	private $responseCallbacks;

	/** @var callable[] */
	private $failureCallbacks;

	/** @var callable[] */
	private $passThroughCallbacks;

	/** @var float */
	private $startTime;

	/** @var ProvidesResponseData */
	private $response;

	/** @var int */
	private $status;

	/**
	 * @param ConfiguresSocketConnection $connection
	 * @param EncodesPacket              $packetEncoder
	 * @param EncodesNameValuePair       $nameValuePairEncoder
	 *
	 * @throws \Exception
	 */
	public function __construct(
		ConfiguresSocketConnection $connection,
		EncodesPacket $packetEncoder,
		EncodesNameValuePair $nameValuePairEncoder
	)
	{
		$this->id                   = random_int( 1, (1 << 16) - 1 );
		$this->connection           = $connection;
		$this->packetEncoder        = $packetEncoder;
		$this->nameValuePairEncoder = $nameValuePairEncoder;
		$this->responseCallbacks    = [];
		$this->failureCallbacks     = [];
		$this->passThroughCallbacks = [];
		$this->status               = self::REQ_STATE_UNKNOWN;
	}

	public function getId() : int
	{
		return $this->id;
	}

	public function hasResponse() : bool
	{
		$reads  = [$this->resource];
		$writes = $excepts = null;

		return (bool)stream_select( $reads, $writes, $excepts, 0, self::STREAM_SELECT_USEC );
	}

	/**
	 * @param ProvidesRequestData $request
	 *
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function sendRequest( ProvidesRequestData $request ) : void
	{
		$this->responseCallbacks    = $request->getResponseCallbacks();
		$this->failureCallbacks     = $request->getFailureCallbacks();
		$this->passThroughCallbacks = $request->getPassThroughCallbacks();

		$this->connect();

		$requestPackets = $this->getRequestPackets( $request );

		$this->write( $requestPackets );

		$this->status    = self::REQ_STATE_WRITTEN;
		$this->startTime = microtime( true );
	}

	/**
	 * @throws ConnectException
	 */
	private function connect() : void
	{
		try
		{
			$this->resource = @stream_socket_client(
				$this->connection->getSocketAddress(),
				$errorNumber,
				$errorString,
				$this->connection->getConnectTimeout() / 1000
			);
		}
		catch ( \Throwable $e )
		{
			throw new ConnectException( $e->getMessage(), $e->getCode(), $e );
		}

		$this->handleFailedResource( $errorNumber, $errorString );

		if ( !$this->setStreamTimeout( $this->connection->getReadWriteTimeout() ) )
		{
			throw new ConnectException( 'Unable to set timeout on socket' );
		}
	}

	/**
	 * @param $errorNumber
	 * @param $errorString
	 *
	 * @throws ConnectException
	 */
	private function handleFailedResource( $errorNumber, $errorString ) : void
	{
		if ( $this->resource !== false )
		{
			return;
		}

		$lastError          = error_get_last();
		$lastErrorException = null;

		if ( null !== $lastError )
		{
			$lastErrorException = new \ErrorException(
				$lastError['message'] ?? '[No message available]',
				0,
				$lastError['type'] ?? E_ERROR,
				$lastError['file'] ?? '[No file available]',
				$lastError['line'] ?? '[No line available]'
			);
		}

		throw new ConnectException(
			'Unable to connect to FastCGI application: ' . $errorString,
			$errorNumber,
			$lastErrorException
		);
	}

	private function setStreamTimeout( int $timeoutMs ) : bool
	{
		return stream_set_timeout(
			$this->resource,
			(int)floor( $timeoutMs / 1000 ),
			($timeoutMs % 1000) * 1000
		);
	}

	private function getRequestPackets( ProvidesRequestData $request ) : string
	{
		# Keep alive bit always set to 1
		$requestPackets = $this->packetEncoder->encodePacket(
			self::BEGIN_REQUEST,
			\chr( 0 ) . \chr( self::RESPONDER ) . \chr( 1 ) . str_repeat( \chr( 0 ), 5 ),
			$this->id
		);

		$paramsRequest = $this->nameValuePairEncoder->encodePairs( $request->getParams() );

		if ( $paramsRequest )
		{
			$requestPackets .= $this->packetEncoder->encodePacket( self::PARAMS, $paramsRequest, $this->id );
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::PARAMS, '', $this->id );

		if ( $request->getContent() )
		{
			$offset = 0;
			do
			{
				$requestPackets .= $this->packetEncoder->encodePacket( self::STDIN, substr( $request->getContent(), $offset, self::REQ_MAX_CONTENT_SIZE ), $this->id );
				$offset         += self::REQ_MAX_CONTENT_SIZE;
			}
			while ( $offset < $request->getContentLength() );
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::STDIN, '', $this->id );

		return $requestPackets;
	}

	/**
	 * @param string $data
	 *
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	private function write( string $data ) : void
	{
		$writeResult = fwrite( $this->resource, $data );
		$flushResult = fflush( $this->resource );

		if ( $writeResult === false || $flushResult === false )
		{
			$info = stream_get_meta_data( $this->resource );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Write timed out' );
			}

			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}
	}

	/**
	 * @param int|null $timeoutMs
	 *
	 * @return ProvidesResponseData
	 * @throws \Throwable
	 */
	public function fetchResponse( ?int $timeoutMs = null ) : ProvidesResponseData
	{
		if ( null !== $this->response )
		{
			return $this->response;
		}

		// Reset timeout on socket for reading
		$this->setStreamTimeout( $timeoutMs ?? $this->connection->getReadWriteTimeout() );

		$responseContent = '';

		do
		{
			$packet = $this->readPacket();

			switch ( (int)$packet['type'] )
			{
				case self::STDERR:
					$this->status    = self::REQ_STATE_ERR;
					$responseContent .= $packet['content'];
					$this->notifyPassThroughCallbacks( $packet['content'] );
					break;

				case self::STDOUT:
					$responseContent .= $packet['content'];
					$this->notifyPassThroughCallbacks( $packet['content'] );
					break;

				case self::END_REQUEST:
					if ( $packet['requestId'] === $this->id )
					{
						$this->status = self::REQ_STATE_OK;
						break 2;
					}
					break;
			}
		}
		while ( null !== $packet );

		try
		{
			$this->handleNullPacket( $packet );
			$this->guardRequestCompleted( \ord( $packet['content']{4} ) );

			$this->response = new Response(
				$this->id,
				$responseContent,
				microtime( true ) - $this->startTime
			);

			return $this->response;
		}
		catch ( \Throwable $e )
		{
			throw $e;
		}
		finally
		{
			$this->disconnect();
		}
	}

	private function readPacket() : ?array
	{
		if ( $header = fread( $this->resource, self::HEADER_LEN ) )
		{
			$packet            = $this->packetEncoder->decodeHeader( $header );
			$packet['content'] = '';

			if ( $packet['contentLength'] )
			{
				$length = $packet['contentLength'];

				while ( $length && ($buffer = fread( $this->resource, $length )) !== false )
				{
					$length            -= \strlen( $buffer );
					$packet['content'] .= $buffer;
				}
			}

			if ( $packet['paddingLength'] )
			{
				fread( $this->resource, $packet['paddingLength'] );
			}

			return $packet;
		}

		return null;
	}

	private function notifyPassThroughCallbacks( string $buffer ) : void
	{
		foreach ( $this->passThroughCallbacks as $passThroughCallback )
		{
			$passThroughCallback( $buffer );
		}
	}

	/**
	 * @param $packet
	 *
	 * @throws ForbiddenException
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 */
	private function handleNullPacket( $packet ) : void
	{
		if ( $packet === null )
		{
			$info = stream_get_meta_data( $this->resource );

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
	}

	/**
	 * @param int $flag
	 *
	 * @throws ReadFailedException
	 * @throws WriteFailedException
	 */
	private function guardRequestCompleted( int $flag ) : void
	{
		switch ( $flag )
		{
			case self::REQUEST_COMPLETE:
				return;

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

	private function disconnect() : void
	{
		if ( \is_resource( $this->resource ) )
		{
			fclose( $this->resource );
		}
	}

	public function __destruct()
	{
		$this->disconnect();
	}

	public function notifyResponseCallbacks( ProvidesResponseData $response ) : void
	{
		foreach ( $this->responseCallbacks as $responseCallback )
		{
			$responseCallback( $response );
		}
	}

	public function notifyFailureCallbacks( \Throwable $throwable ) : void
	{
		foreach ( $this->failureCallbacks as $failureCallback )
		{
			$failureCallback( $throwable );
		}
	}

	public function collectResource( array &$resources ) : void
	{
		if ( null !== $this->resource )
		{
			$resources[ $this->id ] = $this->resource;
		}
	}
}
