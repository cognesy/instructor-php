# Step 3: Extract contracts and static composition

## Outcome

Tell has narrow capability contracts and a minimal, Cordis-independent static
composition boundary with factory-backed definitions and pre-boot replacement.

## Size and parallelism

Large and foundational. The composition spike and contract dependency audit
can run in parallel; public kernel API work waits for their decision record.

## Work

- Compare ordinary named factories with a constrained adapter over
  `Utils\Context`, `Layer`, and `Key` in executable fixtures.
- Evaluate duplicate-provider diagnostics, missing requirements, construction
  order, typing, disposal, static analysis, and API surface for both options.
- Inventory existing Tell results and events that expose public Agents and
  Polyglot types; record the narrow allowed dependencies of `tell-contracts`.
- Extract the [capability catalogue](../concept/capability-catalogue.md) without
  duplicating state, status, usage, cancellation, or clock models.
- Add factory-backed definitions, duplicate and missing-capability admission,
  standard profiles, boot, redacted description, and reverse-order disposal.
- Keep replacement before boot and expose purpose-built facades, not arbitrary
  service lookup.
- Decide command descriptor shape through a Symfony-backed fixture.
- Start conformance alongside the first second implementation, not later.
- Enforce contract, kernel, module, and framework dependency direction.

## Acceptance evidence

- The decision record selects the smaller composition model using executable
  evidence and calls out any `Context` merge semantic adapted or rejected.
- Missing requirements are aggregated; duplicate singleton providers fail.
- A two-module fixture constructs from fresh factories and disposes in reverse
  order after success and partial boot failure.
- Contracts compile with only documented public Agents and Polyglot imports.
- A direct unit test uses contracts without booting Cordis or Symfony.
- No product code receives a public service locator.

## Boundary

The kernel performs static composition only. It has no pending modules,
dependency restart, watcher, YAML, health stream, or reconciliation.

## Enables

[Step 4](04-modularize-agent-and-provider-runtime.md) and
[Step 5](05-modularize-workspace-and-conversations.md) can move independently
behind a stable pre-boot graph.
