// Generates website pages from files of the repository root, so they have a single source of truth.
import {mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const website = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const root = resolve(website, '..');

const license = readFileSync(resolve(root, 'LICENSE'), 'utf8')
  .replace(/^\* (https:\/\/github\.com\/\S+) \((.+)\)$/gm, '* [$2]($1)');

const [copyright, ...rest] = license.split('\n\nContributors:\n');

writeFileSync(
  resolve(website, 'src/pages/license.md'),
  `---
title: License
---

# License

The FastCGI Client is released under the [MIT License](https://opensource.org/license/mit).

\`\`\`text
${copyright.trim()}
\`\`\`

## Contributors

${rest.join('').trim()}
`,
);

const contributing = readFileSync(resolve(root, '.github/CONTRIBUTING.md'), 'utf8')
  .replace(/^# Contributing/, '# Contribution guide');

writeFileSync(
  resolve(website, 'src/pages/contributing.md'),
  `---
title: Contribution guide
---

${contributing}`,
);

// The changelogs of all major versions, with links pointing to the generated pages
const changelogs = {
  '4.x': 'CHANGELOG.md',
  '3.x': 'docs/changelog/3.x.md',
  '2.x': 'docs/changelog/2.x.md',
  '1.x': 'docs/changelog/1.x.md',
};

mkdirSync(resolve(website, 'src/pages/changelog'), {recursive: true});

for (const [version, file] of Object.entries(changelogs)) {
  const changelog = readFileSync(resolve(root, file), 'utf8')
    .replace(/^# .*\n/, '')
    .replace(/\]\((?:\.\.\/\.\.\/|\.\/)CHANGELOG\.md\)/g, '](/changelog/4.x)')
    .replace(/\]\(\.\/(?:docs\/changelog\/)?(\d\.x)\.md(#[^)]*)?\)/g, '](/changelog/$1$2)')
    .replace(/\]\(\.\/LICENSE\)/g, '](/license)');

  writeFileSync(
    resolve(website, `src/pages/changelog/${version}.md`),
    `---
title: Changelog ${version}
---

# Changelog ${version}
${changelog}`,
  );
}
