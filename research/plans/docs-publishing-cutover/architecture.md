# Documentation Publishing Architecture

## Ownership

| Asset | Owner | Tracked on `main` | Durable location |
|---|---|---:|---|
| authored docs, package docs, examples, cheatsheets | humans | yes | source tree |
| `docs/mint.json` | humans | yes | source tree |
| `docs/mkdocs.yml.template` | humans | yes | source tree |
| `builds/docs-build` | generator | no | CI artifact and `docs-mintlify` branch |
| `builds/docs-mkdocs` | generator | no | CI artifact |
| `builds/mkdocs.yml` | generator | no | CI artifact |
| `builds/docs-site` | MkDocs | no | GitHub Pages deployment |
| `builds/build-llms` | generator | no | CI/release artifact when requested |
| `builds/*.tar.gz` | release script | no | GitHub release assets when requested |

## Mintlify Adapter

The `docs-mintlify` branch contains only the contents generated under
`builds/docs-build` plus a machine-readable source-SHA provenance file. CI updates the
branch linearly from validated `main` commits. Mintlify's GitHub App watches that
branch and deploys `/`.

Human and web-editor commits to this branch are unsupported because the next source
deployment replaces generated content. Branch updates include the originating source
SHA in the commit message for traceability.

## MkDocs Adapter

CI runs a strict static build using `builds/mkdocs.yml` and uploads
`builds/docs-site` with the official GitHub Pages artifact action. The deployment job
uses the `github-pages` environment and deploys that exact artifact. No `gh-pages`
branch or generated content commit is required. The site artifact contains the same
machine-readable source-SHA provenance file as the Mintlify artifact.

## Release-Note Invariant

For each `docs/release-notes/vX.Y.Z.mdx`:

- `builds/docs-build/release-notes/vX.Y.Z.mdx` exists;
- `builds/docs-build/mint.json` references `release-notes/vX.Y.Z`;
- `builds/docs-mkdocs/release-notes/vX.Y.Z.md` exists;
- `builds/mkdocs.yml` references `release-notes/vX.Y.Z.md`.

For a requested release version, all four checks are mandatory before any publish
mutation.

## Failure Semantics

- generation failure: no artifact and no deployment;
- target validation failure: artifacts may be retained for diagnostics but not
  deployed;
- deployment failure: source remains merged, deployment is visibly red, release is
  not complete;
- smoke failure or provenance mismatch: deployment is treated as failed even if
  provider status is green;
- stale workflow: publication rechecks that its source SHA is still `origin/main` and
  exits without mutation when superseded;
- concurrent workflow: one shared production concurrency group serializes both
  publication adapters; `docs-mintlify` updates are linear and never force-pushed.
