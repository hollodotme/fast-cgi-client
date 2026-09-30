# Documentation website

The documentation website at https://fast-cgi-client.hollo.me, built with [Docusaurus](https://docusaurus.io).
The API reference is generated with [Doctum](https://github.com/code-lts/doctum) into `static/api`.

* `docs/` — documentation of the current major version (4.x)
* `versioned_docs/` — documentation of the previous major versions
* `src/pages/` — homepage; `license.md` and `contributing.md` are generated from `LICENSE` and
  `.github/CONTRIBUTING.md` by `scripts/generate-pages.mjs`
* `doctum.php`, `scripts/build-api.sh` — API reference of all major versions
* `src/components/Animation/` — interactive animations of the use cases, used in MDX docs as
  `<Animation scene="multipleRequests" />`. A scene in `scenes/` defines the example code and builds a timeline of
  what the client, the sockets and PHP-FPM do (`timeline.ts`); everything shown is derived from that timeline and the
  current time. New scenes are registered in `index.tsx`.

Run from the repository root:

```bash
make docs-serve   # live reload on http://localhost:3000
make docs-build   # complete website including API reference in website/build
```

The workflow `.github/workflows/docs.yml` builds the website for every pull request and deploys it to GitHub Pages
on pushes to `4.x-dev` and `master`.
