# Quality, Testing & Tooling

All commands below run from the monorepo root unless noted otherwise.

## Purpose

This repository uses multiple verification layers on purpose:

- tests catch behavioral regressions
- static analysis catches type and API misuse early
- style and structural rules keep the codebase consistent
- docs QA protects public examples and package documentation
- matrix builds catch dependency-compatibility breakage across supported environments

No single command is sufficient for every change. Pick the smallest useful set locally, then use the full matrix for compatibility-sensitive work.

## Quick Reference

| Command | Use it when | What it does |
|---------|-------------|--------------|
| `composer test` | default local test run | Unit + Feature + Regression suites |
| `composer test-all` | cross-package or release-sensitive changes | every suite, including Integration |
| `composer test:telemetry-interop` | telemetry live-backend validation | opt-in telemetry integration suite |
| `composer qa` | normal code QA | PHPStan + Psalm + Pint + Semgrep |
| `composer qa:docs` | docs/API example changes | docs QA across package docs |
| `composer docs drift` | docs freshness review | detects package docs drift |
| `composer dead-code` | cleanup and refactors | dead code detection via PHPStan |
| `composer unused-deps` | dependency cleanup | unused Composer dependency detection |
| `composer bench` | performance-sensitive changes | PHPBench benchmarks |
| `act pull_request -W .github/workflows/php.yml -j build` | local CI reproduction | executes the real GitHub Actions test workflow locally |

## Testing Strategy

### Test taxonomy

Tests are organized around intent, not just location.

- **Unit**: fast, isolated domain and service tests
- **Feature**: higher-level behavior inside the monorepo boundary
- **Integration**: external systems, cross-boundary behavior, or realistic end-to-end flows
- **Regression**: permanent coverage for previously fixed bugs

The default local run is intentionally biased toward fast, high-signal coverage:

```bash
composer test
```

That executes Pest with:

- `Unit`
- `Feature`
- `Regression`

and excludes the `docs-qa` group from the default run.

For the broadest test run:

```bash
composer test-all
```

### What to run by change type

Use this as the default policy:

- **small implementation change in one package**: `composer test`
- **serializer/schema/deserialization/config/runtime changes**: `composer test && composer qa`
- **dependency constraint changes**: `composer test && composer qa && act pull_request -W .github/workflows/php.yml -j build`
- **docs or example changes**: `composer qa:docs`
- **release-sensitive or compatibility work**: `composer test-all && composer qa && act pull_request -W .github/workflows/php.yml -j build`

### Package-level tests

Packages can still be tested independently:

```bash
cd packages/instructor
composer test
composer phpstan
composer psalm
```

Use package-level runs for focused iteration. They are not a substitute for monorepo-root verification when internal package interactions changed.

## Integration Tests

Integration tests are intentionally not part of the default `composer test` path.

Use them when:

- code crosses package boundaries in non-trivial ways
- a real backend or protocol matters
- you are validating release-sensitive behavior

### Opt-in live backend suites

The telemetry interoperability suite is the current live-backend example:

```bash
TELEMETRY_INTEROP_ENABLED=1 composer test:telemetry-interop
```

Current environment requirements:

- `TELEMETRY_INTEROP_ENABLED=1`
- Logfire env:
  - `LOGFIRE_TOKEN`
  - `LOGFIRE_OTLP_ENDPOINT`
  - `LOGFIRE_READ_TOKEN`
- Langfuse env:
  - `LANGFUSE_BASE_URL`
  - `LANGFUSE_PUBLIC_KEY`
  - `LANGFUSE_SECRET_KEY`
- `OPENAI_API_KEY` for inference, streaming, and agent runtime smoke coverage
- a working `codex` CLI with valid auth/config for the AgentCtrl smoke portion

When these env vars are absent, those suites should skip rather than fail.

## Static Analysis And QA

### PHPStan

```bash
composer qa:phpstan
# or
composer phpstan
```

Primary configuration:

- `phpstan.neon`
- `phpstan-baseline.neon`

Use PHPStan for broad type and API correctness across the monorepo.

### Psalm

```bash
composer qa:psalm
# or
composer psalm

composer psalm-unused
```

Primary configuration:

- `packages/instructor/psalm.xml`

Use Psalm as the second static-analysis pass. It catches issues PHPStan misses and supports unused-code discovery.

### Pint

```bash
composer qa:pint
composer pint
```

- `composer qa:pint` is the non-destructive check
- `composer pint` applies formatting

Primary configuration:

- `packages/agents/pint.json`

### Semgrep

```bash
composer qa:semgrep
```

Semgrep enforces repository-specific structural and architectural rules that ordinary linters do not capture.

Rule layout:

| Location | Scope |
|----------|-------|
| `.qa/semgrep/*.yml` | global cross-package rules |
| `packages/<pkg>/.qa/semgrep.yml` | package-specific rules |

Current rule themes include:

- event bus usage restrictions
- config model conventions
- method naming conventions
- test placement rules

### Dead code and dependency hygiene

```bash
composer dead-code
composer dead-code-debug
composer unused-deps
composer psalm-unused
```

