<?php

declare(strict_types=1);

namespace hollodotme\FastCGI\Tests\Integration\Signals;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\ConnectException;
use hollodotme\FastCGI\Exceptions\ReadFailedException;
use hollodotme\FastCGI\Exceptions\TimedoutException;
use hollodotme\FastCGI\Exceptions\WriteFailedException;
use hollodotme\FastCGI\Interfaces\ProvidesResponseData;
use hollodotme\FastCGI\RequestContents\UrlEncodedFormData;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use hollodotme\FastCGI\Tests\Traits\SocketDataProviding;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Throwable;

use function escapeshellarg;
use function exec;
use function preg_match;
use function shell_exec;
use function sleep;
use function sprintf;
use function usleep;

final class SignaledWorkersTest extends TestCase
{
    use SocketDataProviding;

    private function getWorkerPath(string $workerFile): string
    {
        return sprintf('%s/Workers/%s', dirname(__DIR__), $workerFile);
    }

    /**
     * @param int $signal
     *
     * @throws ConnectException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReadFailedException
     * @throws Throwable
     * @throws TimedoutException
     * @throws WriteFailedException
     *
     * @dataProvider signalProvider
     */
    public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnNetworkSocket(int $signal): void
    {
        $client   = new Client();
        $request  = new PostRequest($this->getWorkerPath('worker.php'));
        $success  = [];
        $failures = [];

        $request->addResponseCallbacks(
            static function (ProvidesResponseData $response) use (&$success) {
                $success[] = (int)$response->getBody();
            }
        );

        $request->addFailureCallbacks(
            static function (Throwable $e) use (&$failures) {
                $failures[] = $e;
            }
        );

        for ($i = 0; $i < 3; $i++) {
            $request->setContent(new UrlEncodedFormData(['test-key' => $i]));

            $client->sendAsyncRequest($this->getNetworkSocketConnection(), $request);
        }

        $pids = $this->getPoolWorkerPIDs('pool network');

        $this->killPoolWorker((int)$pids[0], $signal);

        $client->waitForResponses();

        self::assertCount(2, $success);
        self::assertCount(1, $failures);
        self::assertContainsOnlyInstancesOf(ReadFailedException::class, $failures);

        sleep(1);
    }

    /**
     * @return array<array<string, int>>
     */
    public function signalProvider(): array
    {
        return [
            [
                # SIGHUP
                'signal' => 1,
            ],
            [
                # SIGINT
                'signal' => 2,
            ],
            [
                # SIGKILL
                'signal' => 9,
            ],
            [
                # SIGTERM
                'signal' => 15,
            ],
        ];
    }

    private function getNetworkSocketConnection(): NetworkSocket
    {
        return new NetworkSocket(
            $this->getNetworkSocketHost(),
            $this->getNetworkSocketPort()
        );
    }

    /**
     * @param string $poolName
     *
     * @return array<int>
     */
    private function getPoolWorkerPIDs(string $poolName): array
    {
        $command = sprintf(
            'ps -o pid,args | grep %s | grep -v "grep"',
            escapeshellarg($poolName)
        );
        $list    = (string)shell_exec($command);

        return array_map(
            static function (string $item) {
                preg_match('#^(\d+)\s.+$#', trim($item), $matches);

                return (int)$matches[1];
            },
            explode("\n", trim($list))
        );
    }

    private function killPoolWorker(int $PID, int $signal): void
    {
        $command = sprintf('kill -%d %d', $signal, $PID);
        exec($command);
    }

    /**
     * @param int $signal
     *
     * @throws ConnectException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReadFailedException
     * @throws Throwable
     * @throws TimedoutException
     * @throws WriteFailedException
     * @dataProvider signalProvider
     */
    public function testFailureCallbackGetsCalledIfOneProcessGetsInterruptedOnUnixDomainSocket(int $signal): void
    {
        $client   = new Client();
        $request  = new PostRequest($this->getWorkerPath('worker.php'));
        $success  = [];
        $failures = [];

        $request->addResponseCallbacks(
            static function (ProvidesResponseData $response) use (&$success) {
                $success[] = (int)$response->getBody();
            }
        );

        $request->addFailureCallbacks(
            static function (Throwable $e) use (&$failures) {
                $failures[] = $e;
            }
        );

        for ($i = 0; $i < 3; $i++) {
            $request->setContent(new UrlEncodedFormData(['test-key' => $i]));

            $client->sendAsyncRequest($this->getUnixDomainSocketConnection(), $request);
        }

        $pids = $this->getPoolWorkerPIDs('pool uds');

        $this->killPoolWorker((int)$pids[0], $signal);

        $client->waitForResponses();

        self::assertCount(2, $success);
        self::assertCount(1, $failures);
        self::assertContainsOnlyInstancesOf(ReadFailedException::class, $failures);

        sleep(1);
    }

