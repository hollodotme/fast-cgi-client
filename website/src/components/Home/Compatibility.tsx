import type {ReactNode} from 'react';
import Heading from '@theme/Heading';
import data from '@site/src/data/site-data.json';
import styles from './Home.module.css';

const date = new Intl.DateTimeFormat('en', {day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'});

/** The FastCGI servers the client is tested against, with the result of their latest workflow run */
export default function Compatibility(): ReactNode {
  const servers = data.compatibility;
  if (servers.length === 0) {
    return null;
  }

  const checked = servers
    .map((server) => server.checkedAt)
    .filter((checkedAt): checkedAt is string => checkedAt !== null)
    .sort()
    .at(-1);

  return (
    <section className={`${styles.section} ${styles.sectionAlt}`}>
      <div className="container">
        <Heading as="h2" className={styles.sectionTitle}>
          Tested against real FastCGI servers
        </Heading>
        <p className={styles.sectionLede}>
          Every change runs the test suite against PHP-FPM and against FastCGI servers written in other languages, so
          the client sticks to the protocol, not to the quirks of one server.
        </p>
        <ul className={styles.servers}>
          {servers.map((server) => (
            <li key={server.workflow}>
              <a className={styles.server} href={server.url} data-passing={server.passing}>
                <span className={styles.serverName}>{server.name}</span>
                <span className={styles.serverStatus}>
                  <span aria-hidden="true">{server.passing ? '✓' : '✕'}</span> {server.passing ? 'passing' : 'failing'}
                </span>
              </a>
            </li>
          ))}
        </ul>
        {checked !== undefined && <p className={styles.note}>Latest results from {date.format(new Date(checked))}.</p>}
      </div>
    </section>
  );
}
