# Quality, Testing & Tooling

All commands below run from the monorepo root unless noted otherwise.

## Quick Reference

| Command | What it does |
|---------|-------------|
| `composer qa` | Full QA pipeline (PHPStan + Psalm + Pint + Semgrep) |
| `composer test` | Unit + Feature + Regression tests |
| `composer test-all` | Every test in the monorepo, no exclusions |
| `composer bench` | PHPBench benchmarks (instructor + polyglot) |
| `composer docs qa` | Docs QA checks across all packages |
| `composer docs drift` | Detect documentation drift per package |

## Testing

### Test Suites

Tests are organised into four Pest/PHPUnit suites defined in `phpunit.xml`:

- **Unit** — fast, isolated tests per package
- **Feature** — higher-level behaviour tests (docs-qa Feature tests are excluded from the default run)
- **Integration** — tests that hit real services or cross package boundaries
- **Regression** — tests covering previously-fixed bugs

```bash
# Default run: Unit + Feature + Regression
composer test

# Everything, including Integration
composer test-all
```

### Package-Level Tests

Each package can be tested independently:

```bash
cd packages/instructor
composer test
```

> **Note:** Package-level tests resolve dependencies from Packagist, so they may not reflect unpublished monorepo changes.

## Static Analysis

### PHPStan

```bash
composer qa:phpstan        # or: composer phpstan
```

Configuration: `phpstan.neon` (with `phpstan-baseline.neon` for suppressed issues).

### Psalm

```bash
composer qa:psalm          # or: composer psalm
composer psalm-unused      # find unused code via Psalm
```

Configuration: `packages/instructor/psalm.xml`.

### Pint (Code Style)

```bash
composer qa:pint           # dry-run check (exits non-zero on diff)
composer pint              # apply fixes
```

Configuration: `packages/agents/pint.json`.

## Semgrep (Source Code Rules)

```bash
composer qa:semgrep
```

