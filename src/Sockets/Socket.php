<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Sockets;

use ErrorException;
use Exception;
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
use function is_resource;
use function max;
use function microtime;
use function min;
use function ord;
use function str_repeat;
use function stream_get_meta_data;
use function stream_select;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_shutdown;
use function strlen;
use function substr;

use const PHP_INT_MAX;
use const STREAM_SHUT_RDWR;

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

    private const SOCK_STATE_INIT      = 1;

    private const SOCK_STATE_BUSY      = 2;

    private const SOCK_STATE_IDLE      = 3;

    private const REQ_MAX_CONTENT_SIZE = 65535;

    public const  STREAM_SELECT_USEC   = 200000;

    private SocketId $id;

    private ConfiguresSocketConnection $connection;

    /** @var null|resource */
    private $resource;

    private EncodesPacket $packetEncoder;

    private EncodesNameValuePair $nameValuePairEncoder;

    /** @var callable[] */
    private array $responseCallbacks;

    /** @var callable[] */
    private array $failureCallbacks;

    /** @var callable[] */
    private array $passThroughCallbacks;

    private float $startTime;

    private ?ProvidesResponseData $response;

    private int $status;

    /**
     * @param SocketId                   $socketId
     * @param ConfiguresSocketConnection $connection
     * @param EncodesPacket              $packetEncoder
     * @param EncodesNameValuePair       $nameValuePairEncoder
     *
     * @throws Exception
     */
    public function __construct(
        SocketId $socketId,
        ConfiguresSocketConnection $connection,
        EncodesPacket $packetEncoder,
        EncodesNameValuePair $nameValuePairEncoder
    ) {
        $this->id                   = $socketId;
        $this->connection           = $connection;
        $this->packetEncoder        = $packetEncoder;
        $this->nameValuePairEncoder = $nameValuePairEncoder;
        $this->responseCallbacks    = [];
        $this->failureCallbacks     = [];
        $this->passThroughCallbacks = [];
        $this->status               = self::SOCK_STATE_INIT;
    }

    public function getId(): int
    {
        return $this->id->getValue();
    }

    public function usesConnection(ConfiguresSocketConnection $connection): bool
    {
        return $this->connection->equals($connection);
    }

    public function hasResponse(): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        $reads  = [$this->resource];
        $writes = $excepts = null;

        return (bool)stream_select($reads, $writes, $excepts, 0, self::STREAM_SELECT_USEC);
    }

    /**
     * @param ProvidesRequestData $request
     *
     * @throws ConnectException
     * @throws TimedoutException
     * @throws WriteFailedException
     */
    public function sendRequest(ProvidesRequestData $request): void
    {
        $this->guardSocketIsUsable();

        $this->response = null;

        $this->responseCallbacks    = $request->getResponseCallbacks();
        $this->failureCallbacks     = $request->getFailureCallbacks();
        $this->passThroughCallbacks = $request->getPassThroughCallbacks();

        $this->connect();

        $requestPackets = $this->getRequestPackets($request);

        $this->write($requestPackets);

        $this->status    = self::SOCK_STATE_BUSY;
        $this->startTime = microtime(true);
    }

    /**
     * @throws ConnectException
     */
    private function guardSocketIsUsable(): void
    {
        if (!$this->isIdle() || !$this->isUsable()) {
            throw new ConnectException('Trying to connect to a socket that is not idle.');
        }
    }

    public function isIdle(): bool
    {
        if (self::SOCK_STATE_INIT === $this->status) {
            return true;
        }

        if (self::SOCK_STATE_IDLE === $this->status) {
            return true;
        }

        return false;
    }

    public function isUsable(): bool
    {
        if (null === $this->resource) {
            return true;
        }

        if (!is_resource($this->resource)) {
            return false;
        }

        /** @var false|array<string, mixed> $metaData */
        $metaData = stream_get_meta_data($this->resource);

        if (false === $metaData) {
            return false;
        }

        return !($metaData['timed_out'] || $metaData['unread_bytes'] || $metaData['eof']);
    }

    public function isBusy(): bool
    {
        return self::SOCK_STATE_BUSY === $this->status;
    }

    /**
     * @throws ConnectException
     */
    private function connect(): void
    {
        if (is_resource($this->resource)) {
            return;
        }

        try {
            $resource = @stream_socket_client(
                $this->connection->getSocketAddress(),
                $errorNumber,
                $errorString,
                $this->connection->getConnectTimeout() / 1000
            );

            if (false !== $resource) {
                $this->resource = $resource;
            }

            $this->status = self::SOCK_STATE_IDLE;
        } catch (Throwable $e) {
            throw new ConnectException($e->getMessage(), $e->getCode(), $e);
        }

        $this->handleFailedResource($errorNumber, $errorString);

        if (!$this->setStreamTimeout($this->connection->getReadWriteTimeout())) {
            throw new ConnectException('Unable to set timeout on socket');
        }
    }

    /**
     * @param int|null    $errorNumber
     * @param string|null $errorString
     *
     * @throws ConnectException
     */
    private function handleFailedResource(?int $errorNumber, ?string $errorString): void
    {
        if (is_resource($this->resource)) {
            return;
        }

        $lastError          = error_get_last();
        $lastErrorException = null;

        if (null !== $lastError) {
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

    private function setStreamTimeout(int $timeoutMs): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        return stream_set_timeout(
            $this->resource,
            (int)floor($timeoutMs / 1000),
            ($timeoutMs % 1000) * 1000
        );
    }

    private function getRequestPackets(ProvidesRequestData $request): string
    {
        # Keep alive bit always set to 1
        $requestPackets = $this->packetEncoder->encodePacket(
            self::BEGIN_REQUEST,
            chr(0) . chr(self::RESPONDER) . chr(1) . str_repeat(chr(0), 5),
            $this->id->getValue()
        );

        $paramsRequest = $this->nameValuePairEncoder->encodePairs($request->getParams());

        if ($paramsRequest) {
            $requestPackets .= $this->packetEncoder->encodePacket(
                self::PARAMS,
                $paramsRequest,
                $this->id->getValue()
            );
        }

        $requestPackets .= $this->packetEncoder->encodePacket(self::PARAMS, '', $this->id->getValue());

        if ($request->getContent() !== null) {
            $offset = 0;
            do {
                $requestPackets .= $this->packetEncoder->encodePacket(
                    self::STDIN,
                    substr(
                        $request->getContent()->getContent(),
                        $offset,
                        self::REQ_MAX_CONTENT_SIZE
                    ),
                    $this->id->getValue()
                );
                $offset         += self::REQ_MAX_CONTENT_SIZE;
            } while ($offset < $request->getContentLength());
        }

        $requestPackets .= $this->packetEncoder->encodePacket(self::STDIN, '', $this->id->getValue());

        return $requestPackets;
    }

    /**
     * @param string $data
     *
     * @throws TimedoutException
     * @throws WriteFailedException
     */
    private function write(string $data): void
    {
        if (!is_resource($this->resource)) {
            throw new WriteFailedException('Failed to write request to socket [broken pipe]');
        }

        $writeResult = @fwrite($this->resource, $data);
        $flushResult = @fflush($this->resource);

        if ($writeResult === false || !$flushResult) {
            if (stream_get_meta_data($this->resource)['timed_out']) {
                throw new TimedoutException('Write timed out');
            }

            throw new WriteFailedException('Failed to write request to socket [broken pipe]');
        }
    }

    /**
     * @param int|null $timeoutMs
     *
     * @return ProvidesResponseData
     * @throws TimedoutException
     * @throws WriteFailedException
     * @throws ReadFailedException
     */
    public function fetchResponse(?int $timeoutMs = null): ProvidesResponseData
    {
        if (null !== $this->response) {
            return $this->response;
        }

        // Reset timeout on socket for reading
        $this->setStreamTimeout($timeoutMs ?? $this->connection->getReadWriteTimeout());

        $error  = '';
        $output = '';

        do {
            $packet = $this->readPacket();

            if (null === $packet) {
                break;
            }

            $packetType = (int)$packet['type'];

            if (self::STDERR === $packetType) {
                $error .= $packet['content'];
                $this->notifyPassThroughCallbacks('', $packet['content']);
                continue;
            }

            if (self::STDOUT === $packetType) {
                $output .= $packet['content'];
                $this->notifyPassThroughCallbacks($packet['content'], '');
                continue;
            }

            if (self::END_REQUEST === $packetType && $packet['requestId'] === $this->id->getValue()) {
                break;
            }
        } while (null !== $packet);

        $this->handleNullPacket($packet);
        $character = isset($packet['content']) ? ((string)$packet['content'])[4] : '';
        $this->guardRequestCompleted(ord($character));

        $this->response = new Response(
            $output,
            $error,
            microtime(true) - $this->startTime
        );

        # Set socket to idle again
        $this->status = self::SOCK_STATE_IDLE;

        return $this->response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readPacket(): ?array
    {
        if (!is_resource($this->resource)) {
            return null;
        }

        if ($header = fread($this->resource, self::HEADER_LEN)) {
            $packet            = $this->packetEncoder->decodeHeader($header);
            $packet['content'] = '';

            if ($packet['contentLength']) {
                $length = $this->getValidLength((int)$packet['contentLength']);

                while ($length && ($buffer = fread($this->resource, $length)) !== false) {
                    $length            = $this->getValidLength($length - strlen((string)$buffer));
                    $packet['content'] .= $buffer;
                }
            }

            if ($packet['paddingLength']) {
                /** @noinspection UnusedFunctionResultInspection */
                fread($this->resource, $this->getValidLength((int)$packet['paddingLength']));
            }

            return $packet;
        }

        return null;
    }

    /**
     * @param int $value
     *
     * @return int<0, max>
     */
    private function getValidLength(int $value): int
    {
        return (int)max(0, min($value, PHP_INT_MAX));
    }

    private function notifyPassThroughCallbacks(string $outputBuffer, string $errorBuffer): void
    {
        foreach ($this->passThroughCallbacks as $passThroughCallback) {
            $passThroughCallback($outputBuffer, $errorBuffer);
        }
    }

    /**
     * @param array<string, mixed>|null $packet
     *
     * @throws ReadFailedException
     * @throws TimedoutException
     */
    private function handleNullPacket(?array $packet): void
    {
        if ($packet === null && is_resource($this->resource)) {
            $info = stream_get_meta_data($this->resource);

            if ($info['timed_out']) {
                throw new TimedoutException('Read timed out');
            }

            if ($info['unread_bytes'] === 0 && $info['blocked'] && $info['eof']) {
                throw new ReadFailedException('Stream got blocked, or terminated.');
            }

            throw new ReadFailedException('Read failed');
        }
    }

    /**
     * @param int $flag
     *
     * @throws ReadFailedException
     * @throws WriteFailedException
     */
    private function guardRequestCompleted(int $flag): void
    {
        switch ($flag) {
            case self::REQUEST_COMPLETE:
                return;

            case self::CANT_MPX_CONN:
                throw new WriteFailedException('This app can\'t multiplex [CANT_MPX_CONN]');

            case self::OVERLOADED:
                throw new WriteFailedException('New request rejected; too busy [OVERLOADED]');

            case self::UNKNOWN_ROLE:
                throw new WriteFailedException('Role value not known [UNKNOWN_ROLE]');

            default:
                throw new ReadFailedException('Unknown content.');
        }
    }

    private function disconnect(): void
    {
        if (is_resource($this->resource)) {
            @stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
            fclose($this->resource);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function notifyResponseCallbacks(ProvidesResponseData $response): void
    {
        foreach ($this->responseCallbacks as $responseCallback) {
            $responseCallback($response);
        }
    }

    public function notifyFailureCallbacks(Throwable $throwable): void
    {
        foreach ($this->failureCallbacks as $failureCallback) {
            $failureCallback($throwable);
        }
    }

    /**
     * @param array<int, resource> $resources
     */
    public function collectResource(array &$resources): void
    {
        if (null !== $this->resource) {
            $resources[ (string)$this->id->getValue() ] = $this->resource;
        }
    }
}
