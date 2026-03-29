---
name: instructor-release
description: Prepare and execute a new InstructorPHP version release. Use when asked to cut a release, prepare release notes, run release QA, create a release epic and tasks in bd, verify changed packages since the last tag, run curated live examples, publish via scripts/publish-ver.sh, or prepare the launch announcement.
---

# Instructor Release

This skill is the repo-local release orchestrator for InstructorPHP. A release is never a loose checklist. It is always a deterministic `bd` workflow with explicit gates, tracked blockers, and a final approval step before publish.

Treat these files as source of truth:

- `AGENTS.md` for repo rules, `bd`, and git ownership constraints
- `CONTRIBUTING.md` for release and package-management workflow
- `QUALITY.md` for verification lanes
- `scripts/publish-ver.sh` for the actual publish path
- `scripts/sync-ver.sh` for version synchronization
- `.github/workflows/php.yml` for compatibility matrix coverage
- `.github/workflows/split.yml` for post-tag split package publishing
- `docs/release-notes/*.mdx` for public release-note shape

Read these references as needed:

- `references/bd-release-template.md`
- `references/preflight-checks.md`
- `references/examples-policy.md`
- `references/release-notes-template.mdx`
- `references/post-release.md`

## When To Use

Use this skill when the user asks to:

- create or ship a new version release
- prepare release notes or package-change summaries
- run release-candidate QA or production-readiness checks
- decide which tests, examples, docs checks, and CI reproductions are required before publish
- publish with `scripts/publish-ver.sh`
- prepare or post the release announcement

## Release Rules

- A release always gets a `bd` epic and child tasks.
- Root quality gates must be green before publish unless the user explicitly accepts a waiver.
- Split-package verification must be green before publish unless the user explicitly accepts a waiver.
- Live examples are expensive. Run the curated live smoke set once per release cycle, then retry only failed examples after fixes.
- Do not publish, tag, push, or post to X without explicit approval in the current conversation.
- Do not hide failing checks. Record them in `bd` notes and block the release until resolved or explicitly waived.

## Default Workflow

1. Determine the target version and latest tag.
2. Create the `bd` epic and child tasks from `references/bd-release-template.md`.
3. Inventory changed packages since the last tag, focusing on `packages/*/src`.
4. Run preflight from `references/preflight-checks.md`.
5. Run curated live examples from `references/examples-policy.md`.
6. Produce per-package change summaries from diffs.
7. Consolidate those summaries into a release draft.
8. Write `docs/release-notes/vX.Y.Z.mdx` using `references/release-notes-template.mdx`.
9. Re-run final release gates if the release-note or versioning work changed generated outputs.
10. Ask for explicit approval before `scripts/publish-ver.sh`.
11. After publish, verify the GitHub release and split workflow kickoff.
12. Hand off announcement creation to `$tweet-package` and optional posting to `$xurl`.

## Deterministic `bd` Execution

A release is always multi-step. Use `bd`, not ad hoc notes.

1. Create or reuse a scoped release epic.
2. Create child tasks by lane:
   - scope and changed-package inventory
   - root preflight
   - split-package verification
   - live example verification
   - package change summaries
   - docs/API drift audit
   - release notes draft
   - publish gate
   - post-release communication
3. Claim tasks before work: `bd update <id> --claim --json`.
4. Record exact commands, failures, retries, and blockers in task notes.
5. Create `discovered-from` follow-up tasks for unrelated debt or tooling defects.
6. Close tasks only when the gate is green or the blocker is explicitly tracked and waived.

## Preflight Policy

Default release gates:

- `composer validate --strict`
- `composer test-all`
- `composer qa`
- `./scripts/run-all-tests.sh`
- `composer qa:docs`
- `composer docs drift --tier=public`
- `act pull_request -W .github/workflows/php.yml -j build --dryrun`

Add targeted non-dryrun `act` cells when:

- dependency constraints changed
- workflow behavior changed
- compatibility surfaces changed
- the current release includes install-time or matrix-sensitive fixes

## Example Policy

Use the deterministic example-selection rules in `references/examples-policy.md`.

Key rules:

- do not run the entire example corpus by default
- run the curated live smoke set once per release cycle
- add conditional live examples only for changed surfaces
- retry only the failed examples after fixes
- use non-live examples as supplementary verification, not as a substitute for live smoke coverage

## Package Change Summaries

Summaries must be package-aware and diff-based.

- identify changed packages from `packages/*/src` since the last tag
- inspect package diffs directly; do not rely solely on generated scripts that require external CLIs
- focus on developer-facing changes, public API shifts, fixes, behavior changes, and upgrade impact
- keep one summary per changed package, then consolidate into the release draft

## Release Notes

Write the public note to `docs/release-notes/vX.Y.Z.mdx`.

Requirements:

- match the existing repo release-note style
- include user-visible highlights, upgrade notes, and breaking changes if any
- reflect the package summaries, not just a raw changelog
- call out docs, compatibility, telemetry, runtime, agent, or provider changes only when they matter to users

Use `references/release-notes-template.mdx` as the starting shape.

## Publish And Communication Gates

Before publish:

- summarize the exact green gates
- summarize any waived or unresolved risk
- ask for explicit approval to run `scripts/publish-ver.sh <version>`

After publish:

- verify the main GitHub release exists
- verify the split workflow started
- prepare announcement material

For announcement work:

- use `$tweet-package` to draft the short release announcement package
- use `$xurl` only if the user explicitly asks to post

## Reporting Standard

The final release summary must include:

- target version and base tag
- changed packages
- commands actually run
- pass/fail status by release gate
- live examples run and retried
- blockers, waivers, and follow-up `bd` tasks
- release-note file path
- whether publish happened
- whether announcement drafting/posting happened
