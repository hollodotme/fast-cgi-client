import type {ReactNode} from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import CodeBlock from '@theme/CodeBlock';

import styles from './index.module.css';

type Feature = {
  title: string;
  description: ReactNode;
};

const features: Feature[] = [
  {
    title: 'Sync and async',
    description: (
      <>
        Send a request and wait for the response, or fire many requests in parallel and read the responses in order
        or as soon as they are ready.
      </>
    ),
  },
  {
    title: 'Network and unix domain sockets',
    description: <>Talk to PHP-FPM over TCP or a local socket file, with configurable connect and read/write timeouts.</>,
  },
  {
    title: 'Callbacks and streaming',
    description: (
      <>
        Notify callbacks on responses and failures, and stream the output of long-running scripts while they are
        still running.
      </>
    ),
  },
  {
    title: 'All kinds of request content',
    description: (
      <>
        GET, POST, PUT, PATCH and DELETE requests with URL-encoded, multipart (file upload), JSON or plain text
        content.
      </>
    ),
  },
  {
    title: 'Strict protocol handling',
    description: (
      <>
        Validates every received record and splits oversized content into FastCGI records, as the specification
        demands.
      </>
    ),
  },
  {
    title: 'Proven compatibility',
    description: (
      <>
        No dependencies. Continuously tested against PHP-FPM 8.0 – 8.5 and FastCGI servers written in Go, Rust, C#
        and Java.
      </>
    ),
  },
];

const example = `use hollodotme\\FastCGI\\Client;
use hollodotme\\FastCGI\\Requests\\GetRequest;
use hollodotme\\FastCGI\\SocketConnections\\NetworkSocket;

$client     = new Client();
$connection = new NetworkSocket( '127.0.0.1', 9000 );
$request    = new GetRequest( '/path/to/script.php' );

echo $client->sendRequest( $connection, $request )->getBody();`;

export default function Home(): ReactNode {
  const {siteConfig} = useDocusaurusContext();
  return (
    <Layout description={siteConfig.tagline}>
      <header className={clsx('hero hero--primary', styles.hero)}>
        <div className="container">
          <Heading as="h1" className="hero__title">
            {siteConfig.title}
          </Heading>
          <p className="hero__subtitle">{siteConfig.tagline}</p>
          <div className={styles.buttons}>
            <Link className="button button--secondary button--lg" to="/docs/getting-started">
              Get started
            </Link>
            <Link className="button button--outline button--secondary button--lg" to="/docs/api-reference">
              API reference
            </Link>
          </div>
          <div className={styles.install}>
            <CodeBlock language="bash">composer require hollodotme/fast-cgi-client</CodeBlock>
          </div>
        </div>
      </header>
      <main>
        <section className={styles.features}>
          <div className="container">
            <div className="row">
              <div className="col col--6">
                <Heading as="h2">Execute PHP scripts through PHP-FPM, from PHP</Heading>
                <p>
                  The FastCGI Client speaks the FastCGI protocol directly to PHP-FPM or any other FastCGI server. Use it
                  to run background jobs in parallel worker processes, to warm up caches, or to call scripts on another
                  host — without a web server in between.
                </p>
              </div>
              <div className="col col--6">
                <CodeBlock language="php">{example}</CodeBlock>
              </div>
            </div>
            <div className="row">
              {features.map(({title, description}) => (
                <div key={title} className={clsx('col col--4', styles.feature)}>
                  <Heading as="h3">{title}</Heading>
                  <p>{description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
}
