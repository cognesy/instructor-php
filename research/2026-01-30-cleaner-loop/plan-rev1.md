# Cleaner Agent Loop Plan (rev1)

Goal: dramatically simplify Core agent loop/state while preserving stop reasons and event visibility. Changes are incremental and reversible.

## Guardrails (Non‑Negotiable)
- AgentState remains the flow object passed across the loop.
- Stop reason must be preserved and emitted in events (monitoring + debugging).
- Avoid new surface area unless it replaces existing complexity.
- Keep behavior compatible with existing tests until a phase explicitly changes semantics.

## Phase 0 — Baseline + Inventory (no behavior changes)
- Map current continuation/stop flow in Core:
  - Where evaluations are created, aggregated, stored, and emitted.
  - Where stop reason is read/used (AgentLoop, AgentState, EventEmitter).
- Document existing event payloads and required fields for monitoring.
- Identify minimal invariants to preserve (e.g., stop reason, resolved by, error handling).

Deliverables:
- Short mapping doc (can live in the same folder) for current flow and event fields.

## Phase 1 — Introduce StopSignal Model (parallel to existing)
- Add minimal StopSignal/StopReason model in Core (modeled after Zero):
  - StopSignal: reason, message, context, source
  - StopReason: enum with deterministic priority
  - StopSignals: list + “pick top priority”
- Add adapters from current ContinuationOutcome to StopSignal (no behavior change yet).
- Ensure AgentEventEmitter can emit stop reason from either model.

Deliverables:
- New Core Stop* classes.
- Adapter helpers (ContinuationOutcome → StopSignal).
- Event emission remains stable.

## Phase 2 — Collapse Evaluations into Signals (behavior preserved)
- Replace ContinuationEvaluation usage in AgentLoop with StopSignal accumulation.
- Remove EvaluationProcessor usage from decision path (keep for compatibility only).
- ExecutionState holds a single pending StopSignal (or none), not a list of evaluations.
- StepExecution records StopSignal (or null) instead of full ContinuationOutcome.
- AgentState exposes `stopSignal()`/`stopReason()` derived from StepExecution.

Deliverables:
- AgentLoop produces StopSignal for the step.
- StepExecution + AgentState simplified.
- EventEmitter reads stop reason from StopSignal.

## Phase 3 — Simplify ExecutionState + AgentState
- Remove `pendingEvaluations` and `ContinuationOutcome` storage entirely.
- Remove `ContinuationEvaluation` + `EvaluationProcessor` if unused.
- Reduce AgentState surface area to:
  - identity + context + execution
  - accessors derived from execution when needed

Deliverables:
- ExecutionState and AgentState trimmed to essentials.
- Tests updated to cover stop reason propagation + events.

## Phase 4 — Optional: Exception‑Based Stop Shortcut
- Introduce `AgentStopException` carrying a StopSignal.
- AgentLoop catches it at a single boundary and records StepExecution with signal.
- Deep code can throw without plumbing evaluation/signal up the stack.

Criteria to proceed:
- Phase 2–3 stable and green tests.
- Clear benefit from deep control‑flow simplification.

## Phase 5 — Cleanup + Documentation
- Remove legacy continuation classes if unused.
- Update internal docs and diagrams for the simplified model.

## Acceptance Criteria (per phase)
- Stop reason and stop source visible in events (AgentEventEmitter).
- StepExecution carries sufficient stop data for debugging.
- AgentLoop path readable end‑to‑end in one sitting.
- Test suite stays green; new tests for stop reason emission added.

## Key Risks + Mitigations
- Risk: Losing detailed stop provenance.
  - Mitigation: StopSignal includes `source` + `context`.
- Risk: Behavior drift in edge‑case stops.
  - Mitigation: Phase 1 adapters, Phase 2 parallel assertions.
- Risk: API churn.
  - Mitigation: Keep legacy accessors until Phase 5 removal.

## Suggested Next Step
- Approve Phase 1 scope and StopSignal API (fields + priority rules) before any code change.