    private function getUnixDomainSocketConnection(): UnixDomainSocket
    {
        return new UnixDomainSocket($this->getUnixDomainSocket());
    }

    /**
     * @param int $signal
     *
     * @throws ConnectException
     * @throws ExpectationFailedException
     * @throws \InvalidArgumentException
     * @throws ReadFailedException
     * @throws Throwable
     * @throws TimedoutException
     * @throws WriteFailedException
     *
     * @dataProvider signalProvider
     */
    public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnNetworkSocket(int $signal): void
    {
        $client   = new Client();
        $request  = new PostRequest($this->getWorkerPath('sleepWorker.php'));
        $success  = [];
        $failures = [];

        $request->addResponseCallbacks(
            static function (ProvidesResponseData $response) use (&$success) {
                $success[] = (int)$response->getBody();
            }
        );

        $request->addFailureCallbacks(
            static function (Throwable $e) use (&$failures) {
                $failures[] = $e;
            }
        );

        for ($i = 0; $i < 3; $i++) {
            $request->setContent(new UrlEncodedFormData(['test-key' => $i, 'sleep' => 2]));

            $client->sendAsyncRequest($this->getNetworkSocketConnection(), $request);
        }

        $this->killPhpFpmChildProcesses('pool network', $signal);

        $client->waitForResponses();

        self::assertCount(0, $success);
        self::assertCount(3, $failures);
        self::assertContainsOnlyInstancesOf(ReadFailedException::class, $failures);

        sleep(1);
    }

    private function killPhpFpmChildProcesses(string $poolName, int $signal): void
    {
        usleep(100000);

        $PIDs = $this->getPoolWorkerPIDs($poolName);
        $this->killPoolWorkers($PIDs, $signal);

        usleep(100000);

        $PIDs = $this->getPoolWorkerPIDs($poolName);
        $this->killPoolWorkers($PIDs, $signal);
    }

    /**
     * @param array<int> $PIDs
     * @param int        $signal
     */
    private function killPoolWorkers(array $PIDs, int $signal): void
    {
        foreach ($PIDs as $PID) {
            $this->killPoolWorker($PID, $signal);
        }
    }

    /**
     * @param int $signal
     *
     * @throws ConnectException
     * @throws ExpectationFailedException
     * @throws \InvalidArgumentException
     * @throws ReadFailedException
     * @throws Throwable
     * @throws TimedoutException
     * @throws WriteFailedException
     *
     * @dataProvider signalProvider
     */
    public function testFailureCallbackGetsCalledIfAllProcessesGetInterruptedOnUnixDomainSocket(int $signal): void
    {
        $client   = new Client();
        $request  = new PostRequest($this->getWorkerPath('sleepWorker.php'));
        $success  = [];
        $failures = [];

        $request->addResponseCallbacks(
            static function (ProvidesResponseData $response) use (&$success) {
                $success[] = (int)$response->getBody();
            }
        );

        $request->addFailureCallbacks(
            static function (Throwable $e) use (&$failures) {
                $failures[] = $e;
            }
        );

        for ($i = 0; $i < 3; $i++) {
            $request->setContent(new UrlEncodedFormData(['test-key' => $i, 'sleep' => 1]));

            $client->sendAsyncRequest($this->getUnixDomainSocketConnection(), $request);
        }

        $this->killPhpFpmChildProcesses('pool uds', $signal);

        $client->waitForResponses();

        self::assertCount(0, $success);
        self::assertCount(3, $failures);
        self::assertContainsOnlyInstancesOf(ReadFailedException::class, $failures);

        sleep(1);
    }

    /**
     * @throws ConnectException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Throwable
     * @throws TimedoutException
     * @throws WriteFailedException
     */
    public function testBrokenSocketGetsRemovedIfWritingRequestFailed(): void
    {
        $client     = new Client();
        $request    = new PostRequest($this->getWorkerPath('pidWorker.php'));
        $connection = $this->getUnixDomainSocketConnection();

        $socketId1 = $client->sendAsyncRequest($connection, $request);
        $pid1      = (int)$client->readResponse($socketId1)->getBody();

        # This request should use the same socket and same PHP-FPM child process
        $socketId2 = $client->sendAsyncRequest($connection, $request);
        $pid2      = (int)$client->readResponse($socketId2)->getBody();

        self::assertSame($socketId1, $socketId2);
        self::assertSame($pid1, $pid2);

        $this->killPoolWorker($pid2, 9);

        try {
            # This should fail because we killed the socket
            $client->sendAsyncRequest($connection, $request);
        } catch (WriteFailedException $e) {
            # This request should use a new socket and a new PHP-FPM child process
            $socketId3 = $client->sendAsyncRequest($connection, $request);
            $pid3      = (int)$client->readResponse($socketId3)->getBody();

            self::assertNotSame($socketId2, $socketId3);
            self::assertNotSame($pid2, $pid3);
        }
    }
}
