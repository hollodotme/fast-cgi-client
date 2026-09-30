import {php, type Scene, type Settings} from '../scene';
import {seconds, TimelineBuilder, type Request} from '../timeline';

const SOCKET_IDS = [23417, 8091, 51602];
/** Default stream select timeout of the connections, see Defaults::STREAM_SELECT_TIMEOUT */
const SELECT_TIMEOUT = 0.2;
const CALLBACK = 0.2;

const runtimesOf = (settings: Settings) => [1, 2, 3].map((number) => Number(settings[`sleep${number}`]));

/**
 * Sends three requests with response and failure callbacks. waitForResponses() notifies the response callback of
 * each request as soon as its response is there, and the failure callback of requests that exceed the timeout.
 */
export const callbacks: Scene = {
  summary:
    'Your script registers a response and a failure callback on three requests and sends them. ' +
    'waitForResponses() calls the response callback of each request as soon as its response arrives, ' +
    'and the failure callback with a TimedoutException for requests that take longer than the timeout.',
  filename: 'callbacks.php',
  sliders: [
    ...[1, 2, 3].map((number) => ({
      id: `sleep${number}`,
      label: `Runtime of request #${number}`,
      min: 1,
      max: 4,
      step: 1,
      default: [3, 1, 2][number - 1],
      unit: 's',
    })),
    {id: 'timeout', label: 'Timeout of waitForResponses()', min: 1, max: 5, step: 0.5, default: 2.5, unit: 's'},
  ],
  choices: [],

  code: (settings) => {
    const sleeps = runtimesOf(settings)
      .map((sleep, index) => `${index + 1} => ${sleep}`)
      .join(', ');
    return php(`
$client     = new Client();                                       //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);               //@setup

foreach ([${sleeps}] as $key => $sleep)                        //@setup
{
    $request = new PostRequest(                                   //@setup
        '/path/to/target/script.php',                             //@setup
        new UrlEncodedFormData(['key' => $key, 'sleep' => $sleep]) //@setup
    );                                                            //@setup
    $request->addResponseCallbacks(                               //@setup
        static fn(ProvidesResponseData $response) => print $response->getBody() . "\\n" //@response
    );                                                            //@setup
    $request->addFailureCallbacks(                                //@setup
        static fn(Throwable $e) => print "Request #{$key} failed: {$e->getMessage()}\\n" //@failure
    );                                                            //@setup

    $client->sendAsyncRequest($connection, $request);             //@send
}

$client->waitForResponses(${Number(settings.timeout) * 1000});                              //@wait
`);
  },

  build: (settings) => {
    const runtimes = runtimesOf(settings);
    const timeout = Number(settings.timeout);
    const timeline = new TimelineBuilder();

    timeline.say(
      'For each request, your script registers a response callback and a failure callback, then sends it. ' +
        'The requests run in parallel.',
    );

    const requests = runtimes.map((runtime, index) => {
      timeline.show('setup').do('setup', index === 0 ? 0.6 : 0.3).show('send');
      return timeline.send(timeline.lane(`Request #${index + 1}`), SOCKET_IDS[index], runtime);
    });

    timeline
      .say(
        `waitForResponses() blocks your script until each request is handled — ` +
          `by its response callback, or by its failure callback after ${seconds(timeout)}.`,
      )
      .show('wait');

    const waitingSince = timeline.now;
    const pending = [...requests];
    let responses = 0;

    while (pending.length > 0) {
      const nextReturn = Math.min(...pending.map((request) => request.returnAt));
      timeline.doUntil('blocked', Math.min(timeline.now + SELECT_TIMEOUT, Math.max(timeline.now, nextReturn)));

      for (const request of pending.filter((candidate) => candidate.returnAt <= timeline.now)) {
        pending.splice(pending.indexOf(request), 1);
        responses++;
        timeline
          .say(`Request #${request.number} is done: waitForResponses() reads its response and calls the response callback.`)
          .show('wait')
          .read(request)
          .show('response')
          .do('callback', CALLBACK)
          .print(`${request.number}\n`)
          .close(request.lane, 'socket closed')
          .show('wait');
      }

      if (timeline.now - waitingSince >= timeout) {
        for (const request of [...pending]) {
          pending.splice(pending.indexOf(request), 1);
          timedOut(timeline, request, timeout);
        }
      }
    }

    const failures = requests.length - responses;
    timeline.say(
      failures === 0
        ? 'All three responses arrived within the timeout, each one was handled by its response callback.'
        : `All requests are handled: ${responses} by the response callback, ${failures} by the failure callback. ` +
            'Raise the timeout to get all responses.',
    );

    return timeline.build();
  },
};

function timedOut(timeline: TimelineBuilder, request: Request, timeout: number): void {
  timeline
    .say(
      `There is no response of request #${request.number} after ${seconds(timeout)}. Its failure callback gets a ` +
        'TimedoutException, and the client closes the socket — the response of the script is lost.',
    )
    .show('failure')
    .close(request.lane, 'closed after timeout')
    .drop(request)
    .do('callback', CALLBACK)
    .print(`Request #${request.number} failed: Read timed out\n`)
    .show('wait');
}
