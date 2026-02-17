# Runtime Layer Design - Remaining Work

Last updated: 2026-02-16

Completed items were moved to `research/runtimes-done.md`.
This document tracks only unfinished runtime migration work.

## Remaining Scope

- Finish final compatibility-bridge cleanup in experimental runtime mutation APIs.
- Decide whether to run optional V2 request/config cleanup.

## Open Work By Phase

### Post-Release Cleanup (Compatibility Bridges)

Status: Partial.

Done in this phase:
- Removed fallback bridge signatures in public helpers:
  - `packages/addons/src/Image/Image.php` (`toData(...)` now requires `CanCreateStructuredOutput`)
  - `packages/auxiliary/src/Web/Filters/EmbeddingsSimilarityFilter.php`
    (constructor now requires `CanCreateEmbeddings`)
  - `packages/polyglot/src/Embeddings/Utils/EmbedUtils.php`
    (`findSimilar(...)` now accepts `CanCreateEmbeddings` only)
- Added contract-lock regression tests for no-fallback behavior and runtime-first constructors.

Remaining:
- Resolve residual nullable creator mutators in experimental module surfaces:
  - `packages/experimental/src/Module/Core/Module.php`
  - `packages/experimental/src/Module/Core/Predictor.php`
  - `packages/experimental/src/ModPredict/Core/Module.php`
  - `packages/experimental/src/ModPredict/Core/Predictor.php`
- Decision required:
  - keep these mutators as explicit optional convenience API, or remove nullable creator params in this breaking cycle.

### Phase 6: Optional V2 Request/Config Cleanup (Separate ADR)

Status: Not started (optional).

Open decision:
- Keep V1 split as-is, or move more behavior fields to request layer.

Potential scope if approved:
- Revisit grouped request config VOs only if duplication remains painful.
- Revisit moving StructuredOutput behavior fields from config to request where it simplifies per-call variation.

## Current Risks

- Constructor-owned events require composition roots to pass one shared bus to drivers and injected runtimes,
  otherwise event streams are intentionally isolated.
- Experimental nullable creator mutators may continue to blur runtime-first guarantees if intentionally retained.

## Quality Gates For Remaining Work

- Run package-focused tests at each phase boundary (`polyglot`, `instructor`, `laravel`, `agents`, `addons`).
- Run static analysis (`phpstan`, `psalm`) on changed files each phase.
- Keep constructor-owned event wiring and no-fallback regression tests green while finishing the bridge sweep.
