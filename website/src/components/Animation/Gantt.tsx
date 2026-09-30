import type {MouseEvent, ReactNode} from 'react';
import {READ, type Span, type Timeline} from './timeline';
import styles from './styles.module.css';

const ROW = 22;
const GAP = 6;
const AXIS = 22;

type Props = {
  timeline: Timeline;
  time: number;
  onSeek: (time: number) => void;
  narrow: boolean;
};

type Row = {
  label: string;
  spans: Span<string>[];
};

function rowsOf(timeline: Timeline, narrow: boolean): Row[] {
  return [
    {label: narrow ? 'Script' : 'Your script', spans: timeline.activities},
    ...timeline.requests.map((request) => ({
      label: `${narrow ? 'Req.' : 'Request'} #${request.number}`,
      spans: [
        {from: request.sendAt, to: request.arriveAt, value: 'transfer'},
        {from: request.arriveAt, to: request.finishAt, value: 'running'},
        {from: request.finishAt, to: request.returnAt, value: 'transfer'},
        {from: request.returnAt, to: request.readAt, value: 'ready'},
        {from: request.readAt, to: request.readAt + READ, value: 'read'},
      ].filter((span) => Number.isFinite(span.to) && span.to > span.from),
    })),
  ];
}

/** A chart of what the client script and each request do over time, with a playhead at the current time */
export default function Gantt({timeline, time, onSeek, narrow}: Props): ReactNode {
  const width = narrow ? 440 : 720;
  const plot = {from: narrow ? 60 : 112, to: width - 12};
  const rows = rowsOf(timeline, narrow);
  const height = rows.length * (ROW + GAP) + AXIS;
  const x = (at: number) => plot.from + (Math.min(at, timeline.duration) / timeline.duration) * (plot.to - plot.from);
  const ticks = Array.from({length: Math.floor(timeline.duration) + 1}, (_, second) => second);

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
          <g key={row.label}>
            <text className={styles.ganttLabel} x={0} y={y + ROW / 2 + 4}>
              {row.label}
            </text>
            <rect className={styles.ganttTrack} x={plot.from} y={y} width={plot.to - plot.from} height={ROW} rx={4} />
            {row.spans.map((span) => (
              <g key={`${span.from}-${span.value}`} data-kind={span.value}>
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

export const legend: {kind: string; label: string}[] = [
  {kind: 'work', label: 'your script works'},
  {kind: 'blocked', label: 'your script waits (blocked)'},
  {kind: 'poll', label: 'your script polls'},
  {kind: 'transfer', label: 'request / response on the way'},
  {kind: 'running', label: 'script.php runs in PHP-FPM'},
  {kind: 'ready', label: 'response ready, not yet read'},
];
