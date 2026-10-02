import {useRef, useState, type KeyboardEvent, type ReactNode} from 'react';
import Link from '@docusaurus/Link';
import useBrokenLinks from '@docusaurus/useBrokenLinks';
import Heading from '@theme/Heading';
import Animation from '@site/src/components/Animation';
import styles from './Home.module.css';

type UseCase = {
  id: string;
  tab: string;
  text: string;
  docs: string;
  animation: ReactNode;
};

const useCases: UseCase[] = [
  {
    id: 'sync',
    tab: 'Wait for the response',
    text: 'sendRequest() sends the request and returns the response, like calling a function — only that the code runs in PHP-FPM.',
    docs: '/docs/usage/single-requests#send-request-synchronously',
    animation: <Animation scene="syncRequest" minimal />,
  },
  {
    id: 'async',
    tab: 'Work while it runs',
    text: 'sendAsyncRequest() returns right away. Your script does other work and reads the response when it needs it.',
    docs: '/docs/usage/single-requests#read-the-response-after-sending-the-async-request',
    animation: <Animation scene="asyncRequest" minimal />,
  },
  {
    id: 'parallel',
    tab: 'Run many in parallel',
    text: 'Send many requests at once. PHP-FPM runs them in parallel, and you handle each response as soon as it arrives.',
    docs: '/docs/usage/multiple-requests',
    animation: <Animation scene="multipleRequests" settings={{mode: 'reactive'}} minimal />,
  },
];

/** Tabs with the main ways to send requests, each with its animation */
export default function UseCases(): ReactNode {
  const [selected, setSelected] = useState(0);
  // The hero links to this section
  useBrokenLinks().collectAnchor('how-it-works');
  const tabs = useRef<(HTMLButtonElement | null)[]>([]);

  const select = (index: number) => {
    const next = (index + useCases.length) % useCases.length;
    setSelected(next);
    tabs.current[next]?.focus();
  };

  const navigate = (event: KeyboardEvent<HTMLDivElement>) => {
    const moves: Record<string, number> = {ArrowLeft: selected - 1, ArrowRight: selected + 1, Home: 0, End: useCases.length - 1};
    if (event.key in moves) {
      event.preventDefault();
      select(moves[event.key]);
    }
  };

  const useCase = useCases[selected];

  return (
    <section id="how-it-works" className={styles.section}>
      <div className="container">
        <Heading as="h2" className={styles.sectionTitle}>
          Three ways to send a request
        </Heading>
        <p className={styles.sectionLede}>
          Press play, step through, or let it run: the animations show what your script, the socket and PHP-FPM do.
        </p>

        <div className={styles.tabs} role="tablist" aria-label="Ways to send a request" onKeyDown={navigate}>
          {useCases.map((entry, index) => (
            <button
              key={entry.id}
              ref={(element) => {
                tabs.current[index] = element;
              }}
              type="button"
              role="tab"
              id={`tab-${entry.id}`}
              aria-selected={index === selected}
              aria-controls={`panel-${entry.id}`}
              tabIndex={index === selected ? 0 : -1}
              className={styles.tab}
              onClick={() => setSelected(index)}>
              {entry.tab}
            </button>
          ))}
        </div>

        <div className={styles.tabPanel} role="tabpanel" id={`panel-${useCase.id}`} aria-labelledby={`tab-${useCase.id}`}>
          <p className={styles.tabText}>
            {useCase.text} <Link to={useCase.docs}>Read the docs</Link>
          </p>
          <div key={useCase.id}>{useCase.animation}</div>
        </div>
      </div>
    </section>
  );
}
