# Deterministic Documentation Publishing Cutover

Date: 2026-08-02
Epic: `instructor-bwob`

## Goal

Make documentation publication for every release deterministic across both public
sites:

- Mintlify: `https://docs.instructorphp.com`
- MkDocs on GitHub Pages: `https://cognesy.github.io/instructor-php/`

The source commit must be the only human-maintained documentation state. Generated
trees must be created, validated, and deployed by CI. A release is incomplete until
the new release note is present in both generated navigation trees and both public
sites.

## Current Failure Mode

The repository currently has contradictory ownership:

- `docs/`, package docs, cheatsheets, and examples are source material.
- `builds/docs-build` is generated, but Mintlify deploys that tracked directory.
- `builds/docs-mkdocs`, `builds/docs-site`, `builds/build-llms`, generated archives,
  and `builds/mkdocs.yml` are also tracked.
- root `mkdocs.yml` is generated and tracked outside `builds/`.
- `scripts/release/publish-ver.sh` regenerates both targets but excludes `builds/**`
  from the release commit.
- Mintlify therefore can deploy stale generated input while MkDocs regenerates in CI.
- existing QA checks content quality but does not prove release-note parity, target
  validity, successful deployment, or live availability.

At the start of this plan, 2,544 files under `builds/` are tracked. Existing staged
changes contain regenerated output plus an unrelated `QUALITY.md` clarification; the
generated output is superseded by this migration while the `QUALITY.md` change must
be preserved.

## Architectural Decision

Adopt one source pipeline with two target-specific deployment adapters:

```text
authored docs on main
        |
        v
generate Mintlify + MkDocs in a clean CI workspace
        |
        +--> release-note parity + Mintlify validation
        |
        +--> strict MkDocs static build
        |
        +--> CI-owned docs-mintlify branch --> Mintlify GitHub App
        |
        `--> GitHub Pages artifact ----------> GitHub Pages
