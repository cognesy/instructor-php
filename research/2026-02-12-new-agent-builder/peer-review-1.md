# Peer Review 1: AgentBuilder Redesign

## Overall Assessment

The direction is strong: moving policy/config concerns out of `AgentBuilder` internals and into explicit capabilities is a clear improvement.  
The main issues are not architectural intent, but unresolved edge cases and a few contradictions in the docs that would cause implementation drift.

---

## Findings

### 1) Contradiction: "uniform capability API" vs retained builder config methods

In `03-examples.md` the target state says:
- "Uniform API: everything is a capability. Builder has no config-specific methods." (`03-examples.md`, section 7)

But `01-architecture.md` explicitly keeps:
- `withDriver()`
- `withContextCompiler()` / `contextCompiler()`
- `withEvents()`
(`01-architecture.md`, "What stays on AgentBuilder")

This is a direct contradiction. If unresolved, contributors will implement to different targets.

**Recommendation**
- Reframe the claim to: "most runtime policy is capability-driven; builder retains composition primitives."
- Remove "everything is a capability" phrasing from examples.

---

### 2) "AgentLoop is stateless" is misleading in this codebase

`00-overview.md` and `01-architecture.md` repeatedly describe `AgentLoop` as stateless. In practice, loop instances retain runtime dependencies (`tools`, `driver`, `executor`, `events`, `interceptor`) and mutable listener registrations (`wiretap`, `onEvent`).

This wording can mislead design decisions and reviews.

**Recommendation**
- Use: "AgentLoop is domain-policy-neutral" or "configuration-light orchestration engine" instead of "stateless."

---

### 3) Known event wiring footgun is accepted instead of fixed

`04-technical-challenges.md` #2 acknowledges stale event handler risk when `withEvents()` is called after `UseLlmConfig`, then recommends documenting ordering constraints.

For a redesign focused on reducing complexity, preserving this non-commutative behavior is a quality regression.

**Recommendation**
- Make event wiring order-independent.
- Prefer deferred driver construction (factory/deferred provider) or rebinding driver/event dependencies in `build()`.
- Avoid "must call X before Y" API contracts in fluent composition.

---

### 4) Duplicate `UseGuards` semantics are oversimplified ("stricter wins")

`04-technical-challenges.md` #6 and #9 claim duplicate guard installation is fine because stricter wins.

That is only partly true. Duplicate hooks can:
- emit multiple stop signals,
- produce non-obvious precedence for same-reason signals,
- and set a precedent that duplicate capability installs are acceptable globally (dangerous for non-idempotent capabilities like persistence/audit).

**Recommendation**
- Treat guard capabilities as singleton-by-name.
- Add explicit replacement semantics for named hooks or a builder-level policy for duplicate capabilities.
- At minimum, document behavior as "undefined/avoid duplicates," not "safe by default."

---

### 5) Hook/event snapshot behavior is under-specified in target builder

The stripped `AgentBuilder` example removes current helper methods and says build will "snapshot hook stack" (`01-architecture.md`).  
However, it does not define how hook stack is wired with event handler context at build time.

This is critical because `HookStack` event emission depends on event bus wiring, and current implementation performs explicit re-registration during build.

**Recommendation**
- Specify build algorithm precisely:
1. Create fresh `HookStack` bound to resolved `events`.
2. Re-register all hooks into that stack.
3. Use this stack for both loop interceptor and tool executor.
- Add an invariant test for `HookExecuted` event emission after build.

---

### 6) Migration scope is underestimated and lacks compatibility strategy

`04-technical-challenges.md` estimates "15-20 call sites" and suggests deprecate/remove old fluent methods.  
Given widespread usage across tests/examples and downstream users, immediate removal is risky.

**Recommendation**
- Use a staged compatibility plan:
1. Introduce new capabilities.
2. Keep old methods for one minor cycle as thin adapters.
3. Emit deprecation notices with exact replacement snippets.
4. Remove only after migration tooling/docs are complete.

---

### 7) Missing definition of default profile taxonomy (`base` / `standard` / `default`)

`04-technical-challenges.md` proposes `AgentBuilder::standard()`, while the ecosystem already has `AgentLoop::default()`. Without explicit semantics, users will confuse these entry points.

**Recommendation**
- Define and document exact contract:
- `AgentLoop::default()` = minimal executable loop primitives.
- `AgentBuilder::base()` = bare composition surface.
- `AgentBuilder::standard()` = opinionated starter profile (e.g., guards).
- Include a single comparison table in docs.

---

## Improvement Opportunities

1. Introduce a tiny capability installation interface (instead of hard dependency on concrete `AgentBuilder`) to reduce coupling.
2. Add "capability conflict matrix" doc for known interactions (guards, summarization, context config, finish reasons).
3. Add golden tests for:
- capability order effects,
- compiler wrapper order,
- event wiring correctness,
- duplicate capability behavior.
4. Add a lint/static check in tests preventing stale "everything is a capability" claims if builder primitives remain.

---

## Suggested Doc Corrections (Immediate)

1. Update section 7 in `03-examples.md` to remove "everything is a capability."
2. Replace "stateless AgentLoop" phrasing in `00-overview.md` and `01-architecture.md`.
3. In `04-technical-challenges.md` #2, move from "document ordering constraint" to "eliminate ordering constraint."
4. In `04-technical-challenges.md` #6/#9, change "stricter wins" to explicit deterministic policy (replace/dedupe/error).

