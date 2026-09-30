/**
 * The model of an animation: a timeline of what the client script, the sockets and the PHP-FPM workers do.
 *
 * A scene builds a timeline once for its current settings. Everything shown at a point in time is derived from the
 * timeline, so playing, pausing, stepping and scrubbing are all just a change of the time.
 * All times are in (simulated) seconds.
 */

/** What the client script is doing */
export type Activity = 'setup' | 'send' | 'blocked' | 'work' | 'poll' | 'read' | 'done';

export const activityLabels: Record<Activity, string> = {
  setup: 'preparing the request',
  send: 'sending a request',
  blocked: 'waiting — blocked',
  work: 'doing other work',
  poll: 'polling for responses',
  read: 'reading a response',
  done: 'finished',
};

/** Time the request needs to travel to PHP-FPM, or the response back */
export const TRAVEL = 0.3;
/** Time the client needs to write a request to the socket */
export const SEND = 0.15;
/** Time the client needs to read a response and print it */
export const READ = 0.25;

export type Span<T> = {
  from: number;
  to: number;
  value: T;
};

export type Request = {
  /** Number of the request in the scene, starting with 1 */
  number: number;
  /** The socket ID returned by the client */
  socketId: number;
  /** Seconds the script runs in the PHP-FPM worker */
  runtime: number;
  /** Time the client starts to write the request */
  sendAt: number;
  /** Time the request reaches PHP-FPM, the script starts */
  arriveAt: number;
  /** Time the script ends, PHP-FPM sends the response */
  finishAt: number;
  /** Time the response is available at the client */
  returnAt: number;
  /** Time the client starts to read the response, set when the scene reads it */
  readAt: number;
};

export type RequestPhase = 'pending' | 'sending' | 'running' | 'returning' | 'ready' | 'reading' | 'done';

export type Timeline = {
  duration: number;
  requests: Request[];
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

/**
 * Builds a timeline step by step, in the order the client script executes. The builder keeps the current time of
 * the client script, every method that makes the client do something advances it.
 */
export class TimelineBuilder {
  private time = 0;
  private readonly requests: Request[] = [];
  private readonly activities: Span<Activity>[] = [];
  private readonly code: Span<string[]>[] = [];
  private readonly captions: Span<string>[] = [];
  private readonly output: {at: number; text: string}[] = [];

  get now(): number {
    return this.time;
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

  print(text: string): this {
    this.output.push({at: this.time, text});
    return this;
  }

  /** The client sends a request, which runs for the given seconds in a PHP-FPM worker */
  send(socketId: number, runtime: number): Request {
    const sendAt = this.time;
    const arriveAt = sendAt + TRAVEL;
    const finishAt = arriveAt + runtime;
    const request: Request = {
      number: this.requests.length + 1,
      socketId,
      runtime,
      sendAt,
      arriveAt,
      finishAt,
      returnAt: finishAt + TRAVEL,
      readAt: Infinity,
    };
    this.requests.push(request);
    this.do('send', SEND);
    return request;
  }

  /** The client reads the response of a request, it blocks until the response is available */
  read(request: Request, body: string): this {
    this.doUntil('blocked', request.returnAt);
    request.readAt = this.time;
    this.do('read', READ);
    return this.print(body);
  }

  build(pause = 1.2): Timeline {
    this.do('done', pause);
    const duration = this.time;
    return {
      duration,
      requests: this.requests,
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

export function spanAt<T>(spans: Span<T>[], time: number): Span<T> | undefined {
  return spans.find((span) => span.from <= time && time < span.to) ?? (time > 0 ? spans.at(-1) : spans[0]);
}

export function activityAt(timeline: Timeline, time: number): Activity {
  return spanAt(timeline.activities, time)?.value ?? 'setup';
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

/** The phase of a request at a point in time, with the progress within that phase from 0 to 1 */
export function phaseOf(request: Request, time: number): {phase: RequestPhase; progress: number} {
  const progress = (from: number, to: number) => (to > from ? Math.min(1, Math.max(0, (time - from) / (to - from))) : 1);

  if (time < request.sendAt) {
    return {phase: 'pending', progress: 0};
  }
  if (time < request.arriveAt) {
    return {phase: 'sending', progress: progress(request.sendAt, request.arriveAt)};
  }
  if (time < request.finishAt) {
    return {phase: 'running', progress: progress(request.arriveAt, request.finishAt)};
  }
  if (time < request.returnAt) {
    return {phase: 'returning', progress: progress(request.finishAt, request.returnAt)};
  }
  if (time < request.readAt) {
    return {phase: 'ready', progress: 0};
  }
  if (time < request.readAt + READ) {
    return {phase: 'reading', progress: progress(request.readAt, request.readAt + READ)};
  }
  return {phase: 'done', progress: 1};
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
