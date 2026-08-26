# Lifecycle and state

## Ordinary host lifecycle

The first Tell host has three states:

```text
unbooted -> active -> disposed
```

The selected graph is immutable after boot. The host constructs modules from
factories, owns any cleanup they return, and disposes in reverse construction
order. Boot failure attempts every applicable cleanup and reports aggregate
failures. CLI and SDK users do not need a watcher, supervisor, or runtime
registry.

## Resource ownership forces the next stage

Host-scoped persistent shell jobs are the selected first resource feature.
They address a concrete D11/fyai gap: start bounded work without blocking,
inspect its output later in the same host, and cancel it deterministically.
They require explicit ownership of processes, readers, cancellation,
approvals, output bounds, and cleanup. The full product contract and executable
scenarios are specified in [Persistent shell jobs](persistent-shell-jobs.md).
MCP lifecycle remains a later candidate because it adds transport, protocol,
discovery, schema, and authentication concerns beyond process ownership.

For this feature, the opt-in Cordis-backed host adds:

- pending, loading, active, unloading, failed, and disposed states;
- one effect scope per module instance;
- reverse-order cleanup of services, listeners, processes, and clients;
- one fresh isolated runtime per builder boot; and
- redacted health and lifecycle events.

These mechanics do not move into the static kernel.

Dependency restart, provider swap, request-local interception, and live
reconciliation are Cordis capabilities but are not used by shell jobs. A job
has no replaceable provider dependency, and its approval is evaluated once
before effects. Reusing a builder after disposal boots a fresh host; it never
restarts a disposed process or mutates an active host.

## Lessons retained from Cordis

The Cordis examples provide useful rules for the resource-host phase:

- lifecycle ownership groups a service, listeners, children, and cleanup;
- dependency identity changes restart affected consumers only;
- stable declarative identity can reconcile one changed leaf;
- Agents hooks remain the inference lifecycle, while Cordis events describe
  module lifecycle;
- health exposes state and missing requirements without raw objects;
- isolation hides credentials and privileged tools from child realms;
- validation precedes HTTP clients, processes, locks, and listeners;
- provider swap preserves the capability contract; and
- request overrides use immutable local context, not provider mutation.

## State ownership

The static kernel owns only the admitted graph, constructed bindings, cleanup,
and host state. It owns no prompts, branches, credentials, models, or records.

The filesystem workspace owns canonical objects, refs, locks, branch
configuration, and migration metadata. The agent module owns ephemeral loop
construction. The model module owns immutable resolved connection data. The
secret module resolves approved sources without exposing values in diagnostics.

The shell-job resource host additionally owns resource-module states, scopes,
health, processes, bounded output, and cancellation. It owns neither runtime
reconciliation nor restart revisions.

## Safe replacement without leases

Static hosts replace modules only before boot. There is no replacement race to
solve in the first phase.

The current decision is not to implement supervised reconciliation; bounded
host rebuild covers all demonstrated workflows. If a revisit trigger is met,
quiescent replacement must use a simple safe-point protocol:

1. request reconciliation;
2. stop admitting new runs;
3. wait until the in-flight run count reaches zero;
4. reconcile factory-backed resource modules; and
5. admit new runs.

Synchronous runs decrement in `finally`. A streaming generator remains
in-flight from first execution until exhaustion, failure, cancellation, or
abandonment. Agents now publishes `OnAbandoned`, so the lifecycle bridge can
close the count on generator abandonment. A deliberately retained live
generator legitimately blocks reconciliation.

Waiting is bounded and fails with an actionable busy-host result. The host may
collect cycles once before timeout, but correctness must not depend on garbage
collection. Capability handles must not escape the reconciliation boundary.

There is no separate execution-lease abstraction.

## Replacement policy

The resource-host phase may classify changes as:

- quiescent, after no runs are in flight; or
- restart-only, requiring construction of a new host.

Hot observation replacement is not promised initially. Event ordering,
redaction, sink handoff, and loss behavior must first be characterized.
Filesystem workspace replacement remains restart-only until a separate data
migration and cutover protocol exists.

## Failure semantics

- Missing mandatory capabilities fail boot with one aggregate report.
- A failed module publishes no partial capability set.
- Cleanup attempts continue after an individual failure.
- A failed optional reconciliation preserves the last known-good graph when
  the operation can be transactional; otherwise the host refuses new work.
- Health, errors, and events never contain resolved secrets.
