/**
 * The model of an animation: a timeline of what the client script, the sockets and the PHP-FPM workers do.
 *
 * A scene builds a timeline once for its current settings. Everything shown at a point in time is derived from the
 * timeline, so playing, pausing, stepping and scrubbing are all just a change of the time.
 * All times are in (simulated) seconds.
 */

/** What the client script is doing */
export type Activity = 'setup' | 'send' | 'blocked' | 'work' | 'poll' | 'read' | 'callback' | 'failed' | 'done';

export const activityLabels: Record<Activity, string> = {
  setup: 'preparing the request',
  send: 'sending a request',
  blocked: 'waiting — blocked',
  work: 'doing other work',
  poll: 'polling for responses',
  read: 'reading a response',
  callback: 'running a callback',
  failed: 'exception thrown',
  done: 'finished',
};

/** Time the request needs to travel to PHP-FPM, or the response back */
export const TRAVEL = 0.3;
/** Time the client needs to write a request to the socket */
export const SEND = 0.15;
/** Time the client needs to read a response and print it */
export const READ = 0.25;
/** Time a failed write is shown on the socket */
const FAILURE = 0.8;

export type Span<T> = {
  from: number;
  to: number;
  value: T;
};

export type PacketKind = 'request' | 'response' | 'record' | 'chunk' | 'error';

/** Something travelling over a socket: a request, a response, a part of them or a FastCGI record */
export type Packet = {
  lane: number;
  label: string;
  kind: PacketKind;
  /** 'out' travels from the client to PHP-FPM, 'in' from PHP-FPM to the client */
  direction: 'out' | 'in';
  from: number;
  to: number;
  /** When the client takes an incoming packet, it waits at the client until then */
  readAt: number;
  /** When writing an outgoing packet fails half way */
  failAt?: number;
};

export type SocketState = {
  id: number;
  state: 'open' | 'closed';
  note: string;
};

/** A socket to PHP-FPM and the worker handling it */
export type Lane = {
  /** Label of the lane in the timeline chart */
  title: string;
  sockets: Span<SocketState>[];
  /** Runs of the script in the worker, with a label like sleep(2) */
  runs: Span<string>[];
  /** Notes about the worker, shown instead of its state, e.g. restarted */
  notes: Span<string>[];
};

export type GanttKind = Activity | 'transfer' | 'running' | 'ready' | 'error';

export type Timeline = {
  duration: number;
  /** The FastCGI client: its title, a short title for the chart, and labels of its activities */
  client: {title: string; short: string; subtitle: string; labels: Record<Activity, string>};
  lanes: Lane[];
  packets: Packet[];
  activities: Span<Activity>[];
  /** Tags of the highlighted code lines */
  code: Span<string[]>[];
  captions: Span<string>[];
  output: {at: number; text: string}[];
};

/** A line of example code, tagged to be highlighted while the client executes it */
export type CodeLine = {
  text: string;
  tag?: string;
};

export type Request = {
  /** Number of the request in the scene, starting with 1 */
  number: number;
  lane: number;
  socketId: number;
  runtime: number;
  /** Time the client starts to write the request */
  sendAt: number;
  /** Time the request reaches PHP-FPM, the script starts */
  arriveAt: number;
  /** Time the script ends, PHP-FPM sends the response */
  finishAt: number;
  /** Time the response is available at the client */
  returnAt: number;
  response: Packet;
};

export type SendOptions = {
  label?: string;
  /** Label of the script run in the worker, default: sleep(<runtime>) */
  script?: string;
  /** Output the script flushes while it runs, the client receives it as separate packets */
  chunks?: {after: number; label: string}[];
  responseLabel?: string;
};

type ClientOptions = {
  title?: string;
  short?: string;
  subtitle?: string;
  labels?: Partial<Record<Activity, string>>;
};

/**
 * Builds a timeline step by step, in the order the client script executes. The builder keeps the current time of
 * the client script, every method that makes the client do something advances it.
 */