Use these when refactoring, splitting packages, or tightening package boundaries.

## Docs QA

Documentation is part of the product surface and has its own verification path.

```bash
composer qa:docs
# or
composer docs qa
```

Docs QA checks:

1. anti-drift regex rules
2. PHP-snippet regex rules
3. AST-grep rules on fenced PHP snippets
4. broken relative links
5. `php -l` on fenced PHP blocks
6. incomplete snippet detection and safe skipping

Profiles live in:

- `packages/doctools/resources/config/quality/profiles/`

Package-local docs rules can be added in:

- `packages/<pkg>/docs/.qa/rules.yaml`

### Drift detection

```bash
composer docs drift
composer docs drift --tier=public
composer docs drift -f json
```

Use drift detection to identify packages where `src/` has moved ahead of docs.

## Benchmarks

```bash
composer bench
```

Use benchmarks only when performance is part of the change. They are not a substitute for behavioral tests.

Benchmark configuration:

- `phpbench.json`

## CI Matrix

The primary compatibility workflow lives in:

- `.github/workflows/php.yml`

This is the source of truth for the supported dependency matrix. Local helpers must not redefine that matrix independently.

### Current matrix axes

The workflow currently exercises:

- PHP: `8.3`, `8.4`, `8.5`
- Symfony line: `^7.3`, `^8.0`
- Composer strategy: `--prefer-stable`, `--prefer-lowest`
- DocBlock line: `^5.6` by default, with an explicit compatibility include for `^6.0.3`

Matrix exclusion:

- PHP `8.3` with Symfony `^8.0`

Targeted compatibility include:

- PHP `8.4`
- Symfony `^8.0`
- Composer `--prefer-stable`
- `phpdocumentor/reflection-docblock:^6.0.3`

That explicit include exists to catch the Symfony 8 + DocBlock 6 compatibility path that previously regressed.

## Running CI Locally With `act`

Prefer `act` over duplicating the GitHub Actions matrix in shell scripts. `act` executes the workflow YAML directly, which keeps local CI aligned with GitHub Actions.

Repository-local defaults live in:

- `.actrc`

### Prerequisites

- Docker-compatible runtime
- Colima on macOS is supported
- `act` installed locally

Install `act`:

```bash
brew install act
```

### Colima setup on macOS

Typical startup sequence:

```bash
colima start
docker context use colima
docker ps
```

This repo already commits the runner mapping in `.actrc`, so the basic commands should be non-interactive. If you need to override it manually:

```bash
act pull_request \
  -W .github/workflows/php.yml \
  -j build \
  -P ubuntu-24.04=shivammathur/node:24.04 \
  --container-daemon-socket -
```

Current repo-local defaults:

- `ubuntu-24.04` maps to `shivammathur/node:24.04`
- `--container-daemon-socket -` avoids Colima host socket mount issues on macOS

### Useful `act` commands

List workflows:

```bash
act --list
```

Validate workflow resolution without running containers:

```bash
act pull_request -W .github/workflows/php.yml -j build --dryrun
```

Run the full test workflow locally:

```bash
act pull_request -W .github/workflows/php.yml -j build
```

Run one matrix combination:

```bash
act pull_request \
  -W .github/workflows/php.yml \
  -j build \
  --matrix php:8.4 \
  --matrix symfony:^8.0 \
  --matrix composer:--prefer-stable \
  --matrix reflection_docblock:^6.0.3
```

Use `act` for:

- dependency compatibility work
- workflow debugging
- reproducing a CI-only failure locally

On Apple Silicon, if a workflow image or action misbehaves under the native architecture, retry with:

```bash
act pull_request -W .github/workflows/php.yml -j build --container-architecture linux/amd64
```

## Preferred Local Verification Flow

For most code changes:

```bash
composer test
composer qa
```

For dependency or compatibility changes:

```bash
composer test
composer qa
act pull_request -W .github/workflows/php.yml -j build
```

For release-sensitive work:

```bash
composer test-all
composer qa
composer qa:docs
act pull_request -W .github/workflows/php.yml -j build
```

## External Tool Requirements

| Tool | Purpose | Install |
|------|---------|---------|
| PHP 8.3+ | runtime, tests, static analysis | project requirement |
| Composer | dependency management and script entrypoint | project requirement |
| Docker / Colima | container runtime for local CI via `act` | `brew install colima docker` |
| `act` | run GitHub Actions workflows locally | `brew install act` |
| [Semgrep](https://semgrep.dev/) | structural source-code rules | `brew install semgrep` or `pip install semgrep` |
| [ast-grep](https://ast-grep.github.io/) | structural docs snippet checks | `brew install ast-grep` or `npm i -g @ast-grep/cli` |
| `gh` | GitHub workflow and release operations | `brew install gh` |

## Notes

- `composer qa` does **not** include docs QA; run `composer qa:docs` separately when docs changed.
- `composer test` is the fast default, not the exhaustive one.
- package-level verification is useful for focused work, but monorepo-root verification is authoritative for shared dependency and integration changes.
- prefer `act` for matrix reproduction because the workflow file is the single source of truth.
