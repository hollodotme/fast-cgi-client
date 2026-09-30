<?php declare(strict_types=1);

namespace hollodotme\FastCGI\Sockets;

use ErrorException;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ConfiguresSocketConnection;
use hollodotme\FastCGI\Interfaces\EncodesNameValuePair;
use hollodotme\FastCGI\Interfaces\EncodesPacket;
use hollodotme\FastCGI\Interfaces\ProvidesRequestData;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\Responses\Response;
use Throwable;
use function chr;
use function error_get_last;
use function fclose;
use function fflush;
use function floor;
use function fread;
use function fwrite;
use function intdiv;
use function is_resource;
use function max;
use function microtime;
use function ord;
use function sprintf;
use function str_repeat;
use function stream_get_meta_data;
use function stream_select;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_shutdown;
use function strlen;
use function substr;
use const PHP_VERSION_ID;
use const STREAM_SHUT_RDWR;

final class Socket
{
	private const BEGIN_REQUEST        = 1;

	private const END_REQUEST          = 3;

	private const PARAMS               = 4;

	private const STDIN                = 5;

	private const STDOUT               = 6;

	private const STDERR               = 7;

	private const GET_VALUES_RESULT    = 10;

	private const UNKNOWN_TYPE         = 11;

	private const VERSION              = 1;

	private const NULL_REQUEST_ID      = 0;

	private const END_REQUEST_LEN      = 8;

	private const RESPONDER            = 1;

	private const REQUEST_COMPLETE     = 0;

	private const CANT_MPX_CONN        = 1;

	private const OVERLOADED           = 2;

	private const UNKNOWN_ROLE         = 3;

	private const HEADER_LEN           = 8;

	private const SOCK_STATE_INIT      = 1;

	private const SOCK_STATE_BUSY      = 2;

	private const SOCK_STATE_IDLE      = 3;

	private const REQ_MAX_CONTENT_SIZE = 65535;

	/** @var null|resource */
	private $resource;

	/** @var callable[] */
	private array $responseCallbacks = [];

	/** @var callable[] */
	private array $failureCallbacks = [];

	/** @var callable[] */
	private array $passThroughCallbacks = [];

	private float $startTime;

	private ?ProvidesResponseData $response = null;

	private int $status = self::SOCK_STATE_INIT;

	public function __construct(
		private SocketId $socketId,
		private ConfiguresSocketConnection $connection,
		private EncodesPacket $packetEncoder,
		private EncodesNameValuePair $nameValuePairEncoder
	)
	{
	}

	public function getId() : int
	{
		return $this->socketId->getValue();
	}

	/**
	 * Returns the ID that identifies the request of this socket in the FastCGI records sent to and received
	 * from the server. It is not the same thing as the ID of the socket, which identifies the socket in the client.
	 *
	 * A socket handles one request at a time and does not multiplex requests, so one request ID per socket
	 * is sufficient. It is derived from the ID of the socket and re-used for every request sent via this socket,
	 * which the FastCGI specification allows as soon as the previous request is completed.
	 */
	private function getRequestId() : int
	{
		return $this->socketId->getValue();
	}

	public function usesConnection( ConfiguresSocketConnection $connection ) : bool
	{
		return $this->connection->equals( $connection );
	}

	public function hasResponse() : bool
	{
		if ( !is_resource( $this->resource ) )
		{
			return false;
		}

		$reads  = [$this->resource];
		$writes = $excepts = null;

		$timeoutMs = $this->getStreamSelectTimeout();

		return (bool)stream_select( $reads, $writes, $excepts, intdiv( $timeoutMs, 1000 ), ($timeoutMs % 1000) * 1000 );
	}

	/**
	 * @return int Timeout in milliseconds
	 */
	public function getStreamSelectTimeout() : int
	{
		return max( 0, $this->connection->getStreamSelectTimeout() );
	}

