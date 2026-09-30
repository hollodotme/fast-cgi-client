import type {ReactNode} from 'react';
import {activityAt, activityLabels, phaseOf, type Activity, type Request, type Timeline} from './timeline';
import styles from './styles.module.css';

type Layout = {
  width: number;
  client: {x: number; width: number};
  fpm: {x: number; width: number};
  packet: {width: number; height: number};
  lane: {from: number; to: number};
  clientTitle: string;
  clientSubtitle: string;
  activityLabels: Record<Activity, string>;
  requestLabel: (request: Request) => string;
  scriptLabel: (request: Request) => string;
  socketLabel: (request: Request) => string;
};

const wide: Layout = {
  width: 720,
  client: {x: 8, width: 196},
  fpm: {x: 508, width: 204},
  packet: {width: 84, height: 22},
  lane: {from: 210, to: 502},
  clientTitle: 'Your PHP script',
  clientSubtitle: 'using the FastCGI Client',
  activityLabels,
  requestLabel: (request) => `request #${request.number}`,
  scriptLabel: (request) => `script.php · sleep(${request.runtime})`,
  socketLabel: (request) => `socket ${request.socketId}`,
};

/** For narrow screens: shorter labels, so the stage can be drawn at a larger scale */
const compact: Layout = {
  width: 440,
  client: {x: 4, width: 124},
  fpm: {x: 300, width: 136},
  packet: {width: 64, height: 22},
  lane: {from: 132, to: 296},
  clientTitle: 'Your script',
  clientSubtitle: '',
  activityLabels: {
    setup: 'preparing',
    send: 'sending',
    blocked: 'blocked',
    work: 'other work',
    poll: 'polling',
    read: 'reading',
    done: 'finished',
  },
  requestLabel: (request) => `req. #${request.number}`,
  scriptLabel: (request) => `sleep(${request.runtime})`,
  socketLabel: (request) => `${request.socketId}`,
};

const ROW = 72;
const TOP = 44;

type Props = {
  timeline: Timeline;
  time: number;
  summary: string;
  narrow: boolean;
};

/** Draws the client script, the sockets with the travelling packets, and the PHP-FPM workers at a point in time */
export default function Stage({timeline, time, summary, narrow}: Props): ReactNode {
  const layout = narrow ? compact : wide;
  const {client, fpm} = layout;
  const rows = Math.max(1, timeline.requests.length);
  const height = TOP + rows * ROW + 12;
  const activity = activityAt(timeline, time);
  const rowY = (index: number) => TOP + index * ROW + ROW / 2;
  const inset = narrow ? 8 : 14;

  return (
    <svg
      className={narrow ? `${styles.stage} ${styles.compact}` : styles.stage}
      viewBox={`0 0 ${layout.width} ${height}`}
      role="img"
      aria-label={summary}>
      <g>
        <rect className={styles.box} x={client.x} y={8} width={client.width} height={height - 16} rx={10} />
        <text className={styles.boxTitle} x={client.x + inset} y={32}>
          {layout.clientTitle}
        </text>
        <text className={styles.boxSubtitle} x={client.x + inset} y={50}>
          {layout.clientSubtitle}
        </text>
        <g transform={`translate(${client.x + inset}, ${Math.max(66, height / 2 - 4)})`}>
          <rect className={styles.status} data-activity={activity} width={client.width - 2 * inset} height={30} rx={15} />
          <circle className={styles.statusDot} data-activity={activity} cx={15} cy={15} r={5} />
          <text className={styles.statusText} x={27} y={20}>
            {layout.activityLabels[activity]}
          </text>
        </g>
      </g>

      <g>
        <rect className={styles.box} x={fpm.x} y={8} width={fpm.width} height={height - 16} rx={10} />
        <text className={styles.boxTitle} x={fpm.x + inset} y={32}>
          PHP-FPM
        </text>
      </g>

      {timeline.requests.map((request, index) => (
        <Lane key={request.number} layout={layout} request={request} time={time} y={rowY(index)} />
      ))}
    </svg>
  );
}

