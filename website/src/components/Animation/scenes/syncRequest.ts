import {php, type Scene} from '../scene';
import {seconds, TimelineBuilder} from '../timeline';

const SOCKET_ID = 23417;

/** Sends one request with sendRequest(), which blocks until the response is there */
export const syncRequest: Scene = {
  summary:
    'Your script sends a request to PHP-FPM with sendRequest() and is blocked until the response arrives, ' +
    'then prints the response body.',
  filename: 'sync.php',
  sliders: [{id: 'sleep', label: 'Runtime of script.php', min: 1, max: 4, step: 1, default: 2, unit: 's'}],
  choices: [],

  code: ({sleep}) =>
    php(`
$client     = new Client();                                 //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);         //@setup
$request    = new PostRequest(                              //@setup
    '/path/to/target/script.php',                           //@setup
    new UrlEncodedFormData(['key' => 'value', 'sleep' => ${sleep}]) //@setup
);                                                          //@setup

$response = $client->sendRequest($connection, $request);    //@send

echo $response->getBody();                                  //@print
`),

  build: ({sleep}) => {
    const runtime = Number(sleep);
    const timeline = new TimelineBuilder();

    timeline
      .say('Create the client, a connection to PHP-FPM and a request for script.php.')
      .show('setup')
      .do('setup', 0.8);

    timeline.say('sendRequest() opens a socket to PHP-FPM and writes the request to it.').show('send');
    const request = timeline.send(SOCKET_ID, runtime);

    timeline
      .doUntil('blocked', request.arriveAt)
      .say(
        `A PHP-FPM worker runs script.php for ${seconds(runtime)}. ` +
          'Your script is blocked in the meantime: sendRequest() only returns with the response.',
      )
      .doUntil('blocked', request.finishAt)
      .say('The script is done, PHP-FPM sends the response back.')
      .doUntil('blocked', request.returnAt)
      .say('sendRequest() returns the response, your script prints its body.')
      .show('print')
      .read(request, 'value\n')
      .say(
        `Done after ${seconds(timeline.now)}: the time of your script is the runtime of script.php ` +
          'plus the way to PHP-FPM and back.',
      );

    return timeline.build();
  },
};
