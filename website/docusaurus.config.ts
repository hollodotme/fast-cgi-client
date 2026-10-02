import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const repository = 'https://github.com/hollodotme/fast-cgi-client';
const apiVersions = ['4.x', '3.x', '2.x', '1.x'];

const config: Config = {
  title: 'FastCGI Client',
  tagline: 'Send requests (a)synchronously to PHP-FPM using the FastCGI protocol',
  favicon: 'img/favicon.svg',

  future: {
    v4: true,
  },

  url: 'https://fast-cgi-client.hollo.me',
  baseUrl: '/',
  trailingSlash: false,

  organizationName: 'hollodotme',
  projectName: 'fast-cgi-client',

  onBrokenLinks: 'throw',
  onBrokenAnchors: 'throw',
  markdown: {
    format: 'detect',
    hooks: {
      onBrokenMarkdownLinks: 'throw',
    },
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  clientModules: ['./src/fonts.ts'],

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          editUrl: ({version, docPath}) =>
            version === 'current' ? `${repository}/edit/4.x-dev/website/docs/${docPath}` : undefined,
          lastVersion: 'current',
          versions: {
            current: {label: '4.x'},
            '3.x': {banner: 'none'},
          },
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    image: 'img/social-card.png',
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'FastCGI Client',
      logo: {
        alt: 'FastCGI Client',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Docs',
        },
        {
          type: 'dropdown',
          label: 'API reference',
          position: 'left',
          items: apiVersions.map((version) => ({
            label: version,
            href: `pathname:///api/${version}/index.html`,
            target: '_self',
          })),
        },
        {
          type: 'docsVersionDropdown',
          position: 'right',
        },
        {
          href: repository,
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Docs',
          items: [
            {label: 'Getting started', to: '/docs/getting-started'},
            {label: 'Use cases & examples', to: '/docs/category/use-cases--examples'},
            {label: 'API reference', href: 'pathname:///api/4.x/index.html', target: '_self'},
          ],
        },
        {
          title: 'Project',
          items: [
            {label: 'Contribution guide', to: '/contributing'},
            {label: 'License', to: '/license'},
            {label: 'Changelog', to: '/changelog/4.x'},
            {label: 'Migrating from 3.x', to: '/docs/migration'},
          ],
        },
        {
          title: 'More',
          items: [
            {label: 'GitHub', href: repository},
            {label: 'Packagist', href: 'https://packagist.org/packages/hollodotme/fast-cgi-client'},
          ],
        },
      ],
      copyright: `Copyright © 2016-${new Date().getFullYear()} Holger Woltersdorf & Contributors. MIT licensed.`,
    },
    prism: {
      theme: {...prismThemes.oneLight, plain: {...prismThemes.oneLight.plain, backgroundColor: '#f3f4f9'}},
      darkTheme: {...prismThemes.oneDark, plain: {...prismThemes.oneDark.plain, backgroundColor: '#161a33'}},
      additionalLanguages: ['php', 'bash', 'json', 'nginx'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
