import type {ReactNode} from 'react';
import Heading from '@theme/Heading';
import styles from './Home.module.css';

/** The GitHub Sponsors card of the maintainer */
export default function Sponsor(): ReactNode {
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
        <div className={styles.sponsorFrame}>
          <iframe
            className={styles.sponsorCard}
            src="https://github.com/sponsors/hollodotme/card"
            title="Sponsor hollodotme"
            height="225"
            width="600"
            loading="lazy"
          />
        </div>
      </div>
    </section>
  );
}