Scans PHP source files for architectural anti-patterns using [Semgrep](https://semgrep.dev/).

### Rule Layout

| Location | Scope |
|----------|-------|
| `.qa/semgrep/*.yml` | Global cross-package rules |
| `packages/<pkg>/.qa/semgrep.yml` | Package-specific rules |

### Current Global Rule Files

- **`global.yml`** — EventBusResolver ban, no optional `CanHandleEvents` in constructors
- **`config-model.yml`** — No `Settings::*` / `CanReadConfig` in core runtime, no preset selectors, no `using(string)` static constructors, no `with*Config(...)` mutators outside builders
- **`method-conventions.yml`** — `fromXxx(...)` must be static; `withXxx(...)` must be instance
- **`test-placement.yml`** — No filesystem I/O, sleep, or subprocess execution in Unit/Feature/Regression tests

Each rule file contains `paths.include` / `paths.exclude` to scope scanning and an `exclude` list for approved exceptions.

### Writing Semgrep Rules

Rules follow the [Semgrep rule syntax](https://semgrep.dev/docs/writing-rules/rule-syntax/). Each YAML file has a top-level `rules` list:

```yaml
rules:
  - id: my-package.no-bad-pattern
    message: "Explanation of why this is wrong and what to do instead."
    severity: ERROR
    languages: [php]
    paths:
      include:
        - "/packages/my-package/src/**/*.php"
    pattern-regex: "\\bBadPattern\\b"
```

Keep package-only rules inside the package; keep shared rules at repo root.

## Docs QA (Documentation Quality)

```bash
composer docs qa                    # alias: composer qa:docs
```

Runs `scripts/docs-qa-all.sh` which iterates over `packages/*/docs/` and invokes the `qa` command from `bin/instructor-docs` with the appropriate profile per package.

### What It Checks

1. **Anti-pattern regex rules** — catches references to removed/renamed APIs in markdown text
2. **PHP snippet regex rules** — same, scoped to fenced `\`\`\`php` code blocks
3. **AST-grep rules** — structural pattern matching on PHP snippets using [ast-grep](https://ast-grep.github.io/)
4. **Broken local links** — validates relative markdown links resolve to existing files
5. **PHP lint** — runs `php -l` on each fenced PHP block
6. **Incomplete snippet detection** — auto-skips blocks with unmatched braces

### Rule Profiles

Profiles live in `packages/doctools/resources/config/quality/profiles/`:

| Profile | Rules | Typical package |
|---------|-------|----------------|
| `instructor` | 14 | instructor |
| `polyglot` | 8 | polyglot |
| `http-client` | 7 | http-client |
| `agents` | 5 | agents |
| `agent-ctrl` | 0 | agent-ctrl |
| `none` | 0 | everything else |

The script auto-selects the profile based on package name.

### Skipping Snippets

Code blocks are automatically skipped when they contain:

- `...` (ellipsis / incomplete code)
- `{{...}}` or `{%...%}` (template syntax)
- HTML tags
- `qa:skip` comment
- `qa:expect-fail` comment

### Package-Local Rules

Place a `.qa/rules.yaml` inside any `packages/<pkg>/docs/` directory to add package-specific rules that layer on top of the profile:

```yaml
version: 1
rules:
  - id: local.no-legacy-token
    engine: regex
    scope: markdown
    pattern: '/\blegacyToken\b/'
    message: 'legacyToken must not appear in docs.'
```

### Running on a Single Package

```bash
php bin/instructor-docs qa --source-dir=packages/polyglot/docs --profile=polyglot
```

Options: `--profile`, `--rules`, `--extensions`, `--format` (text|json), `--strict`/`--no-strict`, `--ast-grep-bin`.

### Writing Docs QA Rules

Rules are YAML files with `version: 1` and a `rules` list. Each rule needs:

| Field | Required | Description |
|-------|----------|-------------|
| `id` | yes | Unique rule identifier (e.g. `pkg.no_bad_api`) |
| `engine` | yes | `regex` or `ast-grep` |
| `scope` | yes | `markdown` (full file) or `php-snippet` (fenced blocks) |
| `pattern` | yes | Regex string or ast-grep pattern |
| `message` | yes | Guidance shown when matched |
| `language` | ast-grep only | Language for ast-grep (e.g. `php`) |
| `severity` | no | Defaults to `error` |

## Documentation Drift Detection

```bash
composer docs drift                            # all packages
composer docs drift --tier=public              # only public-facing
composer docs drift --tier=public,library -r high  # high-risk public+library
composer docs drift -f packages -r medium      # bare names for scripting
composer docs drift -f json                    # full JSON output
composer docs drift polyglot sandbox           # specific packages
```

Compares `packages/*/src/` and `packages/*/docs/` modification timestamps to identify stale documentation.

### Risk Score (0–100)

| Factor | Points | Description |
|--------|--------|-------------|
| Drift age | 0–35 | 5 pts/day src is ahead of docs (max 7 days) |
| Change ratio | 0–25 | % of src files newer than newest doc |
| Docs spread | 0–15 | Gap between newest and oldest doc (0.5 pts/day, max 30 days) |
| Missing docs | 0–25 | +15 if no CHEATSHEET.md, +10 if no docs/ and >10 src files |

Risk levels: **HIGH** (≥70), **MED** (≥30), **LOW** (<30).

## Benchmarks

```bash
composer bench
```

Runs [PHPBench](https://phpbench.readthedocs.io/) across benchmark files matching `*Bench.php` in:

- `packages/instructor/tests/Benchmarks/`
- `packages/polyglot/tests/Benchmarks/`

Configuration: `phpbench.json`.

## Dead Code & Unused Dependencies

```bash
composer dead-code          # ShipMonk dead-code detector via PHPStan
composer dead-code-debug    # same, with verbose diagnostics (-vvv)
composer psalm-unused       # Psalm --find-unused-code
composer unused-deps        # composer-unused (find unused Composer deps)
```

Dead-code configuration: `phpstan-unused.neon`. To debug why a specific symbol is flagged, add it under `parameters.shipmonkDeadCode.debug.usagesOf` in that file.

## Full QA Pipeline

```bash
composer qa
```

Runs in order: `qa:phpstan` → `qa:psalm` → `qa:pint` → `qa:semgrep`.

> **Note:** `qa:docs` is not included in the composite `qa` command — run it separately with `composer docs qa` or `composer qa:docs`.

## External Tool Requirements

| Tool | Purpose | Install |
|------|---------|---------|
| [Semgrep](https://semgrep.dev/) | Source code pattern rules | `brew install semgrep` or `pip install semgrep` |
| [ast-grep](https://ast-grep.github.io/) | Structural PHP snippet matching | `brew install ast-grep` or `npm i -g @ast-grep/cli` |
| PHP 8.3+ | Lint, tests, analysis | — |
| Composer | Dependency management | — |
