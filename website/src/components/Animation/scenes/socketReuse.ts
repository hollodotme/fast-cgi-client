import {php, type Scene} from '../scene';
import {TimelineBuilder, TRAVEL} from '../timeline';

const RUNTIME = 0.5;
const WORK = 0.6;

/**
 * The client keeps sockets open and reuses them. A socket that PHP-FPM closed in the meantime is replaced before
 * sending. If writing still fails, sendRequest() throws, while tryRequest() sends the request again on a new socket.
 */
export const socketReuse: Scene = {
  summary:
    'Your script sends four requests one after another. The second request reuses the socket of the first one. ' +
    'Then PHP-FPM restarts the worker and closes the socket, so the client opens a new socket for the third request. ' +
    'Writing the fourth request fails: sendRequest() throws a WriteFailedException, ' +
    'tryRequest() sends the request again on a new socket.',
  filename: 'reuse.php',
  sliders: [],
  choices: [
    {
      id: 'method',
      label: 'Send with',
      options: [
        {value: 'tryRequest', label: 'tryRequest()'},
        {value: 'sendRequest', label: 'sendRequest()'},
      ],
      default: 'tryRequest',
    },
  ],

  code: ({method}) =>
    php(`
$client     = new Client();                                   //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);           //@setup

foreach ([1, 2, 3, 4] as $key)                                //@setup
{
    $request  = new PostRequest(                              //@setup
        '/path/to/target/script.php',                         //@setup
        new UrlEncodedFormData(['key' => $key])               //@setup
    );                                                        //@setup
    $response = $client->${method}($connection, $request);    //@send

    echo $response->getBody() . "\\n";                         //@print

    doSomethingElse();                                        //@work
}
`),

  build: ({method}) => {
    const retry = method === 'tryRequest';
    const timeline = new TimelineBuilder();
    const first = timeline.lane('Socket A');
    const second = timeline.lane('Socket B');
    const third = timeline.lane('Socket C');

    const roundTrip = (lane: number, socketId: number, key: number) => {
      const request = timeline.send(lane, socketId, RUNTIME);
      timeline.doUntil('blocked', request.returnAt).show('print').read(request, `${key}\n`).show('work').do('work', WORK);
    };

    timeline
      .say(`There is no socket yet, so ${method}() opens a new one for the first request.`)
      .show('setup')
      .do('setup', 0.6)
      .show('send');
    roundTrip(first, 23417, 1);

    timeline
      .say('After reading the response the socket stays open. The second request to the same connection reuses it.')
      .show('setup')
      .do('setup', 0.2)
      .show('send');
    roundTrip(first, 23417, 2);

    const restartAt = timeline.now + 0.2;
    timeline
      .note(first, 'restarting', restartAt, restartAt + 0.8)
      .note(first, 'new process', restartAt + 0.8, Infinity)
      .close(first, 'closed by PHP-FPM', restartAt + 0.3);

    timeline
      .say('Meanwhile PHP-FPM restarts the worker process, e.g. after pm.max_requests, and closes the idle socket.')
      .do('work', 1.2)
      .say('Before sending the third request, the client notices that the idle socket was closed and opens a new one.')
      .show('setup')
      .do('setup', 0.2)
      .show('send');
    roundTrip(second, 8091, 3);

    const failAt = timeline.now + 0.2 + TRAVEL / 2;
    timeline
      .note(second, 'terminated', failAt - 0.05, Infinity)
      .say(
        'The idle socket looks fine, but writing the fourth request fails, e.g. because its worker is terminated ' +
          'while a large request is written.',
      )
      .show('setup')
      .do('setup', 0.2)
      .show('send')
      .failWrite(second, 8091, 'request #4');

    if (!retry) {
      timeline
        .say('sendRequest() throws a WriteFailedException and your script stops. Switch to tryRequest() to retry.')
        .do('failed', 1)
        .print('PHP Fatal error:  Uncaught hollodotme\\FastCGI\\Exceptions\\WriteFailedException: ')
        .print('Failed to write request to socket [broken pipe]\n');
      return timeline.build();
    }

    timeline.say(
      'tryRequest() catches the WriteFailedException and sends the request again on a new socket — ' +
        'up to 5 tries by default.',
    );
    const retried = timeline.send(third, 51602, RUNTIME, {label: 'request #4'});
    timeline
      .doUntil('blocked', retried.returnAt)
      .show('print')
      .read(retried, '4\n')
      .say('The fourth request succeeded on the second try. Only writing is retried, never reading a response.');

    return timeline.build();
  },
};
