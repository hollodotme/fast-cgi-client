import {php, type Scene} from '../scene';
import {TimelineBuilder} from '../timeline';

const SOCKET_ID = 23417;
const RUNTIME = 1;

/**
 * What FastCGI is: a web server forwards HTTP requests to PHP-FPM via FastCGI.
 * With this library your own PHP script talks FastCGI to PHP-FPM, without a web server.
 */
export const fastcgiBasics: Scene = {
  summary:
    'A web server translates an HTTP request of a browser into a FastCGI request and sends it to PHP-FPM, ' +
    'which runs index.php. With the FastCGI Client, your own PHP script sends the FastCGI request to PHP-FPM, ' +
    'without a web server and without HTTP.',
  filename: ({from}) => (from === 'webserver' ? 'nginx.conf' : 'run.php'),
  language: ({from}) => (from === 'webserver' ? 'nginx' : 'php'),
  outputTitle: ({from}) => (from === 'webserver' ? 'Browser' : '$ php run.php'),
  sliders: [],
  choices: [
    {
      id: 'from',
      label: 'Who sends the request to PHP-FPM?',
      options: [
        {value: 'webserver', label: 'a web server'},
        {value: 'client', label: 'your PHP script'},
      ],
      default: 'webserver',
    },
  ],

  code: ({from}) =>
    from === 'webserver'
      ? php(`
location ~ \\.php$ {                                                    //@setup
    fastcgi_pass  127.0.0.1:9000;                                      //@send
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;  //@send
    include       fastcgi_params;                                      //@send
}
`)
      : php(`
$client     = new Client();                                 //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);         //@setup
$request    = new GetRequest('/var/www/index.php');          //@setup

$response = $client->sendRequest($connection, $request);    //@send

echo $response->getBody();                                  //@read
`),

  build: ({from}) => (from === 'webserver' ? viaWebServer() : viaClient()),
};

function viaWebServer() {
  const timeline = new TimelineBuilder({
    title: 'Web server',
    short: 'Web server',
    subtitle: 'e.g. nginx or Apache',
    labels: {
      setup: 'gets an HTTP request',
      send: 'forwards it via FastCGI',
      blocked: 'waits for PHP-FPM',
      read: 'answers the browser',
    },
  });

  timeline
    .say('A browser requests /index.php from the web server via HTTP.')
    .print('→ GET /index.php HTTP/1.1\n\n')
    .show('setup')
    .do('setup', 1);

  timeline
    .say(
      'The web server does not run PHP itself. It turns the HTTP request into a FastCGI request — with ' +
        'SCRIPT_FILENAME, the other parameters and the body — and sends it to PHP-FPM.',
    )
    .show('send');
  const request = timeline.send(timeline.lane('Request'), SOCKET_ID, RUNTIME, {
    label: 'FastCGI request',
    script: 'index.php',
  });

  timeline
    .doUntil('blocked', request.arriveAt)
    .say('A PHP-FPM worker runs index.php.')
    .doUntil('blocked', request.finishAt)
    .say('PHP-FPM sends the output of the script back as FastCGI response: headers and body.')
    .doUntil('blocked', request.returnAt)
    .say('The web server turns the FastCGI response into an HTTP response for the browser.')
    .show()
    .read(request, '← HTTP/1.1 200 OK\n  Content-type: text/html; charset=UTF-8\n\n  Hello World\n')
    .say(
      'PHP-FPM only speaks FastCGI — here the web server is the FastCGI client. ' +
        'Switch to "your PHP script" to see this library in the same role.',
    );

  return timeline.build();
}

function viaClient() {
  const timeline = new TimelineBuilder();

  timeline
    .say(
      'Your PHP script — a CLI command, a cron job or a queue worker — creates a FastCGI request for index.php. ' +
        'There is no web server and no HTTP involved.',
    )
    .show('setup')
    .do('setup', 1);

  timeline.say('sendRequest() sends the FastCGI request directly to PHP-FPM.').show('send');
  const request = timeline.send(timeline.lane('Request'), SOCKET_ID, RUNTIME, {
    label: 'FastCGI request',
    script: 'index.php',
  });

  timeline
    .doUntil('blocked', request.arriveAt)
    .say('A PHP-FPM worker runs index.php, exactly as for a web server.')
    .doUntil('blocked', request.finishAt)
    .say('PHP-FPM sends the output of the script back as FastCGI response.')
    .doUntil('blocked', request.returnAt)
    .say('Your script gets the response as an object and prints its body.')
    .show('read')
    .read(request, 'Hello World\n')
    .say(
      'The same FastCGI conversation with PHP-FPM, but from your own PHP code: you decide which script runs, ' +
        'with which parameters, and what happens with its output.',
    );

  return timeline.build();
}
