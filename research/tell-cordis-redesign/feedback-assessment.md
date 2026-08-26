# Feedback assessment

This document reconciles `feedback-1-opus.md` and `feedback-1-pi.md` against
the working trees inspected on 2026-08-26. Reviewer findings are inputs, not
automatic requirements.

## Current facts that change the original proposal

- Cordis now has local `v1.0.0` and `v1.0.1` tags, CI, PHP `^8.2`, Symfony
  YAML `^7.3 || ^8.0`, restart-scope cleanup, and provider unpublication
  before resource cleanup.
- At review time, `composer show cordis-php/cordis --all` could not find
  Cordis on Packagist. That release gate is now closed: v1.0.1 resolves
  normally and its release/CI/consumer evidence is recorded in
  `cordis-production-gate.md`.
- Agents now exposes `HookTrigger::OnAbandoned` and
  `AgentExecutionAbandoned`; its outer generator `finally` makes stream
  abandonment observable without changing `Tell::runStream()`.
- Published Tell `v2.8.3` still requires PHP `^8.3`. Cordis no longer creates
  any reason to raise Tell to PHP 8.4.
- `Cognesy\Utils\Context` and `Layer` are tested typed-composition
  primitives, but no product package uses them. Their existence warrants a
  spike, not an architectural commitment.
- Tell currently has 19 Symfony command classes and dozens of direct
  workspace/runtime constructions. CLI migration is a major workstream, not a
  final adapter detail.

## Accepted decisions

### Separate composition from lifecycle

Typed contracts, explicit construction, standard profiles, and boot-time
replacement ship before Cordis. The minimal kernel is initially a static
composition root: validate, construct, expose facades, and dispose any
resources it directly owns.

Cordis is introduced only with the first user-visible feature that needs
scoped resource ownership, such as MCP process lifecycle or persistent shell
jobs. This retains Cordis's useful philosophy without putting an optional
lifecycle runtime on the critical path of one-shot Tell usage.

### Restore the PHP 8.3 floor

Tell remains on PHP `^8.3`, matching its published package and current direct
dependencies. Any future floor increase requires a language or dependency
need, compatible split-package CI, and release communication.

### Make contract dependencies honest

The first contracts preserve source compatibility. `TellResult`,
`TellProgress`, and agent construction currently expose public Agents and
Polyglot types, so the contracts boundary may depend on those packages.
Inventing lossy duplicate state models solely for package purity is rejected.

The extraction must keep private implementation types out, record the public
dependencies explicitly, and leave a future breaking decoupling to a separate
versioned decision.

### Use quiescent safe points, not execution leases

There is no runtime replacement in the static-composition phase. A later
supervisor records a reconciliation request and applies it only when no run is
in flight. Streaming generator lifetime counts as in-flight after execution
starts; `OnAbandoned` closes the observable lifecycle when a generator is
destroyed.

A referenced parked generator may keep the host busy legitimately. Waiting is
bounded and reconciliation fails instead of blocking forever. A supervisor may
collect unreachable cycles once before reporting the timeout, but correctness
does not rely on nondeterministic garbage collection. Capability handles must
not escape across a reconciliation boundary.

### Prefer factories for restartable modules

Cordis re-instantiates plugins on restart. Any lifecycle-enabled module
definition therefore stores a factory, not a reused module instance. Static
composition may accept constructed objects internally, but the public builder
uses factories consistently so later lifecycle adoption does not change its
meaning.

### Separate binding revision from API compatibility

Cordis service versions are binding-revision counters for restart detection,
not semantic contract versions. Contract compatibility follows Composer
package semantic versioning and conformance tests. The plan no longer invents
runtime capability-version negotiation.

### Pull learning and conformance forward

- Simplify `TellRuntime` by making synchronous execution drain the streaming
  path before extracting orchestration contracts.
- Spike the smallest composition candidates before choosing between direct
  constructor factories and `Utils\Context`/`Layer`.
- Create a conformance suite when a contract gains its second implementation,
  not in a later packaging phase.
- Characterize normalized event ordering, redaction, and listener attachment
  before moving observation wiring.

### Name neglected ownership boundaries

The revised design assigns owners for custom CLI routing, one-run protocol,
configuration aggregation, user paths and defaults, discovery errors, process
environment access, telemetry adaptation, and deterministic driver injection.
Legacy sessions receive a removal decision before they receive a module.

### Give the migration measurable value

The baseline records CLI startup time, workspace discovery count, agent
definition scans, and Composer extension scans. Standard host reuse should
reduce redundant discovery without regressing one-shot startup materially.

The first lifecycle phase must ship MCP ownership or persistent shell jobs
rather than lifecycle infrastructure alone. It cross-checks D11 gaps and
`agent-ctrl` vocabulary without assuming either implementation can be reused
unchanged.

## Accepted with modification

### Evaluate `Utils\Context`/`Layer`

It is a credible static wiring candidate, but its current right-biased merge
and lack of product adoption are warning signs. The spike must compare it with
ordinary named factories on duplicate detection, diagnostics, static-analysis
quality, startup cost, and whether product code can remain container-free.
There is no third option called a bespoke lifecycle kernel in this phase.

### Cordis dependency fallback

A local path repository is acceptable for a disposable integration spike.
Shipping Tell against a pinned commit or vendored Cordis is not. Production
adoption requires a Packagist-resolvable tagged release and clean-consumer
proof.

### Existing telemetry reuse

The observation module should adapt existing Agents, metrics, and telemetry
abstractions where their semantics fit. The stable Tell boundary remains the
normalized, redacted Tell event envelope; it must not expose a provider or
telemetry backend as the contract.

### Public compatibility scope

The plan protects published behavior, not every untracked experimental class.
Step 1 reconciles the seven previously committed SDK types with the actual
`v2.8.3` package and explicitly classifies newer branch, catalogue, and tool
facades before freezing them.

## Deferred or rejected proposals

- A lease-bearing replacement for `runStream()` is rejected for the initial
  redesign. Agents abandonment observability plus bounded safe-point
  reconciliation avoids a new public lifetime object.
- Cordis-first modularization is rejected. Cordis is valuable only in the
  later scoped-resource profile.
- Automatic selection of `Utils\Context`/`Layer` is rejected until the spike
  explains why the existing abstraction was never adopted.
- A pinned VCS commit as a production dependency is rejected.
- Hot observation replacement is rejected initially. Observation is
  boot-time replaceable; live replacement begins as quiescent unless a
  lossless hand-off is proven.
- Copying Agents state or event hierarchies into Tell contracts for purity is
  rejected.
- Creating `sessions.legacy` by default is deferred until the compatibility
  horizon proves it will outlive the migration.
- A transactional Cordis `mount()` API is not an upstream blocker. Tell can
  preflight its selected graph and aggregate resulting fiber failures; a
  stronger upstream API can be adopted later if it materially simplifies the
  adapter.

## Resulting plan shape

The revised order is:

1. freeze the real compatibility and performance baseline;
2. simplify duplicated runtime paths and remove process-global shortcuts;
3. extract contracts and choose the smallest static composition primitive;
4. modularize agent, provider, and deterministic driver seams;
5. modularize workspace and conversations;
6. modularize configuration, discovery, tools, and observation;
7. migrate CLI routing, protocol, and SDK standard wiring;
8. prove external replacement and package boundaries;
9. deliver one scoped-resource feature and adopt Cordis behind it; and
10. add optional supervised reconciliation only if operations need it.
