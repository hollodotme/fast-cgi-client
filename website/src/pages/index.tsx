import type {ReactNode} from 'react';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Hero from '@site/src/components/Home/Hero';
import UseCases from '@site/src/components/Home/UseCases';
import Compatibility from '@site/src/components/Home/Compatibility';
import Contributors from '@site/src/components/Home/Contributors';
import Sponsor from '@site/src/components/Home/Sponsor';

export default function Home(): ReactNode {
  const {siteConfig} = useDocusaurusContext();
  return (
    <Layout title="Talk to PHP-FPM directly" description={siteConfig.tagline}>
      <Hero />
      <main>
        <UseCases />
        <Compatibility />
        <Contributors />
        <Sponsor />
      </main>
    </Layout>
  );
}
