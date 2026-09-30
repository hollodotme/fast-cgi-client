import {php, type Scene} from '../scene';
import {TimelineBuilder} from '../timeline';

const SOCKET_ID = 23417;
/** Maximum length of the content of a FastCGI record */
const MAX_CONTENT = 65535;
/** Time a record needs to the other side, slow enough to read its label */
const RECORD_TRAVEL = 0.9;
/** Time between two records */
const GAP = 0.75;
const RUNTIME = 1;
/** Length of the PARAMS of a PostRequest with PlainText content, without the digits of CONTENT_LENGTH */
const PARAMS_WITHOUT_LENGTH = 314;
const OUTPUT = 'Content-type: text/html; charset=UTF-8\r\n\r\nOK'.length;

const bytes = (value: number) => value.toLocaleString('en-US');

type FastCgiRecord = {
  type: string;
  length: number;
  label: string;
  note: string;
  caption?: string;
};

function recordsOf(size: number): FastCgiRecord[] {
  const body = size * 1024;
  const stdin: FastCgiRecord[] = [];
  for (let offset = 0; offset < body; offset += MAX_CONTENT) {
    const length = Math.min(MAX_CONTENT, body - offset);
    stdin.push({type: 'STDIN', length, label: `STDIN ${Math.round(length / 1024)} KB`, note: 'part of the body'});
  }

  if (stdin.length > 0) {
    stdin[0].caption =
      stdin.length === 1
        ? `The body of ${size} KB fits into one STDIN record.`
        : `The body of ${size} KB is longer than 65,535 bytes, so the client splits it into ` +
          `${stdin.length} STDIN records.`;
  }

  return [
    {
      type: 'BEGIN_REQUEST',
      length: 8,
      label: 'BEGIN_REQUEST',
      note: 'role RESPONDER',
      caption: 'BEGIN_REQUEST starts the request. It tells PHP-FPM the role (RESPONDER) and the request ID.',
    },
    {
      type: 'PARAMS',
      length: PARAMS_WITHOUT_LENGTH + String(body).length,
      label: 'PARAMS',
      note: 'SCRIPT_FILENAME, CONTENT_LENGTH, …',
      caption:
        'PARAMS carry the parameters of the request, like SCRIPT_FILENAME, REQUEST_METHOD and CONTENT_LENGTH. ' +
        'Many or long parameters are split into more PARAMS records.',
    },
    {
      type: 'PARAMS',
      length: 0,
      label: 'PARAMS (empty)',
      note: 'end of the parameters',
      caption: 'An empty PARAMS record ends the parameters.',
    },
    ...stdin,
    {
      type: 'STDIN',
      length: 0,
      label: 'STDIN (empty)',
      note: 'end of the body',
      caption:
        body === 0
          ? 'There is no body, so the client only sends an empty STDIN record, which ends the request.'
          : 'An empty STDIN record ends the body — the request is complete.',
    },
  ];
}

/** How a request and its response are sent as FastCGI records */
export const records: Scene = {
  summary:
    'The client sends a request as a sequence of FastCGI records: BEGIN_REQUEST, PARAMS, an empty PARAMS record, ' +
    'the body in STDIN records of at most 65,535 bytes each, and an empty STDIN record. PHP-FPM runs the script ' +
    'and answers with STDOUT records and an END_REQUEST record.',
  filename: 'records.php',
  outputTitle: () => 'FastCGI records on the socket',
  sliders: [{id: 'size', label: 'Size of the request body', min: 0, max: 320, step: 32, default: 160, unit: 'KB'}],
  choices: [],

  code: ({size}) =>
    php(`
$client     = new Client();                                   //@setup
$connection = new NetworkSocket('127.0.0.1', 9000);           //@setup
$request    = new PostRequest(                                //@setup
    '/path/to/target/script.php',                             //@setup
    new PlainText(str_repeat('x', ${size} * 1024))              //@body
);                                                            //@setup

$response = $client->sendRequest($connection, $request);      //@send

echo $response->getBody();                                    //@print
`),

  build: ({size}) => {
    const timeline = new TimelineBuilder({labels: {send: 'writing records', read: 'reading records'}});
    const lane = timeline.lane('Socket');
    const log = (arrow: string, record: {type: string; length: number; note: string}, at: number) =>
      timeline.print(
        `${arrow} ${record.type.padEnd(13)} ${bytes(record.length).padStart(6)} bytes  ${record.note}\n`,
        at,
      );

    timeline
      .say(
        'On the socket, a request is a sequence of records. Each record has an 8-byte header with its type, ' +
          'the request ID and the length of its content — at most 65,535 bytes.',
      )
      .show('setup', 'body')
      .do('setup', 1.6)
      .show('send')
      .open(lane, SOCKET_ID);

    for (const record of recordsOf(Number(size))) {
      if (record.caption !== undefined) {
        timeline.say(record.caption);
      }
      timeline.record(lane, record.label, 'out', timeline.now, RECORD_TRAVEL);
      log('→', record, timeline.now);
      timeline.do('send', GAP);
    }

    const start = timeline.now - GAP + RECORD_TRAVEL;
    const finish = start + RUNTIME;
    timeline
      .run(lane, start, finish, 'runs the script')
      .doUntil('blocked', start)
      .say('PHP-FPM has the complete request and runs the script. The client waits for the response records.')
      .doUntil('blocked', finish);

    const responses = [
      {
        type: 'STDOUT',
        length: OUTPUT,
        note: 'headers and output',
        caption: 'The output of the script — headers and body — comes back in STDOUT records, errors in STDERR records.',
      },
      {
        type: 'END_REQUEST',
        length: 8,
        note: 'exit status 0',
        caption: 'END_REQUEST ends the request. The client builds the response object from the collected output.',
      },
    ];

    responses.forEach((record, index) => {
      const from = finish + index * GAP;
      timeline.doUntil('blocked', from).say(record.caption);
      timeline.record(lane, record.type, 'in', from, RECORD_TRAVEL);
      log('←', record, from + RECORD_TRAVEL);
    });

    timeline
      .doUntil('blocked', finish + GAP + RECORD_TRAVEL)
      .show('print')
      .do('read', 0.4)
      .say('sendRequest() returns the response — your code never deals with records.');

    return timeline.build(1.5);
  },
};