	/**
	 * @throws ConnectException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function sendRequest( ProvidesRequestData $request ) : void
	{
		$this->guardSocketIsUsable();

		$this->response = null;

		$this->responseCallbacks    = $request->getResponseCallbacks();
		$this->failureCallbacks     = $request->getFailureCallbacks();
		$this->passThroughCallbacks = $request->getPassThroughCallbacks();

		$this->connect();

		$requestPackets = $this->getRequestPackets( $request );

		$this->write( $requestPackets );

		$this->status    = self::SOCK_STATE_BUSY;
		$this->startTime = microtime( true );
	}

	/**
	 * @throws ConnectException
	 */
	private function guardSocketIsUsable() : void
	{
		if ( !$this->isIdle() || !$this->isUsable() )
		{
			throw new ConnectException( 'Trying to connect to a socket that is not idle.' );
		}
	}

	public function isIdle() : bool
	{
		if ( self::SOCK_STATE_INIT === $this->status )
		{
			return true;
		}

		if ( self::SOCK_STATE_IDLE === $this->status )
		{
			return true;
		}

		return false;
	}

	public function isUsable() : bool
	{
		if ( null === $this->resource )
		{
			return true;
		}

		if ( !is_resource( $this->resource ) )
		{
			return false;
		}

		/** @var false|array<string, mixed> $metaData */
		$metaData = stream_get_meta_data( $this->resource );

		if ( false === $metaData )
		{
			return false;
		}

		if ( $metaData['timed_out'] || $metaData['unread_bytes'] || $metaData['eof'] )
		{
			return false;
		}

		# There is nothing to read from an idle socket, unless the connection was closed by the peer
		return !($this->isIdle() && $this->isReadable());
	}

	private function isReadable() : bool
	{
		if ( !is_resource( $this->resource ) )
		{
			return false;
		}

		$reads  = [$this->resource];
		$writes = $excepts = null;

		return (bool)@stream_select( $reads, $writes, $excepts, 0, 0 );
	}

	public function isBusy() : bool
	{
		return self::SOCK_STATE_BUSY === $this->status;
	}

	/**
	 * @throws ConnectException
	 */
	private function connect() : void
	{
		if ( is_resource( $this->resource ) )
		{
			return;
		}

		try
		{
			$resource = @stream_socket_client(
				$this->connection->getSocketAddress(),
				$errorNumber,
				$errorString,
				$this->connection->getConnectTimeout() / 1000
			);

			if ( false !== $resource )
			{
				$this->resource = $resource;
			}

			$this->status = self::SOCK_STATE_IDLE;
		}
		catch ( Throwable $e )
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
	 * @throws ConnectException
	 */
	private function handleFailedResource( ?int $errorNumber, ?string $errorString ) : void
	{
		if ( is_resource( $this->resource ) )
		{
			return;
		}

		$lastError          = error_get_last();
		$lastErrorException = null;

		if ( null !== $lastError )
		{
			$lastErrorException = new ErrorException(
				$lastError['message'],
				0,
				$lastError['type'],
				$lastError['file'],
				$lastError['line']
			);
		}

		throw new ConnectException(
			'Unable to connect to FastCGI application: ' . $errorString,
			(int)$errorNumber,
			$lastErrorException
		);
	}

	private function setStreamTimeout( int $timeoutMs ) : bool
	{
		if ( !is_resource( $this->resource ) )
		{
			return false;
		}

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
			chr( 0 ) . chr( self::RESPONDER ) . chr( 1 ) . str_repeat( chr( 0 ), 5 ),
			$this->getRequestId()
		);

		$paramsRequest = $this->nameValuePairEncoder->encodePairs( $request->getParams() );

		if ( $paramsRequest )
		{
			$requestPackets .= $this->packetEncoder->encodePacket(
				self::PARAMS,
				$paramsRequest,
				$this->getRequestId()
			);
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::PARAMS, '', $this->getRequestId() );

		if ( $request->getContent() !== null )
		{
			$offset = 0;
			do
			{
				$requestPackets .= $this->packetEncoder->encodePacket(
					self::STDIN,
					substr(
						$request->getContent()->getContent(),
						$offset,
						self::REQ_MAX_CONTENT_SIZE
					),
					$this->getRequestId()
				);
				$offset         += self::REQ_MAX_CONTENT_SIZE;
			}
			while ( $offset < $request->getContentLength() );
		}

		$requestPackets .= $this->packetEncoder->encodePacket( self::STDIN, '', $this->getRequestId() );

		return $requestPackets;
	}

