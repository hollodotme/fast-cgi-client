import {useState, type CSSProperties, type ReactNode} from 'react';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';
import Stats from './Stats';
import styles from './Home.module.css';

type WireRecord = {
  label: string;
  /** Width in px */
  width: number;
  /** Seconds for one trip */
  duration: number;
  /** Seconds, negative so the record is already on its way when the page loads */
  delay: number;
};

type Lane = {
  /** Vertical position in % of the hero */
  top: number;
  direction: 'request' | 'response';
  records: WireRecord[];
};

/** Records travel to PHP-FPM on request lanes and back on response lanes */
const lanes: Lane[] = [
  {top: 12, direction: 'request', records: [
    {label: 'BEGIN_REQUEST', width: 104, duration: 14, delay: -2},
    {label: 'PARAMS', width: 58, duration: 14, delay: -9},
  ]},
  {top: 24, direction: 'response', records: [
    {label: 'STDOUT', width: 62, duration: 17, delay: -5},
    {label: 'END_REQUEST', width: 92, duration: 17, delay: -13},
  ]},
  {top: 36, direction: 'request', records: [
    {label: 'STDIN', width: 52, duration: 11, delay: -1},
    {label: 'STDIN', width: 52, duration: 11, delay: -6},
  ]},
  {top: 48, direction: 'response', records: [
    {label: 'STDOUT', width: 62, duration: 13, delay: -8},
  ]},
  {top: 60, direction: 'request', records: [
    {label: 'PARAMS', width: 58, duration: 16, delay: -4},
    {label: 'STDIN', width: 52, duration: 16, delay: -12},
  ]},
  {top: 72, direction: 'response', records: [
    {label: 'STDERR', width: 60, duration: 12, delay: -3},
    {label: 'END_REQUEST', width: 92, duration: 12, delay: -9},
  ]},
  {top: 84, direction: 'request', records: [
    {label: 'BEGIN_REQUEST', width: 104, duration: 18, delay: -10},
  ]},
];

const INSTALL = 'composer require hollodotme/fast-cgi-client';

function Wire(): ReactNode {
  return (
    <div className={styles.wire} aria-hidden="true">
      {lanes.map((lane) => (
        <div key={lane.top} className={styles.lane} data-direction={lane.direction} style={{top: `${lane.top}%`}}>
          {lane.records.map((record) => (
            <span
              key={record.delay}
              className={styles.record}
              style={
                {
                  '--width': `${record.width}px`,
                  '--duration': `${record.duration}s`,
                  '--delay': `${record.delay}s`,
                } as CSSProperties
              }>
              {record.label}
            </span>
          ))}
        </div>
      ))}
      <div className={styles.wireServer}>
        <span>PHP-FPM</span>
      </div>
    </div>
  );
}

function InstallCommand(): ReactNode {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(INSTALL);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  };

  return (
    <div className={styles.install}>
      <code>
        <span className={styles.prompt} aria-hidden="true">
          $
        </span>{' '}
        {INSTALL}
      </code>
      <button type="button" className={styles.copy} onClick={copy}>
        {copied ? 'Copied' : 'Copy'}
      </button>
    </div>
  );
}

export default function Hero(): ReactNode {
  return (
    <header className={styles.hero}>
      <div className={styles.heroTop}>
        <Wire />
        <div className={`${styles.heroInner} ${styles.heroText}`}>
          <Heading as="h1" className={styles.headline}>
            Talk to PHP-FPM directly.
          </Heading>
          <p className={styles.lede}>
            A PHP client for the FastCGI protocol. Run scripts through PHP-FPM from your own code — one request at a
            time, in the background, or many in parallel. No web server in between, no dependencies.
          </p>
          <InstallCommand />
          <div className={styles.actions}>
            <Link className={styles.primaryAction} to="/docs/getting-started">
              Get started
            </Link>
            <Link className={styles.secondaryAction} to="#how-it-works">
              See how it works
            </Link>
          </div>
        </div>
      </div>
      <div className={styles.heroInner}>
        <Stats />
      </div>
    </header>
  );
}
