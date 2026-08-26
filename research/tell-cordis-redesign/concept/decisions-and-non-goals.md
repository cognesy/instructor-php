# Decisions and non-goals

## Decisions

### Separate composition from lifecycle

Tell first got a minimal static composition boundary. Cordis is used only by
the accepted shell-job resource host. This keeps dependency
inversion useful to ordinary SDK and CLI callers without imposing a worker
runtime on them.

### Retain PHP 8.3

Tell declares PHP `^8.3`. Current Tell dependencies support it, Cordis now
supports `^8.2`, and the redesign uses no PHP 8.4-only feature.

### Consume Cordis normally or not at all

Production Tell may depend on Cordis only after a tagged release resolves from
Packagist and passes a clean-consumer install. Path repositories are permitted
for disposable spikes, not shipped dependency metadata. Tell will not vendor,
copy, or pin an unreleased Cordis commit.

### Prefer explicit PHP wiring

The standard module list is ordinary PHP assembled outside the kernel. YAML is
an optional allowlisted adapter for a future long-lived supervisor.

### Evaluate existing composition primitives

Step 3 compares simple named factories with a narrow adapter over the existing
`Utils\Context` and `Layer`. The choice is evidence-driven; neither becomes a
general service container.

### Use interfaces as capability IDs

Contracts are navigable and statically checkable. Singleton duplicates fail.
Multi-binding is explicit through ordered aggregator contracts.

### Preserve current public domain values

The first contracts may depend on public Agents and Polyglot types already
present in Tell results and events. Compatibility is more valuable than
inventing a second state, usage, or execution-status model.

### Keep canonical workspace behavior cohesive

Canonical storage, refs, branches, locking, history, and compaction remain one
default workspace module until an alternative backend proves a narrower seam.

### Preserve SDK facades

Tell's developer-facing vocabulary delegates to contracts. It does not expose
a container, Cordis context, or raw service registry.

### Make factories restart-safe

Module definitions contain factories rather than reusable instances. Static
replacement is pre-boot. The resource-host builder creates fresh instances
after disposal; any future supervisor must preserve that rule.

### Separate Agents capabilities from Tell modules

Agents capabilities customize an `AgentLoop`. Tell modules own host-level
infrastructure. Composer agent discovery remains an explicit adapter and never
auto-mounts Tell modules.

## Non-goals

### No generic application container

The kernel is not a PSR-11 replacement and product code cannot perform
arbitrary service lookup.

### No class-per-module rewrite

Modularity follows volatility and state ownership, not file count. Canonical
and workspace domain classes stay intact behind a cohesive gateway.

### No second agent lifecycle

Agents hooks and state transitions govern inference and tool use. The Cordis
adapter governs shell-resource lifecycle; the event models remain explicitly
separate.

### No YAML-first SDK

Simple PHP usage requires no composition file, watcher, or schema tooling.

### No untrusted in-process plugins

Explicit module selection is a trust decision. Capability isolation is not a
security sandbox for hostile PHP.

### No live workspace backend swap

That feature needs a separate drain, migration, verification, and cutover
protocol.

### No premature package explosion

Modules are source-separated first. A package needs independent use,
dependencies, conformance, and clean-consumer proof.

### No semantic capability revisions in the runtime

Runtime binding revisions identify restarts. Composer versions and conformance
tests govern interface compatibility.

### No execution-lease abstraction

If supervised reconciliation is delivered, admission gating and an in-flight
counter define the safe point. Agents abandonment closes streaming execution.

## Open implementation decisions

Executable spikes must resolve:

- named factories versus a constrained `Utils\Context` and `Layer` adapter;
- exact Agents and Polyglot public types admitted into `tell-contracts`;
- framework-neutral command descriptors versus Symfony command contributions;
- the legacy session retirement horizon; and
- whether normalized observation replacement is ever safe before host restart.

The first scoped-resource feature is no longer open: Tell will deliver
host-scoped persistent shell jobs. See
[Persistent shell jobs](persistent-shell-jobs.md) for the accepted boundary;
MCP lifecycle remains deferred.
