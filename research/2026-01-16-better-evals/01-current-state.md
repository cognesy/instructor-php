# Evals review - current state

## What exists

- `./evals/*/run.php` scripts are the primary entry point. Each script builds an `Experiment` with ad-hoc wiring and runs it manually.
- `packages/evals` provides:
  - `Experiment` and `Execution` orchestration.
  - Case generators (`InferenceCases`) that expand a matrix of presets x modes x streaming.
  - Executors for `StructuredOutput` and `Inference`.
  - Observers for timing, token usage, array match, and LLM-graded correctness.
  - Console display only (no durable artifacts).

## Pain points

1. Manual and brittle
   - Each `evals/*/run.php` hardcodes prompts, models, expected output, and filters.
   - Small changes require hand-editing scripts and babysitting runs.
   - Output is console-only. There is no single source of truth for results.

2. No capability gating
   - `InferenceCases::all()` expands every preset across every `OutputMode` and streaming option.
   - Providers with unsupported modes produce runtime failures or misleading metrics.
   - There is no central capability matrix (provider -> supported modes + streaming).

3. No dataset versioning or reuse
   - Inputs and expectations are embedded in PHP scripts or local files.
   - Datasets are not versioned, tagged, or shareable across eval types.

4. Weak reproducibility
   - No run manifest with git SHA, config, prompt templates, or schema version.
   - No caching or replay of responses.

5. Limited diagnostics
   - Errors are mostly surfaced as exceptions without structured failure taxonomy.
   - No standardized artifact for raw response, extracted JSON, or validation stage.

6. Scoring and reporting gaps
   - Observations exist but are not persisted or summarized per model/mode.
   - No tabular comparison view across presets and modes.

## Gaps vs best eval practices

- Lack of spec-driven evals (dataset + config in declarative form).
- No deterministic offline eval capability (fixture replay).
- No partial credit, type coercion rules, or field-level scoring config.
- No first-class support for agent workflows and tool-use traces.

## Why it matters

The manual eval loop makes it hard to:
- Compare models across `StructuredOutput` modes consistently.
- Detect regressions in tool calling and streaming deltas.
- Use evals as a repeatable acceptance gate before releases.