export class TimelineBuilder {
  private time = 0;
  private readonly lanes: Lane[] = [];
  private readonly packets: Packet[] = [];
  private readonly requests: Request[] = [];
  private readonly activities: Span<Activity>[] = [];
  private readonly code: Span<string[]>[] = [];
  private readonly captions: Span<string>[] = [];
  private readonly output: {at: number; text: string}[] = [];
  private readonly client: Timeline['client'];

  constructor({
    title = 'Your PHP script',
    short = 'Your script',
    subtitle = 'using the FastCGI Client',
    labels = {},
  }: ClientOptions = {}) {
    this.client = {title, short, subtitle, labels: {...activityLabels, ...labels}};
  }

  get now(): number {
    return this.time;
  }

  /** Adds a socket lane with its PHP-FPM worker */
  lane(title: string): number {
    this.lanes.push({title, sockets: [], runs: [], notes: []});
    return this.lanes.length - 1;
  }

  /** Starts a new caption, which is also a step of the animation */
  say(caption: string): this {
    this.captions.push({from: this.time, to: Infinity, value: caption});
    return this;
  }

  /** Highlights the code lines with the given tags from now on */
  show(...tags: string[]): this {
    this.code.push({from: this.time, to: Infinity, value: tags});
    return this;
  }

  /** The client does something for the given duration */
  do(activity: Activity, duration: number): this {
    if (duration > 0) {
      this.activities.push({from: this.time, to: this.time + duration, value: activity});
      this.time += duration;
    }
    return this;
  }

  /** The client does something until the given time */
  doUntil(activity: Activity, time: number): this {
    return this.do(activity, time - this.time);
  }

  print(text: string, at = this.time): this {
    this.output.push({at, text});
    return this;
  }

  /** Opens a socket on the lane, if it has no open socket with that ID yet */
  open(lane: number, socketId: number, at = this.time): this {
    const current = this.lanes[lane].sockets.at(-1)?.value;
    if (current?.state !== 'open' || current.id !== socketId) {
      this.lanes[lane].sockets.push({from: at, to: Infinity, value: {id: socketId, state: 'open', note: ''}});
    }
    return this;
  }

  /** Closes the socket of the lane, the note tells why */
  close(lane: number, note: string, at = this.time): this {
    const id = this.lanes[lane].sockets.at(-1)?.value.id ?? 0;
    this.lanes[lane].sockets.push({from: at, to: Infinity, value: {id, state: 'closed', note}});
    return this;
  }

  /** Shows a note instead of the state of the worker */
  note(lane: number, note: string, from: number, to: number): this {
    this.lanes[lane].notes.push({from, to, value: note});
    return this;
  }

  /** The client sends a request on a lane, which runs for the given seconds in the PHP-FPM worker */
  send(lane: number, socketId: number, runtime: number, options: SendOptions = {}): Request {
    const number = this.requests.length + 1;
    const sendAt = this.time;
    const arriveAt = sendAt + TRAVEL;
    const finishAt = arriveAt + runtime;

    this.open(lane, socketId);
    this.packet(lane, options.label ?? `request #${number}`, 'request', 'out', sendAt);
    this.lanes[lane].runs.push({from: arriveAt, to: finishAt, value: options.script ?? `sleep(${runtime})`});

    for (const chunk of options.chunks ?? []) {
      this.packet(lane, chunk.label, 'chunk', 'in', arriveAt + chunk.after);
    }

    const response = this.packet(lane, options.responseLabel ?? 'response', 'response', 'in', finishAt, Infinity);
    const request: Request = {
      number,
      lane,
      socketId,
      runtime,
      sendAt,
      arriveAt,
      finishAt,
      returnAt: finishAt + TRAVEL,
      response,
    };
    this.requests.push(request);
    this.do('send', SEND);
    return request;
  }

  /** The client reads the response of a request, it blocks until the response is available */
  read(request: Request, body?: string): this {
    this.doUntil('blocked', request.returnAt);
    request.response.readAt = this.time;
    this.do('read', READ);
    return body === undefined ? this : this.print(body);
  }