	/**
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	private function write( string $data ) : void
	{
		if ( !is_resource( $this->resource ) )
		{
			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}

		$writeResult = @fwrite( $this->resource, $data );
		$flushResult = @fflush( $this->resource );

		if ( $writeResult === false || !$flushResult )
		{
			if ( stream_get_meta_data( $this->resource )['timed_out'] )
			{
				throw new TimedoutException( 'Write timed out' );
			}

			throw new WriteFailedException( 'Failed to write request to socket [broken pipe]' );
		}
	}

	/**
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ReadFailedException
	 */
	public function fetchResponse( ?int $timeoutMs = null ) : ProvidesResponseData
	{
		if ( null !== $this->response )
		{
			return $this->response;
		}

		// Reset timeout on socket for reading
		$this->setStreamTimeout( $timeoutMs ?? $this->connection->getReadWriteTimeout() );

		$error  = '';
		$output = '';

		do
		{
			$packet = $this->readPacket();

			if ( null === $packet )
			{
				break;
			}

			$packetType = (int)$packet['type'];

			if ( self::STDERR === $packetType )
			{
				$error .= $packet['content'];
				$this->notifyPassThroughCallbacks( '', $packet['content'] );
				continue;
			}

			if ( self::STDOUT === $packetType )
			{
				$output .= $packet['content'];
				$this->notifyPassThroughCallbacks( $packet['content'], '' );
				continue;
			}

			# The request ID of the record was already validated when the packet was read
			if ( self::END_REQUEST === $packetType )
			{
				break;
			}
		}
		while ( true );

		$this->handleNullPacket( $packet );
		$character = isset( $packet['content'] ) ? ((string)$packet['content'])[4] : '';
		$this->guardRequestCompleted( ord( $character ) );

		$this->response = new Response(
			$output,
			$error,
			microtime( true ) - $this->startTime
		);

		# Set socket to idle again
		$this->status = self::SOCK_STATE_IDLE;

		return $this->response;
	}

	/**
	 * @return array<string, mixed>|null
	 * @throws ReadFailedException
	 */
	private function readPacket() : ?array
	{
		$header = $this->read( self::HEADER_LEN );

		if ( null === $header )
		{
			return null;
		}

		$packet = $this->packetEncoder->decodeHeader( $header );

		$this->guardPacketHeaderIsValid( $packet );

		$content = $this->read( (int)$packet['contentLength'] );
		$padding = $this->read( (int)$packet['paddingLength'] );

		if ( null === $content || null === $padding )
		{
			return null;
		}

		$packet['content'] = $content;

		return $packet;
	}

	/**
	 * Reads exactly the given number of bytes from the stream.
	 * Returns NULL, if the stream ended or timed out before all bytes were received.
	 */
	private function read( int $length ) : ?string
	{
		$resource = $this->resource;

		if ( !is_resource( $resource ) )
		{
			return null;
		}

		$data = '';

		while ( $length > 0 )
		{
			$buffer = fread( $resource, $length );

			# An empty string means that the stream timed out or was closed by the peer,
			# further attempts to read would return an empty string over and over again
			if ( false === $buffer || '' === $buffer )
			{
				return null;
			}

			$data   .= $buffer;
			$length -= strlen( $buffer );

			# Before PHP 8.3 an incomplete read only returns after the timeout was reached,
			# so there is no point in waiting for the rest once again.
			# Since PHP 8.3 incomplete reads are the normal case for large packets and must not be checked.
			if ( $length > 0 && PHP_VERSION_ID < 80300 && stream_get_meta_data( $resource )['timed_out'] )
			{
				return null;
			}
		}

		return $data;
	}

