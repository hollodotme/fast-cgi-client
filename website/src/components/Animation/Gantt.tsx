import type {MouseEvent, ReactNode} from 'react';
import {ganttRows, type GanttKind, type Timeline} from './timeline';
import styles from './styles.module.css';

const ROW = 22;
const GAP = 6;
const AXIS = 22;

type Props = {
  timeline: Timeline;
  time: number;
  onSeek: (time: number) => void;
  compact: boolean;
};

const shortLabel = (label: string) =>
  label.replace(/^Your script$/, 'Script').replace(/^Web server$/, 'Server').replace(/^Request /, 'Req. ');

/** A chart of what the client script and each socket do over time, with a playhead at the current time */
export default function Gantt({timeline, time, onSeek, compact}: Props): ReactNode {
  const width = compact ? 440 : 720;
  const plot = {from: compact ? 60 : 112, to: width - 12};
  const rows = ganttRows(timeline);
  const height = rows.length * (ROW + GAP) + AXIS;
  const x = (at: number) => plot.from + (Math.min(at, timeline.duration) / timeline.duration) * (plot.to - plot.from);
  const tick = timeline.duration > (compact ? 6 : 12) ? 2 : 1;
  const ticks = Array.from({length: Math.floor(timeline.duration / tick) + 1}, (_, index) => index * tick);

  const seek = (event: MouseEvent<SVGSVGElement>) => {
    const box = event.currentTarget.getBoundingClientRect();
    const svgX = ((event.clientX - box.left) / box.width) * width;
    const at = ((svgX - plot.from) / (plot.to - plot.from)) * timeline.duration;
    onSeek(Math.min(timeline.duration, Math.max(0, at)));
  };

  return (
    <svg className={styles.gantt} viewBox={`0 0 ${width} ${height}`} onClick={seek} aria-hidden="true">
      {rows.map((row, index) => {
        const y = index * (ROW + GAP);
        return (
          <g key={index}>
            <text className={styles.ganttLabel} x={0} y={y + ROW / 2 + 4}>
              {compact ? shortLabel(row.label) : row.label}
            </text>
            <rect className={styles.ganttTrack} x={plot.from} y={y} width={plot.to - plot.from} height={ROW} rx={4} />
            {row.spans.map((span, spanIndex) => (
              <g key={spanIndex} data-kind={span.value}>
                <rect className={styles.ganttPlanned} x={x(span.from)} y={y} width={x(span.to) - x(span.from)} height={ROW} />
                {time > span.from && (
                  <rect
                    className={styles.ganttDone}
                    x={x(span.from)}
                    y={y}
                    width={x(Math.min(span.to, time)) - x(span.from)}
                    height={ROW}
                  />
                )}
              </g>
            ))}
          </g>
        );
      })}

      {ticks.map((second) => (
        <text
          key={second}
          className={styles.ganttTick}
          x={x(second)}
          y={height - 6}
          textAnchor={second === 0 ? 'start' : 'middle'}>
          {second} s
        </text>
      ))}

      <line className={styles.playhead} x1={x(time)} y1={0} x2={x(time)} y2={height - AXIS + 4} />
    </svg>
  );
}

const legend: {kinds: GanttKind[]; label: string}[] = [
  {kinds: ['setup', 'send', 'work', 'read'], label: '%s works'},
  {kinds: ['blocked'], label: '%s waits (blocked)'},
  {kinds: ['poll'], label: '%s polls'},
  {kinds: ['callback'], label: 'a callback runs'},
  {kinds: ['transfer'], label: 'on the way over the socket'},
  {kinds: ['running'], label: 'the script runs in PHP-FPM'},
  {kinds: ['ready'], label: 'response ready, not yet read'},
  {kinds: ['error', 'failed'], label: 'failure'},
];

/** The entries of the legend for the kinds of spans in the chart */
export function legendOf(timeline: Timeline): {kind: GanttKind; label: string}[] {
  const kinds = new Set(ganttRows(timeline).flatMap((row) => row.spans.map((span) => span.value)));
  const subject = timeline.client.short.toLowerCase();
  return legend
    .filter((entry) => entry.kinds.some((kind) => kinds.has(kind)))
    .map((entry) => ({kind: entry.kinds[0], label: entry.label.replace('%s', subject)}));
}
