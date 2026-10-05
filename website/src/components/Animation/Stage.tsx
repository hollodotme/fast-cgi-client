import type {ReactNode} from 'react';
import {activityAt, packetAt, socketAt, workerAt, type Activity, type Lane, type Packet, type Timeline} from './timeline';
import styles from './styles.module.css';

type Layout = {
  width: number;
  client: {x: number; width: number};
  fpm: {x: number; width: number};
  lane: {from: number; to: number};
  inset: number;
  /** Approximate width of a character of a packet label */
  charWidth: number;
  compact: boolean;
};

const wide: Layout = {
  width: 720,
  client: {x: 8, width: 196},
  fpm: {x: 508, width: 204},
  lane: {from: 210, to: 502},
  inset: 14,
  charWidth: 6.6,
  compact: false,
};

/** For narrow screens: shorter labels, so the stage can be drawn at a larger scale */
const narrow: Layout = {
  width: 440,
  client: {x: 4, width: 124},
  fpm: {x: 300, width: 136},
  lane: {from: 132, to: 296},
  inset: 8,
  charWidth: 6.2,
  compact: true,
};

const compactLabels: Record<Activity, string> = {
  setup: 'preparing',
  send: 'sending',
  blocked: 'blocked',
  work: 'other work',
  poll: 'polling',
  read: 'reading',
  callback: 'callback',
  failed: 'exception',
  done: 'finished',
};

const ROW = 72;
const TOP = 44;
const PACKET_HEIGHT = 22;

type Props = {
  timeline: Timeline;
  time: number;
  summary: string;
  compact: boolean;
};

/** Draws the client script, the sockets with the travelling packets, and the PHP-FPM workers at a point in time */
export default function Stage({timeline, time, summary, compact}: Props): ReactNode {
  const layout = compact ? narrow : wide;
  const {client, fpm, inset} = layout;
  const rows = Math.max(1, timeline.lanes.length);
  const height = TOP + rows * ROW + 12;
  const activity = activityAt(timeline, time);
  const rowY = (index: number) => TOP + index * ROW + ROW / 2;

  return (
    <svg className={styles.stage} viewBox={`0 0 ${layout.width} ${height}`} role="img" aria-label={summary}>
      <g>
        <rect className={styles.box} x={client.x} y={8} width={client.width} height={height - 16} rx={10} />
        <text className={styles.boxTitle} x={client.x + inset} y={32}>
          {compact ? timeline.client.title.replace(/^Your PHP script$/, 'Your script') : timeline.client.title}
        </text>
        {!compact && (
          <text className={styles.boxSubtitle} x={client.x + inset} y={50}>
            {timeline.client.subtitle}
          </text>
        )}
        <g transform={`translate(${client.x + inset}, ${Math.max(66, height / 2 - 4)})`}>
          <rect className={styles.status} data-activity={activity} width={client.width - 2 * inset} height={30} rx={15} />
          <circle className={styles.statusDot} data-activity={activity} cx={15} cy={15} r={5} />
          <text className={styles.statusText} x={27} y={20}>
            {compact ? compactLabels[activity] : timeline.client.labels[activity]}
          </text>
        </g>
      </g>

      <g>
        <rect className={styles.box} x={fpm.x} y={8} width={fpm.width} height={height - 16} rx={10} />
        <text className={styles.boxTitle} x={fpm.x + inset} y={32}>
          PHP-FPM
        </text>
      </g>

      {timeline.lanes.map((lane, index) => (
        <LaneView
          key={index}
          number={index + 1}
          layout={layout}
          lane={lane}
          packets={timeline.packets.filter((packet) => packet.lane === index)}
          time={time}
          y={rowY(index)}
        />
      ))}
    </svg>
  );
}

type LaneProps = {
  number: number;
  layout: Layout;
  lane: Lane;
  packets: Packet[];
  time: number;
  y: number;
};

const socketClasses = {
  none: styles.socketIdle,
  open: styles.socket,
  closed: styles.socketClosed,
};

