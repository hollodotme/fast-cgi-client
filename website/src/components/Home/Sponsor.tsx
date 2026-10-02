import type {ReactNode} from 'react';
import Heading from '@theme/Heading';
import data from '@site/src/data/site-data.json';
import styles from './Home.module.css';

const SPONSORS = 'https://github.com/sponsors/hollodotme';

/** A card for sponsoring the maintainer, with the sponsor button of GitHub */
export default function Sponsor(): ReactNode {
  const maintainer = data.contributors.find((person) => person.login === 'hollodotme');

  return (
    <section className={`${styles.section} ${styles.sectionAlt}`}>
      <div className="container">
        <Heading as="h2" className={styles.sectionTitle}>
          Support its maintenance
        </Heading>
        <p className={styles.sectionLede}>
          The FastCGI Client is free and maintained in spare time. If it saves you work, consider sponsoring its
          maintainer on GitHub.
        </p>
        <div className={styles.sponsorCard}>
          {maintainer?.avatar ? (
            <img className={styles.sponsorAvatar} src={maintainer.avatar} alt="" width={72} height={72} loading="lazy" />
          ) : null}
          <div className={styles.sponsorText}>
            <p className={styles.sponsorName}>Holger Woltersdorf</p>
            <p className={styles.sponsorRole}>Maintainer since 2016</p>
            <p className={styles.sponsorRole}>
              <a href={SPONSORS}>GitHub Sponsors profile</a>
            </p>
          </div>
          <iframe
            className={styles.sponsorButton}
            src={`${SPONSORS}/button`}
            title="Sponsor hollodotme"
            height="32"
            width="114"
            loading="lazy"
          />
        </div>
      </div>
    </section>
  );
}