  /** The response of the request is never sent, e.g. because the client closed the socket before */
  drop(request: Request): this {
    const index = this.packets.indexOf(request.response);
    if (index >= 0) {
      this.packets.splice(index, 1);
    }
    return this;
  }

  /** Sends a packet over a socket. An incoming packet waits at the client until readAt */
  packet(
    lane: number,
    label: string,
    kind: PacketKind,
    direction: 'out' | 'in',
    from: number,
    readAt = from + TRAVEL,
  ): Packet {
    const packet: Packet = {lane, label, kind, direction, from, to: from + TRAVEL, readAt};
    this.packets.push(packet);
    return packet;
  }

  /** Sends a FastCGI record over a socket, it travels slower than a request, so its label can be read */
  record(lane: number, label: string, direction: 'out' | 'in', from: number, travel: number): Packet {
    const packet: Packet = {lane, label, kind: direction === 'out' ? 'record' : 'response', direction, from, to: from + travel, readAt: from + travel};
    this.packets.push(packet);
    return packet;
  }

  /** The worker of the lane runs the script */
  run(lane: number, from: number, to: number, label: string): this {
    this.lanes[lane].runs.push({from, to, value: label});
    return this;
  }

  /** The client tries to write a request, but the socket breaks half way */
  failWrite(lane: number, socketId: number, label: string): this {
    this.open(lane, socketId);
    const packet = this.packet(lane, label, 'error', 'out', this.time);
    packet.failAt = this.time + TRAVEL / 2;
    this.close(lane, 'broken', packet.failAt);
    return this.do('send', TRAVEL / 2);
  }

  build(pause = 1.2): Timeline {
    this.do('done', pause);
    const duration = this.time;
    return {
      duration,
      client: this.client,
      // The last state of a socket lasts beyond the end, so it is still shown at the end
      lanes: this.lanes.map((lane) => ({...lane, sockets: closeSpans(lane.sockets, Infinity)})),
      packets: this.packets,
      activities: this.activities,
      code: closeSpans(this.code, duration),
      captions: closeSpans(this.captions, duration),
      output: this.output,
    };
  }
}

/** Lets each span last until the next one starts */
function closeSpans<T>(spans: Span<T>[], duration: number): Span<T>[] {
  return spans.map((span, index) => ({...span, to: spans[index + 1]?.from ?? duration}));
}

/** The span at a point in time, the first one before it starts and the last one after it ended */
function spanOrEdge<T>(spans: Span<T>[], time: number): Span<T> | undefined {
  return spans.find((span) => span.from <= time && time < span.to) ?? (time > 0 ? spans.at(-1) : spans[0]);
}

export function activityAt(timeline: Timeline, time: number): Activity {
  return spanOrEdge(timeline.activities, time)?.value ?? 'setup';
}

export function captionAt(timeline: Timeline, time: number): string {
  return spanOrEdge(timeline.captions, time)?.value ?? '';
}

export function codeAt(timeline: Timeline, time: number): string[] {
  return spanOrEdge(timeline.code, time)?.value ?? [];
}

export function outputAt(timeline: Timeline, time: number): string {
  return timeline.output
    .filter((entry) => entry.at <= time)
    .map((entry) => entry.text)
    .join('');
}

/** The start times of the captions, the points to step to */
export function stepsOf(timeline: Timeline): number[] {
  return timeline.captions.map((caption) => caption.from);
}

const progressOf = (time: number, from: number, to: number) =>
  to > from ? Math.min(1, Math.max(0, (time - from) / (to - from))) : 1;

export type PacketPosition = {
  /** From 0 at the client to 1 at PHP-FPM */
  position: number;
  state: 'moving' | 'ready' | 'reading' | 'failed';
  opacity: number;
};

