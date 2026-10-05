import type {ReactNode} from 'react';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';
import data from '@site/src/data/site-data.json';
import styles from './Home.module.css';

type Contributor = {
  login: string;
  name: string;
  role: string;
  commits: number;
  avatar: string | null;
};

const initials = (name: string) =>
  name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

function Person({person}: {person: Contributor}): ReactNode {
  const detail =
    person.role !== 'Contributor'
      ? person.role
      : person.commits > 0
        ? `${person.commits} ${person.commits === 1 ? 'commit' : 'commits'}`
        : 'Contributor';

  return (
    <li>
      <a className={styles.person} href={`https://github.com/${person.login}`} data-role={person.role}>
        {person.avatar !== null ? (
          <img className={styles.avatar} src={person.avatar} alt="" width={64} height={64} loading="lazy" />
        ) : (
          <span className={styles.avatar} aria-hidden="true">
            {initials(person.name)}
          </span>
        )}
        <span className={styles.personName}>{person.name}</span>
        <span className={styles.personDetail}>{detail}</span>
      </a>
    </li>
  );
}

/** Everybody who contributed to the library, the maintainer and the original author first */
export default function Contributors(): ReactNode {
  const people = data.contributors as Contributor[];
  if (people.length === 0) {
    return null;
  }

  return (
    <section className={styles.section}>
      <div className="container">
        <Heading as="h2" className={styles.sectionTitle}>
          Built by {people.length} people
        </Heading>
        <p className={styles.sectionLede}>
          The library started as a port of Pierrick Charron's PHP-FastCGI-Client and grew with every pull request.
          Want to be next? Read the <Link to="/contributing">contribution guide</Link>.
        </p>
        <ul className={styles.people}>
          {people.map((person) => (
            <Person key={person.login} person={person} />
          ))}
        </ul>
      </div>
    </section>
  );
}