function LaneView({number, layout, lane: model, packets, time, y}: LaneProps): ReactNode {
  const {fpm, lane, compact} = layout;
  const socket = socketAt(model, time);
  const worker = workerAt(model, time);
  const inset = compact ? 6 : 12;
  const box = {x: fpm.x + inset, y: y - 29, width: fpm.width - 2 * inset, height: 58};
  const socketLabel = compact ? socket.label.replace(/^socket /, '') : socket.label;
  // Runs labelled like sleep(2) run script.php of the examples
  const scriptLabel = !compact && worker.label.startsWith('sleep(') ? `script.php · ${worker.label}` : worker.label;

  return (
    <g>
      <line className={socketClasses[socket.state]} x1={lane.from} y1={y} x2={lane.to} y2={y} />
      <text className={styles.socketLabel} x={(lane.from + lane.to) / 2} y={y - 16} textAnchor="middle">
        {socketLabel}
      </text>

      <rect
        className={worker.running ? styles.workerBusy : styles.worker}
        x={box.x}
        y={box.y}
        width={box.width}
        height={box.height}
        rx={8}
      />
      <text className={styles.workerTitle} x={box.x + 8} y={box.y + 17}>
        worker #{number}
      </text>
      <text className={styles.workerText} x={box.x + box.width - 8} y={box.y + 17} textAnchor="end">
        {worker.state}
      </text>
      <rect className={styles.progressTrack} x={box.x + 8} y={box.y + 25} width={box.width - 16} height={8} rx={4} />
      <rect
        className={styles.progress}
        x={box.x + 8}
        y={box.y + 25}
        width={(box.width - 16) * worker.progress}
        height={8}
        rx={4}
        opacity={worker.running ? 1 : 0.4}
      />
      <text className={styles.workerText} x={box.x + 8} y={box.y + 49}>
        {scriptLabel}
      </text>

      {packets.map((packet, index) => (
        <PacketView key={index} layout={layout} packet={packet} time={time} y={y} />
      ))}
    </g>
  );
}

const packetClasses = {
  request: styles.packetRequest,
  record: styles.packetRequest,
  response: styles.packetResponse,
  chunk: styles.packetChunk,
  error: styles.packetRequest,
};

type PacketProps = {
  layout: Layout;
  packet: Packet;
  time: number;
  y: number;
};

function PacketView({layout, packet, time, y}: PacketProps): ReactNode {
  const at = packetAt(packet, time);
  if (at === null) {
    return null;
  }

  const label = at.state === 'failed' ? '✕ write failed' : layout.compact ? packet.label.replace(/^request /, 'req. ') : packet.label;
  const width = Math.max(layout.compact ? 56 : 64, label.length * layout.charWidth + 18);
  const travel = layout.lane.to - layout.lane.from - width;
  const x = layout.lane.from + travel * at.position - (at.state === 'reading' ? width * (1 - at.opacity) : 0);

  return (
    <g transform={`translate(${x}, ${y - PACKET_HEIGHT / 2})`} opacity={at.opacity}>
      {at.state === 'ready' && (
        <rect
          className={styles.readyPulse}
          x={-4}
          y={-4}
          width={width + 8}
          height={PACKET_HEIGHT + 8}
          rx={(PACKET_HEIGHT + 8) / 2}
        />
      )}
      <rect
        className={at.state === 'failed' ? styles.packetFailed : packetClasses[packet.kind]}
        width={width}
        height={PACKET_HEIGHT}
        rx={PACKET_HEIGHT / 2}
      />
      <text className={styles.packetText} x={width / 2} y={PACKET_HEIGHT / 2 + 4} textAnchor="middle">
        {label}
      </text>
      {at.state === 'ready' && (
        <text className={styles.readyText} x={width / 2} y={PACKET_HEIGHT + 16} textAnchor="middle">
          ready to read
        </text>
      )}
    </g>
  );
}