type LaneProps = {
  layout: Layout;
  request: Request;
  time: number;
  y: number;
};

function Lane({layout, request, time, y}: LaneProps): ReactNode {
  const {phase, progress} = phaseOf(request, time);
  const {fpm, lane, packet: size} = layout;
  const connected = phase !== 'pending' && phase !== 'done';
  const running = phase === 'running';
  const finished = !running && phase !== 'pending' && phase !== 'sending';
  const inset = layout === compact ? 6 : 12;
  const worker = {x: fpm.x + inset, y: y - 29, width: fpm.width - 2 * inset, height: 58};
  const travel = lane.to - lane.from - size.width;

  let packet: ReactNode = null;
  if (phase === 'sending') {
    packet = <Packet size={size} kind="request" label={layout.requestLabel(request)} x={lane.from + travel * progress} y={y} />;
  } else if (phase === 'returning') {
    packet = <Packet size={size} kind="response" label="response" x={lane.from + travel * (1 - progress)} y={y} />;
  } else if (phase === 'ready') {
    packet = <Packet size={size} kind="response" label="response" x={lane.from} y={y} ready />;
  } else if (phase === 'reading') {
    packet = (
      <Packet
        size={size}
        kind="response"
        label="response"
        x={lane.from - size.width * progress}
        y={y}
        opacity={1 - progress}
      />
    );
  }

  return (
    <g>
      <line className={connected ? styles.socket : styles.socketIdle} x1={lane.from} y1={y} x2={lane.to} y2={y} />
      <text className={styles.socketLabel} x={(lane.from + lane.to) / 2} y={y - 16} textAnchor="middle">
        {connected ? layout.socketLabel(request) : phase === 'done' ? 'response read' : ''}
      </text>

      <rect
        className={running ? styles.workerBusy : styles.worker}
        x={worker.x}
        y={worker.y}
        width={worker.width}
        height={worker.height}
        rx={8}
      />
      <text className={styles.workerTitle} x={worker.x + 8} y={worker.y + 17}>
        worker #{request.number}
      </text>
      <text className={styles.workerText} x={worker.x + worker.width - 8} y={worker.y + 17} textAnchor="end">
        {running ? 'running' : finished ? 'finished' : 'idle'}
      </text>
      <rect
        className={styles.progressTrack}
        x={worker.x + 8}
        y={worker.y + 25}
        width={worker.width - 16}
        height={8}
        rx={4}
      />
      <rect
        className={styles.progress}
        x={worker.x + 8}
        y={worker.y + 25}
        width={(worker.width - 16) * (running ? progress : finished ? 1 : 0)}
        height={8}
        rx={4}
        opacity={running ? 1 : 0.4}
      />
      <text className={styles.workerText} x={worker.x + 8} y={worker.y + 49}>
        {layout.scriptLabel(request)}
      </text>

      {packet}
    </g>
  );
}

type PacketProps = {
  size: {width: number; height: number};
  kind: 'request' | 'response';
  label: string;
  x: number;
  y: number;
  ready?: boolean;
  opacity?: number;
};

function Packet({size, kind, label, x, y, ready = false, opacity = 1}: PacketProps): ReactNode {
  return (
    <g transform={`translate(${x}, ${y - size.height / 2})`} opacity={opacity}>
      {ready && (
        <rect
          className={styles.readyPulse}
          x={-4}
          y={-4}
          width={size.width + 8}
          height={size.height + 8}
          rx={(size.height + 8) / 2}
        />
      )}
      <rect
        className={kind === 'request' ? styles.packetRequest : styles.packetResponse}
        width={size.width}
        height={size.height}
        rx={size.height / 2}
      />
      <text className={styles.packetText} x={size.width / 2} y={size.height / 2 + 4} textAnchor="middle">
        {label}
      </text>
      {ready && (
        <text className={styles.readyText} x={size.width / 2} y={size.height + 16} textAnchor="middle">
          ready to read
        </text>
      )}
    </g>
  );
}