```

The required invariant is:

> A deployable documentation artifact can only be produced from the current source
> commit by the canonical generator and must pass target validation before publish.

## Fate of `builds/`

`builds/` remains the standard runtime workspace for all generated documentation,
site output, LLM artifacts, and release archives, but becomes fully ephemeral:

- add `/builds/` to `.gitignore`;
- remove every tracked file below `builds/` from the repository index;
- retain generator target paths under `builds/`;
- generate MkDocs configuration at `builds/mkdocs.yml`, not root `mkdocs.yml`;
- remove tracked root `mkdocs.yml` because `docs/mkdocs.yml.template` is its source;
- do not add placeholder files inside `builds/`;
- CI artifacts and public deployments are the durable copies of generated output.

This decision also covers `builds/build-llms`, `builds/docs-build`,
`builds/docs-mkdocs`, `builds/docs-site`, generated tarballs, and any future output.

## Structural Changes

### 1. Canonical generation and validation

Add a single repository command that:

1. clears the known generated targets so removed source pages cannot survive locally;
2. generates Mintlify output;
3. generates MkDocs output and `builds/mkdocs.yml`;
4. verifies every `docs/release-notes/v*.mdx` source has a page and navigation entry
   in both generated targets;
5. validates Mintlify from `builds/docs-build` with a pinned CLI version;
6. builds MkDocs with `--strict --clean` into `builds/docs-site` using pinned Python
   dependencies.

Expose it through Composer and `just`. Keep target-specific commands for iteration,
but make the combined command the release and CI gate.

### 2. Unified documentation CI

Replace the legacy MkDocs workflow with a workflow that runs for pull requests,
`main` pushes, and manual recovery:

- one build job produces and validates both target artifacts;
- pull requests stop after validation and retain artifacts for diagnostics;
- `main` publishes the exact validated Mintlify artifact to `docs-mintlify`;
- `main` publishes the exact validated MkDocs HTML artifact through
  `actions/upload-pages-artifact` and `actions/deploy-pages`;
- deployment jobs use concurrency and environments to prevent stale runs winning;
- one shared production concurrency group serializes target publication and each
  publisher rechecks that its source SHA is still `origin/main` immediately before
  mutation;
- permissions are least-privilege per job;
- generated deployment output never returns to `main`.

Mintlify must be configured in its Git settings to use branch `docs-mintlify` and
repository root `/`. GitHub Pages must use GitHub Actions as its publishing source.

### 3. Release lifecycle

Update the release preflight so a target version cannot proceed unless:

- `docs/release-notes/vX.Y.Z.mdx` exists;
- combined generation and validation passes;
- both generated navigation trees include that version.

The release script must require a clean index and worktree before it mutates versions;
release notes are committed source input, not staged cargo. Synchronize package
versions before documentation generation. Commit the final candidate, push `main`
without a tag, and let CI build artifacts from that final source SHA. Wait for both
deployment statuses and poll both public release-note URLs plus a deployed source-SHA
provenance file. Only then create and push the tag and create the GitHub release.

Release documentation archives must come from the validated CI artifacts for that
source SHA, not from a pre-version local generation. Download them only when release
assets are still desired; otherwise remove them from the release payload.

### 4. Repository contracts

Update `CONTRIBUTING.md`, `QUALITY.md`, `CONTENTS.md`, `AGENTS.md`, the release and QA
skills, and `just` recipes so they all describe the same commands, ownership boundary,
deployment targets, and live release gate. Remove stale command paths and any guidance
that tells contributors to edit generated files.

### 5. Cleanup

Remove:

- all tracked `builds/**` content;
- generated root `mkdocs.yml`;
- `.github/workflows/mkdocs.yml` after replacement;
- `.github/workflows/mkdocs.yml.bak`;
- stale generated-path logic and obsolete command references discovered during the
  migration.

## Task Sequence

1. Establish the canonical source-to-artifact verification command and pin target
   tooling.
2. Implement the unified PR/build/deploy workflow and deployment helper.
3. Integrate release preflight and live two-site smoke verification.
4. Update contributor, quality, architecture, recipe, and skill contracts.
5. Land and push the replacement deployment capability while retaining the current
   tracked Mintlify input; populate and validate `docs-mintlify`.
6. Configure Mintlify and GitHub Pages to use the replacement deployment sources and
   verify both public sites.
7. In a second landing, cut over the generated-output boundary and remove obsolete
   tracked content only after the providers are confirmed on the replacement paths.
8. Run clean-checkout QA, deployment, live verification, and completion audit.

Tasks must encode dependencies so the first infrastructure landing cannot remove the
current Mintlify input, external cutover cannot happen before CI populates both
replacement targets, and cleanup cannot land before both providers are verified on
those targets.

## Risks and Mitigations

- **Mintlify branch cutover outage:** populate and validate `docs-mintlify` before
  changing Mintlify Git settings; retain `main/builds/docs-build` through the first
  infrastructure landing and until the new branch is live.
- **GitHub Pages outage:** upload and validate the Pages artifact before changing the
  repository publishing source; retain the current `gh-pages` publication until the
  Actions deployment succeeds; use the standard `github-pages` environment.
- **Generator divergence:** parity verification inspects source release notes, target
  files, and both navigation configurations.
- **Nondeterministic tooling:** pin Mintlify, Python, MkDocs, and Material versions.
- **Concurrent deployment race:** serialize both target publications in one
  production concurrency group; immediately before publishing, require
  `origin/main == source SHA`; update `docs-mintlify` linearly without force pushes.
- **Dirty local worktree:** preserve non-generated staged changes and make the cleanup
  explicit during this migration; future release execution requires a clean index and
  worktree and stages only mutations made after that precondition; never use reset,
  restore, clean, or stash.
- **Mintlify web editor overwrite:** generated deployment branch is CI-owned; edits
  must be made in source files on `main`.
- **Broken edit links:** disable MkDocs edit actions because generated pages do not
  have a reliable one-to-one source path.

## Completion Criteria

- no files under `builds/` are tracked and `/builds/` is ignored;
- root `mkdocs.yml` and legacy workflows/backups are absent;
- a clean checkout can generate and validate both targets with one command;
- PR CI contains a required combined docs build check;
- `main` deploys Mintlify from `docs-mintlify` and MkDocs from a Pages artifact;
- release scripts reject missing or unindexed release notes;
- release smoke checks cover both public sites;
- both public sites expose provenance matching the expected source SHA;
- current release navigation and pages are live on both sites;
- documentation, skills, scripts, and recipes name only current commands and paths;
- all epic tasks are closed with verification evidence.
