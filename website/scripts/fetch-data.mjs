// Fetches the data shown on the homepage when the website is built: downloads and dependents from Packagist,
// stars, contributors and the status of the CI workflows from GitHub. The avatars of the contributors are
// downloaded as well, so visitors of the website don't send requests to third parties.
//
// If a source cannot be reached, the values of the snapshot are used, so the website can always be built.
import {existsSync, mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const website = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const root = resolve(website, '..');
const snapshotFile = resolve(website, 'src/data/site-data.snapshot.json');
const dataFile = resolve(website, 'src/data/site-data.json');
const avatarDir = resolve(website, 'static/img/contributors');

const PACKAGE = 'hollodotme/fast-cgi-client';
const REPOSITORY = 'hollodotme/fast-cgi-client';
const BRANCH = '4.x-dev';
const DAYS = 90;

/** The servers the compatibility of the client is checked against, with their workflow */
const SERVERS = [
  {name: 'PHP-FPM 8.0 – 8.5', workflow: 'ci.yml'},
  {name: 'Go', workflow: 'compatibility-go.yml'},
  {name: 'Rust', workflow: 'compatibility-rust.yml'},
  {name: 'Rust (tokio)', workflow: 'compatibility-rust-tokio.yml'},
  {name: 'C#', workflow: 'compatibility-csharp.yml'},
  {name: 'Java', workflow: 'compatibility-java.yml'},
];

/** People with a special role, everybody else is a contributor */
const ROLES = {
  hollodotme: {name: 'Holger Woltersdorf', role: 'Maintainer'},
  adoy: {name: 'Pierrick Charron', role: 'Original author'},
};

const githubHeaders = {
  Accept: 'application/vnd.github+json',
  'User-Agent': 'fast-cgi-client-website',
  ...(process.env.GITHUB_TOKEN ? {Authorization: `Bearer ${process.env.GITHUB_TOKEN}`} : {}),
};

async function json(url, headers = {}) {
  const response = await fetch(url, {headers, signal: AbortSignal.timeout(15000)});
  if (!response.ok) {
    throw new Error(`${url}: HTTP ${response.status}`);
  }
  return response.json();
}

const isoDate = (date) => date.toISOString().slice(0, 10);

async function fetchDownloads() {
  const {package: info} = await json(`https://packagist.org/packages/${PACKAGE}.json`);
  const from = new Date(Date.now() - (DAYS + 1) * 86400000);
  const stats = await json(
    `https://packagist.org/packages/${PACKAGE}/stats/all.json?average=daily&from=${isoDate(from)}`,
  );
  const values = stats.values[PACKAGE] ?? Object.values(stats.values)[0];
  // The current day is not complete yet
  const today = isoDate(new Date());
  const daily = stats.labels
    .map((date, index) => ({date, downloads: values[index]}))
    .filter((day) => day.date < today)
    .slice(-DAYS);

  return {
    total: info.downloads.total,
    monthly: info.downloads.monthly,
    daily,
    stars: info.github_stars,
    dependents: info.dependents,
  };
}

async function fetchTopDependents() {
  const {packages} = await json(`https://packagist.org/packages/${PACKAGE}/dependents.json?order_by=downloads`);
  return packages
    .filter((dependent) => !dependent.abandoned)
    .sort((a, b) => b.downloads - a.downloads)
    .slice(0, 3)
    .map((dependent) => ({name: dependent.name, downloads: dependent.downloads}));
}

async function fetchCompatibility() {
  return Promise.all(
    SERVERS.map(async (server) => {
      const {workflow_runs: runs} = await json(
        `https://api.github.com/repos/${REPOSITORY}/actions/workflows/${server.workflow}/runs?per_page=30`,
        githubHeaders,
      );
      const run = runs.find((candidate) => candidate.head_branch === BRANCH && candidate.status === 'completed');
      return {
        ...server,
        url: `https://github.com/${REPOSITORY}/actions/workflows/${server.workflow}`,
        passing: run?.conclusion === 'success',
        checkedAt: run?.updated_at ?? null,
      };
    }),
  );
}

/** The contributors credited in the LICENSE file, plus everybody with commits in the repository */
async function fetchContributors() {
  const credited = [...readFileSync(resolve(root, 'LICENSE'), 'utf8').matchAll(/^\* https:\/\/github\.com\/(\S+) \((.+)\)$/gm)]
    .map(([, login, name]) => ({login, name}));
  const committers = await json(
    `https://api.github.com/repos/${REPOSITORY}/contributors?per_page=100`,
    githubHeaders,
  );
  const commits = Object.fromEntries(
    committers.filter((committer) => committer.type === 'User').map((committer) => [committer.login.toLowerCase(), committer.contributions]),
  );

  const people = new Map();
  for (const login of Object.keys(ROLES)) {
    people.set(login.toLowerCase(), {login, ...ROLES[login]});
  }
  for (const person of credited) {
    if (!people.has(person.login.toLowerCase())) {
      people.set(person.login.toLowerCase(), {...person, role: 'Contributor'});
    }
  }
  for (const committer of committers.filter((candidate) => candidate.type === 'User')) {
    if (!people.has(committer.login.toLowerCase())) {
      people.set(committer.login.toLowerCase(), {login: committer.login, name: committer.login, role: 'Contributor'});
    }
  }

  return [...people.values()].map((person) => ({...person, commits: commits[person.login.toLowerCase()] ?? 0}));
}

async function downloadAvatar(login) {
  const file = resolve(avatarDir, `${login.toLowerCase()}.png`);
  try {
    const response = await fetch(`https://github.com/${login}.png?size=96`, {signal: AbortSignal.timeout(15000)});
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    writeFileSync(file, Buffer.from(await response.arrayBuffer()));
    return true;
  } catch (error) {
    console.warn(`Avatar of ${login} not downloaded: ${error.message}`);
    return existsSync(file);
  }
}

async function attempt(name, fetcher, fallback) {
  try {
    return await fetcher();
  } catch (error) {
    console.warn(`${name}: using the snapshot, ${error.message}`);
    return fallback;
  }
}

const snapshot = JSON.parse(readFileSync(snapshotFile, 'utf8'));

const [downloads, topDependents, compatibility, contributors] = await Promise.all([
  attempt('Downloads', fetchDownloads, snapshot.downloads),
  attempt('Dependents', fetchTopDependents, snapshot.topDependents),
  attempt('Compatibility', fetchCompatibility, snapshot.compatibility),
  attempt('Contributors', fetchContributors, snapshot.contributors),
]);

mkdirSync(avatarDir, {recursive: true});
for (const contributor of contributors) {
  contributor.avatar = (await downloadAvatar(contributor.login)) ? `/img/contributors/${contributor.login.toLowerCase()}.png` : null;
}

const data = {fetchedAt: new Date().toISOString(), downloads, topDependents, compatibility, contributors};
writeFileSync(dataFile, `${JSON.stringify(data, null, 2)}\n`);

if (process.argv.includes('--update-snapshot')) {
  writeFileSync(snapshotFile, `${JSON.stringify(data, null, 2)}\n`);
}

console.log(
  `Site data: ${downloads.total} downloads, ${contributors.length} contributors, ` +
    `${compatibility.filter((server) => server.passing).length}/${compatibility.length} servers passing`,
);
