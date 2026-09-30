import {php, type Scene, type Settings} from '../scene';
import {READ, SEND, seconds, TimelineBuilder, TRAVEL, type Request} from '../timeline';

const SOCKET_IDS = [23417, 8091, 51602];
/** Default stream select timeout of the connections, see Defaults::STREAM_SELECT_TIMEOUT */
const SELECT_TIMEOUT = 0.2;

const runtimesOf = (settings: Settings) => [1, 2, 3].map((number) => Number(settings[`sleep${number}`]));

const requestLines = (settings: Settings) =>
  runtimesOf(settings)
    .map(
      (sleep, index) =>
        `$request${index + 1} = new PostRequest($script, new UrlEncodedFormData(['key' => '${index + 1}', 'sleep' => ${sleep}])); //@setup`,
    )
    .join('\n');

const sendLines = `
$socketIds = [];                                                  //@send
$socketIds[] = $client->sendAsyncRequest($connection, $request1); //@send
$socketIds[] = $client->sendAsyncRequest($connection, $request2); //@send
$socketIds[] = $client->sendAsyncRequest($connection, $request3); //@send

echo 'Sent requests with IDs: ' . implode(', ', $socketIds) . "\\n"; //@send`;

/**
 * Sends three requests in parallel and reads their responses, either in the order they were sent with
 * readResponses(), or as soon as they are ready with readReadyResponses().
 */
export const multipleRequests: Scene = {
  summary:
    'Your script sends three requests without waiting, PHP-FPM runs them in parallel in three workers. ' +
    'readResponses() reads the responses in the order the requests were sent, ' +
    'readReadyResponses() reads each response as soon as it is ready.',
  filename: 'multiple.php',
  sliders: [1, 2, 3].map((number) => ({
    id: `sleep${number}`,
    label: `Runtime of request #${number}`,
    min: 1,
    max: 4,
    step: 1,
    default: [3, 1, 2][number - 1],
    unit: 's',
  })),
  choices: [
    {
      id: 'mode',
      label: 'Read the responses',
      options: [
        {value: 'ordered', label: 'in order: readResponses()'},
        {value: 'reactive', label: 'when ready: readReadyResponses()'},
      ],
      default: 'ordered',
    },
  ],

  code: (settings) => {
    const head = `
$client     = new Client();                           //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);   //@setup
$script     = '/path/to/target/script.php';           //@setup

${requestLines(settings)}
${sendLines}
`;

    if (settings.mode === 'ordered') {
      return php(`${head}
foreach ($client->readResponses(3000, ...$socketIds) as $response) //@wait
{
    echo $response->getBody() . "\\n";                  //@read
}
`);
    }

    return php(`${head}
while ($client->hasUnhandledResponses())                 //@poll
{
    foreach ($client->readReadyResponses(3000) as $response) //@poll
    {
        echo $response->getBody() . "\\n";              //@read
    }

    echo '.';                                           //@poll
}
`);
  },

  build: (settings) => {
    const runtimes = runtimesOf(settings);
    const timeline = new TimelineBuilder();

    timeline
      .say('Create the client, a connection to PHP-FPM and three requests for script.php.')
      .show('setup')
      .do('setup', 0.8)
      .say(
        'sendAsyncRequest() does not wait, so all three requests are sent right after each other. ' +
          'Each one gets its own socket and PHP-FPM worker — the scripts run in parallel.',
      )
      .show('send');

    const requests = runtimes.map((runtime, index) =>
      timeline.send(timeline.lane(`Request #${index + 1}`), SOCKET_IDS[index], runtime),
    );
    timeline.print(`Sent requests with IDs: ${SOCKET_IDS.join(', ')}\n`);

    if (settings.mode === 'ordered') {
      readInOrder(timeline, requests);
    } else {
      readWhenReady(timeline, requests);
    }

    const sequential = runtimes.reduce((sum, runtime) => sum + SEND + 2 * TRAVEL + runtime + READ, 0.8);
    timeline.say(
      `All responses read after ${seconds(timeline.now)}. ` +
        `One after another, the three requests would have taken about ${seconds(sequential)}.`,
    );

    return timeline.build();
  },
};

function readInOrder(timeline: TimelineBuilder, requests: Request[]): void {
  timeline
    .say('readResponses() reads the responses in the order the requests were sent, starting with request #1.')
    .show('wait');

  for (const request of requests) {
    if (request.returnAt > timeline.now) {
      const readyLater = requests.filter((other) => other.number > request.number && other.returnAt <= request.returnAt);
      timeline.say(
        readyLater.length > 0
          ? `Request ${readyLater.map((other) => `#${other.number}`).join(' and ')} ` +
              `${readyLater.length > 1 ? 'are' : 'is'} already done, but readResponses() waits for request ` +
              `#${request.number} first.`
          : `readResponses() waits for the response of request #${request.number}.`,
      );
      timeline.doUntil('blocked', request.returnAt);
    }

    timeline.say(`Your script reads and prints the response of request #${request.number}.`).show('read');
    timeline.read(request, `${request.number}\n`).close(request.lane, 'socket closed').show('wait');
  }
}

function readWhenReady(timeline: TimelineBuilder, requests: Request[]): void {
  timeline
    .say(
      'The loop asks the client for ready responses. Each round waits up to 200 ms for a response ' +
        '(stream_select), then prints a dot.',
    )
    .show('poll');

  const unread = [...requests];

  while (unread.length > 0) {
    const nextReturn = Math.min(...unread.map((request) => request.returnAt));
    timeline.doUntil('poll', Math.min(timeline.now + SELECT_TIMEOUT, Math.max(timeline.now, nextReturn)));

    const ready = unread.filter((request) => request.returnAt <= timeline.now);

    for (const request of ready) {
      unread.splice(unread.indexOf(request), 1);
      const waiting = unread.filter((other) => other.number < request.number);
      timeline
        .say(
          waiting.length > 0
            ? `Request #${request.number} is done before request ${waiting.map((other) => `#${other.number}`).join(' and ')}, ` +
                'so your script reads and prints its response right away.'
            : `Request #${request.number} is done, your script reads and prints its response.`,
        )
        .show('read')
        .read(request, `${request.number}\n`)
        .close(request.lane, 'socket closed')
        .show('poll');
    }

    if (ready.length > 0 && unread.length > 0) {
      timeline.say('The loop continues polling for the remaining responses.');
    }

    timeline.print('.');
  }
}
