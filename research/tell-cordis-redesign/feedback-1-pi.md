# Feedback 1 — assessment of the Tell/Cordis redesign (pi)

Assessed against actual sources: `packages/tell` (155 files), `~/projects/cordis-php`
(38 files, no tags, not on Packagist), `packages/agents`, `packages/polyglot`,
`packages/instructor`, plus root monorepo metadata and `examples/D11_TellHarness/GAPS.md`.

## Verdict

The redesign is directionally sound and unusually honest about migration risk
(compatibility facades, characterization suites, conformance gates). The
current-state analysis matches the code closely. However:

- one hard prerequisite (tagged `cordis-php/cordis`) does not exist and sits on
  the critical path of steps 3–7;
- two claims about contract purity are contradicted by existing boundary values;
- the largest real migration surface (~20 CLI commands + inline construction of
  `ArenaStore` in 16 classes) is underweighted relative to the attention given
  to kernel mechanics;
- one ordering error exists in the plan (conformance suites are needed at step 5,
  but are scheduled at step 8).

Overall: approve the target architecture; fix the gaps below before starting
step 1.

---

## Claims verified against code (plan is accurate here)

- `Tell.php`, `TellApplication.php`, `TellAgentFactory.php`, `TellRuntime.php`
  really are composition roots hidden inside product classes.
  `TellApplication::__construct()` eagerly instantiates all ~20 commands, each
  taking concrete `TellAgentFactory`. `TellRuntime` duplicates sync/stream logic
  across automatic/stateless/durable/transient/legacy paths as described.
- `new ArenaStore(...)` appears inline in 16 classes (13× in `TellRuntime`
  alone); SDK facades (`TellBranches` 6×) construct workspace services directly.
- Cordis actually provides what the concept docs claim: `ServiceRegistry`
  (conflict detection, `isolate()`, versions, `onChange`),
  `Runtime::settle()` restarting fibers on `dependenciesChanged()`,
  `EffectScope::defer()` reverse-order disposal, EventBus, YAML loader with
  `LoaderLimits`, per-plugin config validation (`ConfigurablePlugin`).
- `CapabilityDiscovery` indeed only instantiates zero-required-arg classes
  (`instantiate()` enforces `getNumberOfRequiredParameters() > 0` → throw).
- PHP floors: root monorepo `^8.3|^8.4|^8.5`; agents/polyglot/instructor `^8.3`;
  tell `^8.4`. The mismatch and CI-lane problem the plan describes is real.

---

## Big gaps

### G1. The Cordis release prerequisite blocks steps 3–7 and has no fallback

`cordis-php/cordis` has no Packagist entry, no git tags, and no release train.
Step 3 declares this a hard prerequisite ("must not vendor or copy"). That makes
a repo outside this monorepo, owned elsewhere, the critical-path dependency of
the whole program — with no stated contingency.

**Fix:** either (a) tag an explicit `0.x.0` now and stabilize API surface via
deprecation rather than pre-release freeze, or (b) add an interim strategy to
step 3: consume Cordis via a pinned commit/path-repository in monorepo CI with a
documented switch-to-tag gate, so kernel extraction can proceed while the
release process catches up. As written, a delay in cordis-php stalls Tell
modularization entirely.

### G2. `tell-contracts` cannot be dependency-free — the boundary values already embed Agents *and* Polyglot types

Concept doc: contracts have "no … Polyglot provider, or concrete Agents
implementation dependency" but "may depend on stable public value types from
`cognesy/agents`". Reality:

- `TellResult` embeds `Cognesy\Agents\Data\AgentState`,
  `Cognesy\Agents\Enums\ExecutionStatus`, **and**
  `Cognesy\Polyglot\Inference\Data\InferenceUsage`.
- `TellProgress` embeds `AgentState` too.
- `CanRunTell` returns `TellResult`, so any implementation of the flagship
  contract drags both `cognesy/agents` and `cognesy/instructor-polyglot` into
  `tell-contracts`.

This is listed as an "open spike", but it is *the* decisive contract-design
question and it invalidates the stated dependency rule. Either:

