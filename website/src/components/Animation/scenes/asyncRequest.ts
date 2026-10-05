import {php, type Scene} from '../scene';
import {READ, seconds, TimelineBuilder, TRAVEL} from '../timeline';

const SOCKET_ID = 23417;

/** Sends one request with sendAsyncRequest(), does other work and reads the response later with readResponse() */
export const asyncRequest: Scene = {
  summary:
    'Your script sends a request with sendAsyncRequest(), which returns immediately with a socket ID. ' +
    'While PHP-FPM runs the script, your script does other work, then reads the response with readResponse().',
  filename: 'async.php',
  sliders: [
    {id: 'sleep', label: 'Runtime of script.php', min: 1, max: 4, step: 1, default: 2, unit: 's'},
    {id: 'work', label: 'Other work of your script', min: 0.5, max: 4, step: 0.5, default: 1, unit: 's'},
  ],
  choices: [],

  code: ({sleep, work}) =>
    php(`
$client     = new Client();                                     //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);             //@setup
$request    = new PostRequest(                                  //@setup
    '/path/to/target/script.php',                               //@setup
    new UrlEncodedFormData(['key' => 'value', 'sleep' => ${sleep}]) //@setup
);                                                              //@setup

$socketId = $client->sendAsyncRequest($connection, $request);   //@send
echo "Request sent, got ID: {$socketId}\\n";                     //@send

doSomethingElse(); # takes ${work} s                                //@work

$response = $client->readResponse($socketId);                   //@read

echo $response->getBody();                                      //@read
`),

  build: ({sleep, work}) => {
    const runtime = Number(sleep);
    const timeline = new TimelineBuilder();

    timeline
      .say('Create the client, a connection to PHP-FPM and a request for script.php.')
      .show('setup')
      .do('setup', 0.8);

    timeline
      .say('sendAsyncRequest() writes the request to a socket and returns the socket ID right away — it does not wait.')
      .show('send');
    const request = timeline.send(timeline.lane('Request #1'), SOCKET_ID, runtime);
    timeline.print(`Request sent, got ID: ${SOCKET_ID}\n`);

    timeline
      .say(`While PHP-FPM runs script.php for ${seconds(runtime)}, your script does other work for ${seconds(Number(work))}.`)
      .show('work')
      .do('work', Number(work))
      .show('read');

    if (request.returnAt > timeline.now) {
      timeline
        .say('readResponse() needs the response, which is not there yet. Your script is blocked until it arrives.')
        .doUntil('blocked', request.returnAt)
        .say('The response arrived, readResponse() returns it and your script prints its body.');
    } else {
      timeline.say('The response is already waiting at the socket, so readResponse() returns it immediately.');
    }

    timeline.read(request, 'value\n');

    const sequential = request.sendAt + 2 * TRAVEL + runtime + READ + Number(work);
    timeline.say(
      `Done after ${seconds(timeline.now)}. Waiting for the response first and working afterwards ` +
        `would have taken about ${seconds(sequential)}.`,
    );

    return timeline.build();
  },
};