	/**
	 * A responder application only sends stdout, stderr and end-request records for the ID of the current request,
	 * and replies to management records with a request ID of zero.
	 * Everything else did not come from a FastCGI server or belongs to another request.
	 *
	 * @param array<string, int> $header
	 *
	 * @throws ReadFailedException
	 */
	private function guardPacketHeaderIsValid( array $header ) : void
	{
		if ( self::VERSION !== $header['version'] )
		{
			throw new ReadFailedException(
				'Not a FastCGI packet: unsupported protocol version ' . $header['version']
			);
		}

		$type      = $header['type'];
		$requestId = $header['requestId'];

		if ( self::GET_VALUES_RESULT === $type || self::UNKNOWN_TYPE === $type )
		{
			if ( self::NULL_REQUEST_ID !== $requestId )
			{
				throw new ReadFailedException(
					'Invalid FastCGI packet: management record with request ID ' . $requestId
				);
			}

			return;
		}

		if ( self::STDOUT !== $type && self::STDERR !== $type && self::END_REQUEST !== $type )
		{
			throw new ReadFailedException( 'Invalid FastCGI packet: unexpected record type ' . $type );
		}

		if ( $this->getRequestId() !== $requestId )
		{
			throw new ReadFailedException(
				sprintf(
					'Invalid FastCGI packet: expected request ID %d, got %d',
					$this->getRequestId(),
					$requestId
				)
			);
		}

		if ( self::END_REQUEST === $type && self::END_REQUEST_LEN !== $header['contentLength'] )
		{
			throw new ReadFailedException(
				'Invalid FastCGI packet: unexpected length of end-request record ' . $header['contentLength']
			);
		}
	}

	private function notifyPassThroughCallbacks( string $outputBuffer, string $errorBuffer ) : void
	{
		foreach ( $this->passThroughCallbacks as $passThroughCallback )
		{
			$passThroughCallback( $outputBuffer, $errorBuffer );
		}
	}

	/**
	 * @param array<string, mixed>|null $packet
	 *
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 */
	private function handleNullPacket( ?array $packet ) : void
	{
		if ( $packet === null && is_resource( $this->resource ) )
		{
			$info = stream_get_meta_data( $this->resource );

			if ( $info['timed_out'] )
			{
				throw new TimedoutException( 'Read timed out' );
			}

			if ( $info['unread_bytes'] === 0 && $info['blocked'] && $info['eof'] )
			{
				throw new ReadFailedException( 'Stream got blocked, or terminated.' );
			}

			throw new ReadFailedException( 'Read failed' );
		}
	}

	/**
	 * @throws ReadFailedException
	 * @throws WriteFailedException
	 */
	private function guardRequestCompleted( int $flag ) : void
	{
		match ( $flag )
		{
			self::REQUEST_COMPLETE => null,
			self::CANT_MPX_CONN    => throw new WriteFailedException( 'This app can\'t multiplex [CANT_MPX_CONN]' ),
			self::OVERLOADED       => throw new WriteFailedException( 'New request rejected; too busy [OVERLOADED]' ),
			self::UNKNOWN_ROLE     => throw new WriteFailedException( 'Role value not known [UNKNOWN_ROLE]' ),
			default                => throw new ReadFailedException( 'Unknown content.' ),
		};
	}

	private function disconnect() : void
	{
		if ( is_resource( $this->resource ) )
		{
			@stream_socket_shutdown( $this->resource, STREAM_SHUT_RDWR );
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

	public function notifyFailureCallbacks( Throwable $throwable ) : void
	{
		foreach ( $this->failureCallbacks as $failureCallback )
		{
			$failureCallback( $throwable );
		}
	}

	/**
	 * @param array<int, resource> $resources
	 */
	public function collectResource( array &$resources ) : void
	{
		if ( null !== $this->resource )
		{
			$resources[ (string)$this->socketId->getValue() ] = $this->resource;
		}
	}
}