- define a contracts-level result/state model and adapt Agents state at the
  module edge (costly, risks lossy mapping of `AgentState` semantics), or
- accept `tell-contracts → cognesy/agents (+polyglot)` as an explicit,
  versioned dependency and drop the "no Polyglot" claim.

Decide this in step 2 as gate #1, not as an open decision floating past API
freeze.

### G3. Replacement machinery (leases, quiescent drain, module swap) is net-new, not adaptation

Cordis gives restart-on-dependency-change for fibers mounted inside its own
runtime loop. It has no concept of:

- module-level replacement by ID (unmount old fiber, mount new, verify promised
  capabilities);
- execution leases held by in-flight runs (including streaming generators);
- hot/quiescent/restart-only policy arbitration.

All of that is new concurrency-adjacent logic written in synchronous PHP inside
`tell-kernel`. The concept docs read as if these are thin typed rules over
existing Cordis mechanics; they are not. The abandoned-generator lease problem
is deferred to "open decisions", yet PHP offers only nondeterministic timing
(generator destruction runs `finally`, but GC timing is not guaranteed) — a
worker can stay permanently busy or dispose under an active stream. Elevate to
a designed requirement: e.g. `runStream()` must return a lease-bearing handle
with explicit `close()`/scope binding, tested for abandonment.

### G4. The CLI migration surface is much larger than "inject a booted host"

Step 6 treats CLI modularization as command aggregation. Actual surface:

- `TellApplication` extends Symfony `Application` with a custom routing layer
  (`hoistCommand`, `routingDefinition`, `commandIndex`, `consumesNextToken`) —
  this routing logic itself needs a home (it is not a "command contributor").
- ~20 command classes each take concrete `TellAgentFactory` and several build
  `ArenaStore` directly (`BranchCommand` ×3, `SessionsCommand` ×2, etc.).

Every command signature changes. Add a per-command migration inventory to step 1
(behavior matrix) and decide where the custom routing/plane-map logic lives
(probably `cli.symfony` internals, but say so).

### G5. Configuration aggregation seam is underspecified and conflicts with singleton cardinality

`workspace.filesystem` provides "the branch-local slice of
`CanResolveTellConfiguration` through a configuration aggregator". This implies
partial providers for a capability declared elsewhere as singleton. Undefined:

- who owns aggregation order between workspace slice and `configuration.standard`;
- what happens in the minimal stateless profile (no filesystem workspace module),
  where branch config simply doesn't exist — is the aggregator optional?
- relatedly: user-level policy defaults currently come from
  `TellPaths::configDirectory` (`userPolicyDefaults()` reads a global user dir)
  — a filesystem dependency assigned to **no module** in the catalogue. It will
  silently break the "minimal stateless = no filesystem modules" profile claim.

### G6. Conformance suites are scheduled after they are required

Step 5 requires "an in-memory workspace module … using the same conformance
suite as the filesystem implementation", but reusable conformance suites first
appear in step 8. Move suite authoring into steps 2–5 (per contract, at the
moment the contract gets a second candidate implementation), and let step 8 be
about *external* implementations and packaging proof only.

### G7. Normalized-event attachment lives in two copies inside the runtime today

`TellRuntime::loop()` and the legacy-session anonymous factory each wire
wiretap→normalizer→listener independently. Moving this into
`observation.standard` changes when/how listeners attach (per-request wiretap on
the AgentLoop). The plan must state that event ordering/redaction guarantees are
part of the compatibility contract and get characterization tests *before*
step 4 moves them. Also unresolved: mid-run behavior when an observation module
is replaced (hot policy) — do in-flight wiretaps switch sinks?

---

## Errors / contradictions

1. **Contracts purity claim vs reality** — see G2. Also `TellEvent` wraps raw
   event objects (`object $event`), so `CanObserveTellExecution` signatures must
   either expose generic objects (weak contract) or copy the Agents event
   hierarchy into contracts.
2. **"No second lifecycle engine" vs admission checks** — the kernel adds
   admission validation, replacement policies, and aggregate boot reports on top
   of Cordis states. Fine, but the docs should acknowledge this is a genuine
   (small) second engine for *composition-time* concerns, and pin which state
   transitions belong to whom, or drift will follow.
