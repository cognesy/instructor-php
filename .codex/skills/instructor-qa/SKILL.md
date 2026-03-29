---
name: instructor-qa
description: Run multi-dimensional quality assurance for InstructorPHP. Use when asked to validate a change, assess production readiness, reproduce CI failures, harden a PR, choose the right verification commands, or investigate regressions across tests, static analysis, docs QA, dependency compatibility, and local GitHub Actions workflow runs.
---

# Instructor QA

This skill is the repo-local quality assurance specialist for InstructorPHP. It chooses the smallest sufficient verification set for simple work, and for complex or multi-step QA it turns the work into a deterministic `bd` plan and executes it methodically.

Treat these files as source of truth:

- `QUALITY.md` for verification lanes and command entrypoints
- `CONTRIBUTING.md` for contributor workflow expectations
- `AGENTS.md` for repo-specific operating rules and `bd` usage
- `.github/workflows/php.yml` for the compatibility matrix
- `.actrc` for local `act` defaults

Read `references/scenarios.md` when you need the scenario-to-command mapping or the complex QA playbooks.

## When To Use

Use this skill when the user asks to:

- run QA or decide which checks to run
- validate a feature, fix, refactor, dependency upgrade, or release candidate
- investigate a failing test, linter, docs QA run, or GitHub Actions workflow
- reproduce CI locally with `act`
- assess regression risk, production readiness, or verification gaps
- audit quality across multiple packages, layers, or tooling lanes

## Working Model

Start by classifying the request into one or more QA lanes:

- behavior tests: `composer test`, `composer test-all`, targeted Pest runs
- integration and live-backend validation: telemetry interop and other opt-in suites
- static analysis: PHPStan, Psalm, dead-code, unused dependency checks
- structural and policy checks: Pint, Semgrep
- docs quality: `composer qa:docs`, docs drift
- dependency and compatibility validation: Composer resolution plus workflow matrix cells
- CI and workflow reproduction: `act` against `.github/workflows/php.yml`
- performance: PHPBench

Prefer the smallest sufficient lane set first. Expand only when the risk profile justifies it.

## Default Execution Flow

1. Read the request, changed files, and the relevant sections of `QUALITY.md`.
2. Identify risk shape:
   - local implementation
   - docs/examples
   - dependency or Composer manifest change
   - workflow or CI change
   - cross-package or release-sensitive change
3. Choose the minimum verification bundle that can falsify the risky assumptions.
4. Run checks from fast/high-signal to broader/slower.
5. Separate failures caused by the current change from pre-existing repository debt.
6. Report exactly what ran, what passed, what failed, what was skipped, and the residual risk.

## Deterministic `bd` Path

For complex QA, do not keep the work as loose shell exploration. Build and execute a deterministic `bd` plan.

Use the `bd` path when any of these are true:

- the request spans multiple QA lanes
- the work is release-sensitive or compatibility-sensitive
- multiple failures must be triaged and separated into current vs pre-existing debt
- the change touches workflows, dependency constraints, or matrix behavior
- the work needs follow-up tasks, blocker tracking, or phased execution
- the user asks for a thorough audit or production-readiness assessment

When that happens:

1. Find an existing scoped `bd` task or create one.
2. If the work is too large for one task, create an epic and child tasks by lane or phase.
3. Claim the active task before substantial work: `bd update <id> --claim --json`.
4. Execute the plan deterministically:
   - gather context
   - run the selected checks
   - record exact commands and outcomes in task notes
   - create `discovered-from` follow-up tasks for unrelated debt
5. Close the task only after the relevant checks ran or the blockers were explicitly tracked.

Do not hide unrelated repo debt inside the current change. Track it separately.

## Repo-Specific Rules

- Prefer root-level Composer commands for authoritative verification.
- Use package-level commands only for tight iteration, then return to root verification for shared surfaces.
- Use `act` against `.github/workflows/php.yml` instead of duplicating the matrix in shell scripts.
- Treat `.github/workflows/php.yml` as the only matrix source of truth.
- When dependency constraints or workflow matrix behavior changes, run the targeted local `act` cell when feasible.
- If `composer qa` or `composer qa:docs` fails because of unrelated existing debt, say so explicitly and file a `bd` follow-up.
- For review-style requests, present findings first, ordered by severity, with concrete file references.

## Reporting Standard

Your final QA summary should always include:

- scope and risk model
- commands actually run
- passes and failures
- skipped lanes and why they were skipped
- whether failures are current-regression or pre-existing debt
- follow-up `bd` tasks created for unrelated blockers
- residual risk after the completed checks
