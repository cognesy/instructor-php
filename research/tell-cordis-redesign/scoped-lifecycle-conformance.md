# Scoped lifecycle conformance

## Applied Cordis capabilities

Tell's resource host delegates dynamic ownership to Cordis rather than copying
its lifecycle engine. The `shell.jobs` root plugin owns one child plugin per
process. Each child scope owns the process group and bounded output readers;
Cordis disposes children in reverse acquisition order before the root manager
is withdrawn.

The public boundary intentionally exposes only Tell values and capabilities:

- `TellResourceHostBuilder` creates a fresh Cordis runtime for every `boot()`;
- `CanManageTellShellJobs` returns immutable snapshots and cursored output;
- default-deny approval and request validation run before a process effect;
- `dispose()` invalidates even a previously retained manager reference;
- `health()` projects label, state, missing contracts, and error class only;
- resource observation uses redacted `tell.resource.event.v1` events; and
- `Tell::open()`, `TellHost::standard()`, CLI, and RPC never boot Cordis.

Integration coverage proves success, execution failure, startup failure with no
partial publication, denial, invalid configuration, timeout, output eviction,
idempotent cancellation, process abandonment, reverse multi-job cleanup, host
isolation, fresh reboot, health projection, observer failure containment, and
the ordinary-host boundary. The clean artifact consumer exercises both the
static SDK host and the resource host across the supported PHP dependency
matrix.

## Deliberately inapplicable capabilities

Provider disappearance/restart, service identity swap, and request-local
interception do not solve a shell-job operation. Approval is a host policy
evaluated before each start, while an admitted process has no replaceable
provider. Restart always means a new request, child scope, process, and job ID.

Live YAML reconciliation is also excluded. Reusing a builder creates a fresh
host after deterministic disposal. This is the safer operational path until a
real retained-worker workflow demonstrates that bounded host rebuild is
insufficient.
