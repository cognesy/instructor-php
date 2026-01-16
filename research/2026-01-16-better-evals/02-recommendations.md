# Evals review - recommendations

## Goals

- Make evals repeatable, comparable, and low-touch across models and output modes.
- Separate live model calls from deterministic replay to keep CI stable.
- Capture enough artifacts to debug StructuredOutput regressions quickly.
- Provide a path to cross-LLM comparisons without manual script edits.

## Proposed architecture

- Replace ad-hoc `evals/*/run.php` with declarative specs.
  - One `evals/*.yaml` (or JSON) file per scenario.
  - Separate dataset files (JSONL) for inputs and expectations.
- Introduce a small CLI runner (e.g., `bin/evals` or `./evals/run`) that:
  - Loads specs, expands cases, and runs executions.
  - Supports `--live` (real APIs) and `--replay` (fixtures).
  - Emits artifacts and summary reports.
- Add a capability matrix per provider:
  - `provider -> supported modes + streaming + tool calling`.
  - Use it to skip or mark unsupported cases rather than failing at runtime.
- Keep `Experiment` and `Execution` but make them spec-driven:
  - `Case` should be data-only (prompt, schema, expected).
  - `Executor` should be pluggable (StructuredOutput, Agent).

## Artifacts and reproducibility

- Create a run manifest per eval run:
  - Git SHA, config hash, dataset version, output mode, model, streaming.
- Store case artifacts to disk:
  - raw response, tool calls, parsed JSON, schema errors, final object.
- Persist observations as JSONL or CSV so they can be diffed.

## Determinism and replay

- Add a fixture cache:
  - Save raw API responses keyed by case id + model + mode + schema hash.
  - Allow replay without network calls for CI and local debugging.
- Provide stub adapters for unit tests:
  - No real APIs in tests, only in `./evals` and `./examples`.

## Scoring and reporting

- Define scoring rules in the spec:
  - strict equality, schema-only, field-level scoring, tolerances for numbers.
- Publish summary reports:
  - per model/mode table, error taxonomy, failure deltas vs baseline.
- Keep LLM-graded metrics optional:
  - do not make LLM-as-judge mandatory for core pass/fail.

## Phased rollout

1. Spec format + CLI runner + artifacts.
2. Capability matrix + deterministic replay cache.
3. StructuredOutput and Agent suites with stable scoring.
4. Reporting dashboards and trend comparisons.