/** Where a packet is at a point in time, or null, if it is not on the socket */
export function packetAt(packet: Packet, time: number): PacketPosition | null {
  if (time < packet.from) {
    return null;
  }
  if (packet.failAt !== undefined) {
    if (time < packet.failAt) {
      return {position: 0.5 * progressOf(time, packet.from, packet.failAt), state: 'moving', opacity: 1};
    }
    return time < packet.failAt + FAILURE ? {position: 0.5, state: 'failed', opacity: 1} : null;
  }
  if (time < packet.to) {
    const progress = progressOf(time, packet.from, packet.to);
    return {position: packet.direction === 'out' ? progress : 1 - progress, state: 'moving', opacity: 1};
  }
  if (packet.direction === 'out') {
    return null;
  }
  if (time < packet.readAt) {
    return {position: 0, state: 'ready', opacity: 1};
  }
  const reading = packet.kind === 'response' ? READ : 0.15;
  return time < packet.readAt + reading
    ? {position: 0, state: 'reading', opacity: 1 - progressOf(time, packet.readAt, packet.readAt + reading)}
    : null;
}

export type SocketView = {
  state: 'none' | 'open' | 'closed';
  label: string;
};

export function socketAt(lane: Lane, time: number): SocketView {
  const socket = lane.sockets.find((span) => span.from <= time && time < span.to)?.value;
  if (socket === undefined) {
    return {state: 'none', label: ''};
  }
  return socket.state === 'open'
    ? {state: 'open', label: `socket ${socket.id}`}
    : {state: 'closed', label: socket.note};
}

export type WorkerView = {
  state: string;
  label: string;
  progress: number;
  running: boolean;
};

/** The state of the worker of a lane at a point in time */
export function workerAt(lane: Lane, time: number): WorkerView {
  const run = lane.runs.find((span) => span.from <= time && time < span.to);
  const note = lane.notes.find((span) => span.from <= time && time < span.to)?.value;
  if (run !== undefined) {
    return {state: note ?? 'running', label: run.value, progress: progressOf(time, run.from, run.to), running: true};
  }
  const last = lane.runs.filter((span) => span.to <= time).at(-1);
  const next = lane.runs.find((span) => span.from > time);
  return {
    state: note ?? (last !== undefined ? 'finished' : 'idle'),
    label: (last ?? next)?.value ?? '',
    progress: last !== undefined && note === undefined ? 1 : 0,
    running: false,
  };
}

/** The rows of the timeline chart: the client script and each lane */
export function ganttRows(timeline: Timeline): {label: string; spans: Span<GanttKind>[]}[] {
  return [
    {label: timeline.client.short, spans: timeline.activities},
    ...timeline.lanes.map((lane, index) => {
      const spans: Span<GanttKind>[] = lane.runs.map((run) => ({from: run.from, to: run.to, value: 'running'}));
      for (const packet of timeline.packets.filter((candidate) => candidate.lane === index)) {
        if (packet.failAt !== undefined) {
          spans.push({from: packet.from, to: packet.failAt, value: 'transfer'});
          spans.push({from: packet.failAt, to: packet.failAt + FAILURE, value: 'error'});
          continue;
        }
        spans.push({from: packet.from, to: packet.to, value: 'transfer'});
        if (packet.direction === 'in' && packet.kind === 'response' && Number.isFinite(packet.readAt)) {
          spans.push({from: packet.to, to: packet.readAt, value: 'ready'});
          spans.push({from: packet.readAt, to: packet.readAt + READ, value: 'read'});
        }
      }
      return {label: lane.title, spans: spans.filter((span) => span.to > span.from + 0.001)};
    }),
  ];
}

/** Converts the tags to highlight into a Docusaurus code block metastring, e.g. {1-2,5} */
export function highlightMetastring(lines: CodeLine[], tags: string[]): string {
  const numbers = lines.flatMap((line, index) => (line.tag !== undefined && tags.includes(line.tag) ? [index + 1] : []));
  return numbers.length > 0 ? `{${numbers.join(',')}}` : '';
}

/** Formats seconds for captions, e.g. 1.5 s */
export function seconds(value: number): string {
  return `${Number(value.toFixed(2))} s`;
}
