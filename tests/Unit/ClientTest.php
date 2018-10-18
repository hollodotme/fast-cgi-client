<?php

declare(strict_types=1);
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

namespace hollodotme\FastCGI\Tests\Unit;

use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest.
 */
final class ClientTest extends TestCase
{
    /**
     * @throws \Exception
     * @throws \Throwable
     * @throws \hollodotme\FastCGI\Exceptions\ConnectException
     * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
     * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
     */
    public function testConnectAttemptToNotExistingSocketThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ConnectException::class);

        $connection = new UnixDomainSocket('/tmp/not/existing.sock', 2000, 2000);
        $client = new Client($connection);

        $client->sendRequest(new PostRequest('/path/to/script.php', ''));
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     * @throws \hollodotme\FastCGI\Exceptions\ConnectException
     * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
     * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
     */
    public function testConnectAttemptToInvalidSocketThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ConnectException::class);

        $testSocket = \realpath(__DIR__.'/Fixtures/test.sock');

        $connection = new UnixDomainSocket(''.$testSocket);
        $client = new Client($connection);

        $client->sendRequest(new PostRequest('/path/to/script.php', ''));
    }

    public function testWaitingForUnknownRequestThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ReadFailedException::class, 'Socket not found for request ID: 12345');

        $connection = new NetworkSocket('127.0.0.1', 9000);
        $client = new Client($connection);

        $client->waitForResponse(12345);
    }

    /**
     * @throws \Throwable
     * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
     */
    public function testWaitingForResponsesWithoutRequestsThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ReadFailedException::class, 'No pending requests found.');

        $connection = new NetworkSocket('127.0.0.1', 9000);
        $client = new Client($connection);

        $client->waitForResponses();
    }

    public function testHandlingUnknownRequestThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ReadFailedException::class, 'Socket not found for request ID: 12345');

        $connection = new NetworkSocket('127.0.0.1', 9000);
        $client = new Client($connection);

        $client->handleResponse(12345);
    }

    public function testHandlingUnknownRequestsThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ReadFailedException::class, 'Socket not found for request ID: 12345');

        $connection = new NetworkSocket('127.0.0.1', 9000);
        $client = new Client($connection);

        $client->handleResponses(null, 12345, 12346);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     * @throws \hollodotme\FastCGI\Exceptions\ConnectException
     * @throws \hollodotme\FastCGI\Exceptions\TimedoutException
     * @throws \hollodotme\FastCGI\Exceptions\WriteFailedException
     */
    public function testConnectAttemptToRestrictedUnixDomainSocketThrowsException(): void
    {
        $this->expectException(\hollodotme\FastCGI\Exceptions\ConnectException::class);
        $this->expectExceptionMessageRegExp('#.*unable to connect to.*#i');

        $connection = new UnixDomainSocket('/var/run/php7.1-ruds.sock');
        $client = new Client($connection);

        $client->sendRequest(new PostRequest('/path/to/script.php', ''));
    }

    /**
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \hollodotme\FastCGI\Exceptions\ReadFailedException
     */
    public function testHandlingReadyResponsesJustReturnsIfClientGotNoRequests(): void
    {
        $connection = new UnixDomainSocket('/var/run/php7.1-ruds.sock');
        $client = new Client($connection);

        $this->assertFalse($client->hasUnhandledResponses());

        $client->handleReadyResponses();
    }
}
