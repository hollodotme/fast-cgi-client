import {php, type Scene} from '../scene';
import {TimelineBuilder, TRAVEL} from '../timeline';

const SOCKET_ID = 23417;
const CALLBACK = 0.15;

/** What the target script flushes and when, in seconds after it started */
const flushes = [
  {after: 0, label: 'STDOUT One', output: 'Content-type: text/html; charset=UTF-8\n\nOne\n', text: 'One'},
  {after: 1, label: 'STDOUT Two', output: 'Two\n', text: 'Two'},
  {after: 2, label: 'STDOUT Three', output: 'Three\n', text: 'Three'},
  {after: 3, label: 'STDERR', error: 'PHP message: Oh oh!\n', text: 'an error message'},
  {after: 3.15, label: 'STDOUT End', output: 'End', text: 'End'},
];
const RUNTIME = 3.3;

/**
 * A script flushes its output while it runs. Pass-through callbacks receive each part as soon as it arrives,
 * readResponse() returns all of it when the request has ended.
 */
export const passThrough: Scene = {
  summary:
    'The target script prints One, Two and Three with a second in between, then an error message and End. ' +
    'PHP-FPM sends each flushed part right away. A pass-through callback prints each part as soon as it arrives, ' +
    'while readResponse() only returns the complete output when the request has ended.',
  filename: 'pass-through.php',
  sliders: [],
  choices: [
    {
      id: 'mode',
      label: 'Receive the output',
      options: [
        {value: 'passThrough', label: 'while it arrives: pass-through callback'},
        {value: 'buffered', label: 'at the end: readResponse()'},
      ],
      default: 'passThrough',
    },
  ],

  code: ({mode}) =>
    mode === 'passThrough'
      ? php(`
$client     = new Client();                                           //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);                   //@setup
$request    = new GetRequest('/path/to/target/script.php');           //@setup

$request->addPassThroughCallbacks(                                    //@setup
    static function (string $outputBuffer, string $errorBuffer): void //@callback
    {
        if ($outputBuffer !== '') { echo 'Output: ' . $outputBuffer; } //@output
        if ($errorBuffer !== '') { echo 'Error: ' . $errorBuffer; }    //@error
    }
);

$client->sendAsyncRequest($connection, $request);                     //@send
$client->waitForResponses();                                          //@wait
`)
      : php(`
$client     = new Client();                                           //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);                   //@setup
$request    = new GetRequest('/path/to/target/script.php');           //@setup

$socketId = $client->sendAsyncRequest($connection, $request);         //@send
$response = $client->readResponse($socketId);                         //@wait

echo $response->getBody() . "\\n";                                     //@print
echo 'Error: ' . $response->getError();                               //@print
`),

  build: ({mode}) => {
    const passThrough = mode === 'passThrough';
    const timeline = new TimelineBuilder();

    timeline
      .say(
        passThrough
          ? 'Create a request for a script that flushes its output while it runs, and register a pass-through callback.'
          : 'Create a request for a script that flushes its output while it runs.',
      )
      .show('setup')
      .do('setup', 1)
      .say('Send the request. PHP-FPM starts the script.')
      .show('send');

    const request = timeline.send(timeline.lane('Request'), SOCKET_ID, RUNTIME, {
      script: 'flushes output',
      chunks: flushes.map((flush) => ({after: flush.after, label: flush.label})),
      responseLabel: 'END_REQUEST',
    });

    timeline.show('wait');

    for (const flush of flushes) {
      const arrival = request.arriveAt + flush.after + TRAVEL;
      timeline.doUntil('blocked', arrival);

      if (!passThrough) {
        timeline.say(
          flush.after === 0
            ? 'The script flushes "One" and PHP-FPM sends it right away. The client receives it, but only collects ' +
                'it: readResponse() returns when the request has ended.'
            : `The client collects "${flush.text}" as well — your script still waits.`,
        );
        continue;
      }

      timeline
        .say(
          flush.error !== undefined
            ? 'The script logs an error with error_log(), PHP-FPM sends it as STDERR with the prefix "PHP message: ". ' +
              'The callback prints it as error.'
            : flush.after === 0
              ? 'The script flushes "One" (after the headers) and PHP-FPM sends it right away. ' +
                  'The pass-through callback prints it immediately.'
              : `"${flush.text}" arrives, the callback prints it immediately.`,
        )
        .show(flush.error !== undefined ? 'error' : 'output')
        .do('callback', CALLBACK)
        .print(flush.error !== undefined ? `Error: ${flush.error}` : `Output: ${flush.output}`)
        .show('wait');
    }

    timeline
      .doUntil('blocked', request.returnAt)
      .say(
        passThrough
          ? 'The request has ended, waitForResponses() returns. The response object still contains the complete output.'
          : 'The request has ended: readResponse() returns the response with the complete output, your script prints it.',
      );

    if (passThrough) {
      // waitForResponses() removes the socket after the request was handled, readResponse() keeps it for reuse
      timeline.read(request).close(request.lane, 'socket closed');
    } else {
      timeline.show('print').read(request, 'One\nTwo\nThree\nEnd\nError: PHP message: Oh oh!');
    }

    return timeline.build();
  },
};