3. **Health snapshots** — Cordis exposes status listeners and `fibers()`, not a
   health-snapshot API; `TellHost::modules()`/`describe()` is Tell-side
   aggregation work. Listed correctly as kernel duty, but the DX section
   presents `describe()` output as if nearly free.
4. **Step 1 acceptance evidence includes standalone-install tests on 8.4/8.5**
   while the monorepo root still allows 8.3 — the excluded-CI-lane mechanics
   (`composer.json` replace/path interplay, `config.platform.php=8.3`? — not
   set at root) need an actual recipe, not a bullet.
5. **D11 harness reference** — `examples/D11_TellHarness/GAPS.md` tracks
   fyai-derived gaps (persistent shell jobs, MCP lifecycle, pause/resume). The
   redesign never mentions how step 9 (long-lived hosts, subprocess ownership)
   relates to those tracked directions. They overlap heavily (resource
   ownership is exactly what the kernel buys); cross-reference them or the two
   roadmaps will diverge.

## Opportunities

1. **Reuse `AgentBuilder`** (`packages/agents/src/Builder/AgentBuilder`):
   `TellAgentFactory` hand-assembles hooks/budgets/cancellation/decoration.
   `agent.cognesy` could delegate loop assembly to AgentBuilder, shrinking the
   module adapter and inheriting future Agents composition features.
2. **Align observation with existing telemetry**: `packages/telemetry`,
   `packages/metrics`, and `packages/agents/src/Telemetry`
   (`AgentStateTelemetry`, projector pattern) already exist. Define
   `CanObserveTellExecution` as an adapter over those abstractions instead of a
   standalone schema sink, and OpenTelemetry/PSR replacements become existing
   adapters.
3. **`testing.deterministic` should keep the driver seam**: current testability
   works because `TellAgentFactory` accepts a `?CanUseTools $driver`. Keep that
   as a narrow injectable point *inside* the agent module, so scripted responses
   don't require replacing the whole `agent.cognesy` module (which would fake
   policy/tools/events unless deliberately re-wired — the failure mode the docs
   warn about).
4. **Cross-reference `agent-ctrl`** (resident controller, D10): step 9's
   supervisor, bounded cancellation, and job-boundary disposal duplicate
   concepts agent-ctrl already models (`Application/Domain/Infrastructure`). At
   minimum, share vocabulary and avoid two host-supervisor designs.
5. **Protocol ownership**: `Protocol/TellAgentProtocolWriter` (one-run JSONL)
   appears in no module list or capability contract. Name it
   (`protocol.onerun` behind the CLI/testing profiles) and give it a contract,
   or it becomes the next thing constructed ad hoc at the shell edge.
6. **Subagent delegation test**: delegated child runs re-enter workspace
   construction (`TellSubagentExecutor`, `TellDelegationScope` create stores).
   Add an explicit scenario proving child runs share the execution lease and
   branch consistency under the modular split (agent module requiring workspace
   capability is implied but untested anywhere in the plan).
7. **Characterization budget realism**: tell has 46 test files total; the
   behavior matrix in step 1 spans ~20 commands × sync/stream × 5 modes. Size
   step 1 accordingly — it is likely larger than steps 2–3 combined, and the
   plan's uniform step sizing hides that.

## Suggested plan amendments (summary)

| Where | Change |
| --- | --- |
| README / step 3 | Add fallback for missing `cordis-php/cordis` release (pinned-commit consumption with tag-gate), or tag 0.x now |
| Step 2 | Make the contracts↔Agents/Polyglot type question gate #1; rewrite the contracts dependency claim |
| Step 2–5 | Pull conformance-suite authoring forward (G6) |
| Step 1 | Per-command inventory incl. `TellApplication` custom routing; size honestly |
| Concept/lifecycle | Design execution-lease/streaming-close semantics now; specify configuration-aggregator cardinality and user-dir defaults ownership (G5, G7) |
| Concept/modules | Assign `protocol.onerun`, user-level paths, and routing logic to named owners |
| Cross-docs | Link step 9 to `D11_TellHarness/GAPS.md` tracked directions and agent-ctrl |
