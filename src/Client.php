<?php declare(strict_types=1);

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
use InvalidArgumentException;
use Throwable;
use function count;
use function intdiv;
use function microtime;
use function stream_select;

class Client
{
	private SocketCollection $sockets;

	private EncodesPacket $packetEncoder;

	private EncodesNameValuePair $nameValuePairEncoder;

	public function __construct()
	{
		$this->packetEncoder        = new PacketEncoder();
		$this->nameValuePairEncoder = new NameValuePairEncoder();
		$this->sockets              = new SocketCollection();
	}

	/**
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
	 * @return int SocketId
	 *
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 */
	public function sendAsyncRequest( ConfiguresSocketConnection $connection, ProvidesRequestData $request ) : int
	{
		$socket = $this->sockets->getIdleSocket( $connection )
				  ?? $this->sockets->new( $connection, $this->packetEncoder, $this->nameValuePairEncoder );

		try
		{
			$socket->sendRequest( $request );

			return $socket->getId();
		}
		catch ( ConnectException | TimedoutException | WriteFailedException $e )
		{
			$this->sockets->remove( $socket->getId() );

			throw $e;
		}
	}

	/**
	 * Sends the request like sendRequest(), but retries on another socket if writing the request to the socket failed.
	 * Failures while reading the response are not retried, because the request may already have been processed.
	 *
	 * @param int                        $maxTries Maximum number of attempts to send the request
	 *
	 * @throws Throwable
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 * @throws InvalidArgumentException
	 */
	public function tryRequest(
		ConfiguresSocketConnection $connection,
		ProvidesRequestData $request,
		int $maxTries = 5
	) : ProvidesResponseData
	{
		$socketId = $this->tryAsyncRequest( $connection, $request, $maxTries );

		return $this->readResponse( $socketId );
	}

	/**
	 * Sends the request like sendAsyncRequest(), but retries on another socket
	 * if writing the request to the socket failed.
	 *
	 * @param int                        $maxTries Maximum number of attempts to send the request
	 *
	 * @return int SocketId
	 *
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 * @throws ConnectException
	 * @throws InvalidArgumentException
	 */
	public function tryAsyncRequest(
		ConfiguresSocketConnection $connection,
		ProvidesRequestData $request,
		int $maxTries = 5
	) : int
	{
		if ( $maxTries < 1 )
		{
			throw new InvalidArgumentException( 'Maximum number of tries must be at least 1, got: ' . $maxTries );
		}

		for ( $try = 1; $try < $maxTries; $try++ )
		{
			try
			{
				return $this->sendAsyncRequest( $connection, $request );
			}
			catch ( WriteFailedException )
			{
				# The broken socket was removed, the next try uses another one
			}
		}

		return $this->sendAsyncRequest( $connection, $request );
	}

	/**
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
	 * Waits until the response is received and notifies the response callbacks of the request.
	 * If there is no response within the timeout (default: the read/write timeout of the connection),
	 * the failure callbacks are notified with a TimedoutException.
	 *
	 * @throws ReadFailedException
	 */
	public function waitForResponse( int $socketId, ?int $timeoutMs = null ) : void
	{
		$socket       = $this->sockets->getById( $socketId );
		$waitingSince = microtime( true );

		while ( !$socket->hasResponse() )
		{
			if ( $this->isWaitingTimedOut( $socket, $waitingSince, $timeoutMs ) )
			{
				$this->notifyTimeout( $socket );

				return;
			}
		}

		$this->fetchResponseAndNotifyCallback( $socket, $timeoutMs );
	}

	/**
	 * Waits until all responses are received and notifies the callbacks of their requests.
	 * Requests without response within the timeout (default: the read/write timeout of their connection)
	 * notify their failure callbacks with a TimedoutException.
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

		$waitingSince = microtime( true );

		while ( $this->hasUnhandledResponses() )
		{
			$this->handleReadyResponses( $timeoutMs );

			foreach ( $this->sockets->getBusySockets() as $socket )
			{
				if ( $this->isWaitingTimedOut( $socket, $waitingSince, $timeoutMs ) )
				{
					$this->notifyTimeout( $socket );
				}
			}
		}
	}

	private function isWaitingTimedOut( Socket $socket, float $waitingSince, ?int $timeoutMs ) : bool
	{
		$timeoutSeconds = ($timeoutMs ?? $socket->getReadWriteTimeout()) / 1000;

		return microtime( true ) - $waitingSince >= $timeoutSeconds;
	}

	private function notifyTimeout( Socket $socket ) : void
	{
		try
		{
			$socket->notifyFailureCallbacks( new TimedoutException( 'Read timed out' ) );
		}
		finally
		{
			$this->sockets->remove( $socket->getId() );
		}
	}

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

	public function hasUnhandledResponses() : bool
	{
		return $this->sockets->hasBusySockets();
	}

	/**
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

		$reads     = $this->sockets->collectResourcesOfBusySockets();
		$writes    = $excepts = null;
		$timeoutMs = $this->sockets->getStreamSelectTimeout();

		$result = @stream_select(
			$reads,
			$writes,
			$excepts,
			intdiv( $timeoutMs, 1000 ),
			($timeoutMs % 1000) * 1000
		);

		if ( false === $result || 0 === count( $reads ) )
		{
			return [];
		}

		return $this->sockets->getSocketIdsByResources( $reads );
	}

	/**
	 * Reads the responses of the given sockets in the given order. Unknown socket IDs are skipped.
	 * If a response cannot be read, its exception is thrown and the remaining responses are not read.
	 *
	 * @param int      ...$socketIds
	 *
	 * @return Generator|ProvidesResponseData[]
	 * @throws ReadFailedException
	 * @throws TimedoutException
	 * @throws WriteFailedException
	 */
	public function readResponses( ?int $timeoutMs = null, int ...$socketIds ) : Generator
	{
		foreach ( $socketIds as $socketId )
		{
			try
			{
				$socket = $this->sockets->getById( $socketId );
			}
			catch ( ReadFailedException )
			{
				# Skip unknown socket IDs
				continue;
			}

			try
			{
				yield $socket->fetchResponse( $timeoutMs );
			}
			finally
			{
				$this->sockets->remove( $socketId );
			}
		}
	}

	/**
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
	 * @throws ReadFailedException
	 */
	public function handleReadyResponses( ?int $timeoutMs = null ) : void
	{
		$socketIds = $this->getSocketIdsHavingResponse();

		$this->handleResponses( $timeoutMs, ...$socketIds );
	}
}
