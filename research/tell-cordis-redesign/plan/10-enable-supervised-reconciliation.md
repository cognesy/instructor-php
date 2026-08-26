# Step 10: Enable supervised reconciliation

## Current disposition

No-go on 2026-08-27. The Step 9 shell-job host has no replaceable provider,
live configuration source, or demonstrated workflow that host rebuild cannot
satisfy. See the evidence and revisit triggers in
[Supervised reconciliation decision](../reconciliation-decision.md). The work
below remains a conditional design, not a current API or implementation
commitment.

## Outcome

Long-lived resource hosts may opt into bounded, validated configuration
reconciliation when actual operations require it. Short-lived SDK and CLI use
stay static and synchronous.

## Size and parallelism

Large and optional. Do not schedule until Step 9 telemetry or user workflows
demonstrate a need that rebuilding a host at a request boundary cannot satisfy.

## Work

- Add a supervisor outside the static kernel; polling, file watching, and
  daemon policy remain host concerns.
- Accept declarative YAML only through an allowlisted registry mapping stable
  names to typed factories.
- Validate the complete candidate before changing the healthy graph.
- Implement a safe-point protocol: request reconciliation, stop admitting new
  runs, wait for zero in-flight executions, reconcile, then resume admission.
- Count streaming work until exhaustion, failure, cancellation, or Agents
  `OnAbandoned`; do not introduce a separate execution-lease abstraction.
- Use a bounded wait with an actionable busy result. Cycle collection may run
  once before timeout but is not a correctness mechanism.
- Keep capability handles inside the reconciliation boundary and reconstruct
  modules from factories.
- Begin with quiescent or restart-only changes. Do not claim hot observation or
  workspace replacement.
- Expose redacted state, missing requirements, restart revisions, in-flight
  count, last outcome, and disposal failures.

## Acceptance evidence

- A long-lived fixture changes one allowed leaf while unrelated scopes remain
  active.
- Invalid configuration preserves the last known-good graph.
- A retained streaming generator causes bounded busy failure; exhausting or
  abandoning it permits reconciliation.
- Restart creates a fresh module instance and fully disposes the previous one.
- Shutdown cancels work within a configured deadline and attempts all cleanup.
- YAML cannot name arbitrary PHP classes, callables, or executable files.

## Boundary

Reconciliation is optional operational infrastructure. Explicit PHP factories
remain the primary programming model, and ordinary Tell users never need the
supervisor.
