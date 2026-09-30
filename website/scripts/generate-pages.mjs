// Generates website pages from files of the repository root, so they have a single source of truth.
import {readFileSync, writeFileSync} from 'node:fs';
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
