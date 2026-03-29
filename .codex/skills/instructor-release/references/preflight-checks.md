# Release Preflight Checks

These are the default release gates for InstructorPHP.

## Required Root Gates

Run from repo root:

```bash
composer validate --strict
composer test-all
composer qa
./scripts/run-all-tests.sh
composer qa:docs
composer docs drift --tier=public
act pull_request -W .github/workflows/php.yml -j build --dryrun
```

Interpretation:

- `composer test-all` proves the monorepo test surface is green
- `composer qa` proves root static-analysis, style, and Semgrep gates are green
- `./scripts/run-all-tests.sh` proves split packages still test in isolation
- `composer qa:docs` catches public docs breakage
- `composer docs drift --tier=public` catches obvious docs/API drift risk
- `act ... --dryrun` proves the workflow still resolves locally

## Additional Gates

Run targeted non-dryrun `act` cells when:

- dependency constraints changed
- root or package Composer manifests changed
- `.github/workflows/php.yml` changed
- Symfony or compatibility-sensitive behavior changed

Example:

```bash
act pull_request \
  -W .github/workflows/php.yml \
  -j build \
  --matrix php:8.4 \
  --matrix symfony:^8.0 \
  --matrix composer:--prefer-stable \
  --matrix reflection_docblock:^6.0.3
```

Run telemetry interop only when that surface changed and the env is available:

```bash
TELEMETRY_INTEROP_ENABLED=1 composer test:telemetry-interop
```

## Docs/API Drift Audit

`composer docs drift --tier=public` is the detection pass, not the full audit.

For each changed package:

1. inspect `packages/<pkg>/src`
2. inspect the corresponding `packages/<pkg>/docs`, `README.md`, and `CHEATSHEET.md` when present
3. decide whether the release changes public behavior, setup, configuration, compatibility, or upgrade guidance
4. block the release if public docs materially lag behind the shipped API

When the root `examples/` or cookbook-facing docs changed, also inspect:

- `docs/`
- `packages/setup/resources/config/examples.yaml`
- `packages/setup/resources/config/docs.yaml`

## Blocker Policy

- A red gate blocks release by default.
- Do not publish with failing tests unless the user explicitly approves a waiver.
- If the failure is unrelated repo debt, create a `bd` follow-up but still treat it as a release blocker unless the user explicitly waives it.
