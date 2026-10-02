import {useState, type KeyboardEvent, type PointerEvent, type ReactNode} from 'react';
import data from '@site/src/data/site-data.json';
import styles from './Home.module.css';

const compact = new Intl.NumberFormat('en', {notation: 'compact', maximumFractionDigits: 1});
const exact = new Intl.NumberFormat('en');
const day = new Intl.DateTimeFormat('en', {day: 'numeric', month: 'short', timeZone: 'UTC'});
const longDay = new Intl.DateTimeFormat('en', {weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC'});

type Day = {date: string; downloads: number};

const WIDTH = 240;
const HEIGHT = 56;
const PAD = 4;

/**
 * The downloads per day as a line. The crosshair follows the pointer or the arrow keys and shows the downloads of
 * the nearest day, the latest day is marked.
 */
function Sparkline({days}: {days: Day[]}): ReactNode {
  const [active, setActive] = useState<number | null>(null);
  const max = Math.max(...days.map((entry) => entry.downloads));
  const x = (index: number) => PAD + (index / (days.length - 1)) * (WIDTH - 2 * PAD);
  const y = (downloads: number) => HEIGHT - PAD - (downloads / max) * (HEIGHT - 2 * PAD);
  const line = days.map((entry, index) => `${index === 0 ? 'M' : 'L'}${x(index).toFixed(1)},${y(entry.downloads).toFixed(1)}`).join('');
  const last = days.length - 1;
  const shown = active ?? last;

  const track = (event: PointerEvent<SVGSVGElement>) => {
    const box = event.currentTarget.getBoundingClientRect();
    const position = ((event.clientX - box.left) / box.width) * WIDTH;
    setActive(Math.min(last, Math.max(0, Math.round(((position - PAD) / (WIDTH - 2 * PAD)) * last))));
  };

  const step = (event: KeyboardEvent<SVGSVGElement>) => {
    const moves: Record<string, number> = {ArrowLeft: -1, ArrowRight: 1, Home: -Infinity, End: Infinity};
    if (event.key in moves) {
      event.preventDefault();
      setActive(Math.min(last, Math.max(0, (active ?? last) + moves[event.key])));
    }
  };

  return (
    <div className={styles.sparkline}>
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        preserveAspectRatio="none"
        tabIndex={0}
        role="img"
        aria-label={`Downloads per day over the last ${days.length} days, between ${exact.format(
          Math.min(...days.map((entry) => entry.downloads)),
        )} and ${exact.format(max)}. Use the arrow keys to read single days.`}
        onPointerMove={track}
        onPointerLeave={() => setActive(null)}
        onKeyDown={step}
        onBlur={() => setActive(null)}>
        <path className={styles.sparkArea} d={`${line}L${x(last)},${HEIGHT}L${x(0)},${HEIGHT}Z`} />
        <path className={styles.sparkLine} d={line} vectorEffect="non-scaling-stroke" />
        {active !== null && (
          <line className={styles.sparkCrosshair} x1={x(active)} x2={x(active)} y1={0} y2={HEIGHT} vectorEffect="non-scaling-stroke" />
        )}
      </svg>
      <span
        className={styles.sparkDot}
        style={{left: `${(x(shown) / WIDTH) * 100}%`, top: `${(y(days[shown].downloads) / HEIGHT) * 100}%`}}
        aria-hidden="true"
      />
      <p className={styles.sparkReading} aria-live="polite">
        {active === null
          ? `${day.format(new Date(days[0].date))} – ${day.format(new Date(days[last].date))}`
          : `${longDay.format(new Date(days[active].date))}: ${exact.format(days[active].downloads)}`}
      </p>
    </div>
  );
}

/** Downloads, stars and dependents, fetched from Packagist and GitHub when the website is built */
export default function Stats(): ReactNode {
  const {downloads, topDependents} = data;
  const days = downloads.daily as Day[];
  const latest = days.at(-1);

  if (downloads.total === 0) {
    return null;
  }

  return (
    <section className={styles.stats} aria-label="Usage of the library">
      <div className={styles.statHero}>
        <p className={styles.statValue}>{compact.format(downloads.total)}</p>
        <p className={styles.statLabel}>Downloads on Packagist</p>
      </div>

      {latest !== undefined && days.length > 1 && (
        <div className={styles.stat}>
          <p className={styles.statValue}>{compact.format(latest.downloads)}</p>
          <p className={styles.statLabel}>Downloads on {day.format(new Date(latest.date))}</p>
          <Sparkline days={days} />
        </div>
      )}

      <div className={styles.stat}>
        <p className={styles.statValue}>{exact.format(downloads.stars)}</p>
        <p className={styles.statLabel}>Stars on GitHub</p>
      </div>

      <div className={styles.stat}>
        <p className={styles.statValue}>{downloads.dependents}</p>
        <p className={styles.statLabel}>Packages built on it, like</p>
        <ul className={styles.dependents}>
          {topDependents.map((dependent) => (
            <li key={dependent.name}>
              <a href={`https://packagist.org/packages/${dependent.name}`}>{dependent.name}</a>
            </li>
          ))}
        </ul>
      </div>

      <p className={styles.statSource}>
        Numbers from <a href="https://packagist.org/packages/hollodotme/fast-cgi-client/stats">Packagist</a> and
        GitHub, updated daily
      </p>
    </section>
  );
}
