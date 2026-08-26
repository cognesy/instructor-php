# Feedback 1: assessment of the Tell/Cordis redesign

Reviewer pass over `research/tell-cordis-redesign/` confronted with
`packages/tell`, `packages/agents`, `packages/polyglot`, `packages/utils`,
and `~/projects/cordis-php`. Findings are ordered by how much they should
change the plan. Every claim below is backed by a file reference or a
reproduction recorded in the appendix.

## Verdict

The **diagnosis is right and unusually well written**. The **prescription is
wrong in its first move**: the plan takes an untagged, three-commit, CI-less
external runtime as the load-bearing foundation for Tell's entire composition
model, in order to obtain lifecycle features that Tell's dominant use case (a
one-shot CLI process) does not use, while the monorepo already contains an
unused, tested, typed composition mechanism that delivers most of the target
dependency direction with no new dependency and no PHP floor bump.

The plan is also nine steps of pure refactoring with no user-visible payoff,
in a package whose own gap analysis names two features (persistent shell jobs,
MCP lifecycle) that need exactly the resource-ownership semantics Cordis
provides. That mismatch is the single biggest structural problem: the
migration has no forcing function and no way to prove its own value until
step 8.

Recommended change: **split the plan in two.** Land contracts and an explicit
composition root without Cordis (high value, low risk, no new dependency).
Adopt Cordis later, gated on it becoming a real dependency, and justify it
with the first feature that actually needs scoped disposal.

## Update 2026-08-26: three findings resolved upstream during review

Cordis was patched while this review was being written (working tree at
`f486728 "Prepare Cordis PHP 1.0.0 release"` plus uncommitted changes), in
response to a parallel exchange with its maintainer. Re-verified against the
current tree:

<!-- markdownlint-disable MD013 -->

| Finding | Status | Evidence |
| --- | --- | --- |
| E2 restart leak | **fixed** | `EffectScope::child()` now captures the parent release handle; `defer()` unsets its record. Reproduction re-run: 500 restarts leave the root scope at **2** records, was 502. |
| E3 `symfony/yaml` pin | **fixed** | now `^7.3 \|\| ^8.0`; a CI matrix `php 8.2–8.5 × symfony ^7.3/^8.0 × stable/lowest` was added. |
| E4 PHP 8.4 floor | **conclusion strengthened** | Cordis dropped to `php: ^8.2`. Cordis was the *only* driver for Tell's `^8.4`. The bump is now unjustified outright — revert it, rather than re-deriving it. |
| E1 maturity | **materially improved** | CI now exists; `EffectScopeTest`/`FiberLifecycleTest` extended; 1.0.0 release in preparation. Still untagged at time of writing. |
| G3 leases | **resolved by design decision** | see below. |
| N1 disposal ordering | **new; open upstream, not a Tell blocker** | acknowledged by the maintainer as a lifecycle-order defect to fix before 1.0; deliberately not taken in a review-only turn. See below. |

<!-- markdownlint-enable MD013 -->

### G3 resolution: quiescence, not leases

The lease question was settled directly with the Cordis maintainer. Outcome:
**no enforceable lease is needed, and none should be built.** Tell's contract
is that it invokes provider release/reload only at its own safe point, so
Cordis needs no mutation deferral and no new API. `plan/04`'s acceptance
("model swaps do not split one run across providers") is a statement that
reconciliation must *not* happen mid-run — which is quiescence, not a lease.

Tell already runs this exact discipline for cancellation and should reuse it:
`TellSignalCancellationSource.php:27-31` enables `pcntl_async_signals(true)`
but its handler only sets a flag; the effect is applied at a cooperative safe
point (`CooperativeCancellationHook` on `BeforeExecution`/`BeforeStep`,
`TellAgentFactory.php:404-408`). Reconciliation should be flag + safe point,
identically. Two constraints must be written into `plan/09`:

- the safe point is "no run in flight", and that must cover **generator
  lifetime**, not call return — `Tell::runStream()` hands a `Generator` to the
  caller, so a host that reconciles between top-level invocations still
  interleaves with a slowly-driven or abandoned generator, which needs a
  bound (measured semantics and the three constraints they impose are in the
  next section);
- a run re-enters capability resolution — `TellSubagentExecutor.php:43` calls
  `agents->build(...)` from inside a parent run's tool call — so any
  run-scoped lease would have had to be reentrant, i.e. a depth counter.

`plan/04`'s acceptance criterion should be rewritten from "kernel tests prove
model swaps do not split a run" (which implies enforcement machinery) to
"reconciliation is invoked only at a safe point, and abandoning a stream
generator cannot postpone that safe point indefinitely".

### Quiescence: measured semantics, and three constraints it imposes

The agreed correction to the plan is: drop leases; make quiescent replacement
a **Tell supervisor invariant** — async sources set only a requested flag, and
`dispose`/`update`/`mount`/reconcile runs synchronously only when no run is
alive; count streaming `Generator` lifetime, *including abandonment*, as
in-flight; and forbid capability handles escaping across that boundary.

"Generator lifetime including abandonment" is the clause with teeth, so its
semantics were measured rather than assumed (appendix B4). Modelling the
`TellRuntime` stream generator wrapping `AgentLoop::iterate()`, with an
enter/leave counter:

| Case | Result |
| --- | --- |
| A. fully drained | quiescent |
| B. abandoned mid-stream | quiescent immediately on `unset` |
| C. parked, still referenced | **never** quiescent |
| D. created, never advanced | quiescent |
| E. held by a reference cycle | **not** quiescent until `gc_collect_cycles()` |

Three consequences follow, and all three are Tell's problem, not Cordis's.

**1. The accounting must live inside the generator body, in `try`/`finally`.**
PHP unwinds a suspended generator on destruction and runs its `finally`
blocks, and this cascades into the inner `AgentLoop::iterate()` generator —
case B shows both `leave` records firing, innermost first. Accounting wrapped
around the *call site* instead would leak an in-flight count forever on
abandonment. This is a real change, not a description of today: **no stream
generator in `TellRuntime` has a `finally` block** (`grep -n finally` over
`packages/tell/src` returns ten hits, none of them in a generator).

**2. `gc_collect_cycles()` before treating non-quiescence as terminal.** Case
E is the liveness hazard. A generator kept alive only by a reference cycle is
unreachable but not destructed, so the supervisor sees a phantom in-flight run
and reconciliation waits on a run that no longer exists.

**3. The wait must be bounded, and expiry must fail the reconciliation rather
than block.** Case C is a legitimate embedder state: a host that holds a
`Tell::runStream()` generator and drives it slowly, or not at all, is doing
nothing wrong. Counting it as in-flight is correct; blocking on it forever is
not.

Case D is the reassuring one: returning a generator from `Tell::runStream()`
does not itself block reconciliation, because an unstarted generator has no
frame and has run no setup.

### N2 (new, fixed): the agreed invariant was not implementable as Tell stood

The consensus says Tell needs no Cordis API change. That is correct, and it
was the question the review set out to answer — but it quietly assumes Tell
*can* detect a live run. It cannot, and the cost lands in a third package that
neither side of that discussion was looking at.

> **Status: fixed in `cognesy/agents`.** `AgentLoop::iterate()` now carries the
> outer `try`/`finally` described below, with a new
> `HookTrigger::OnAbandoned` and an `AgentExecutionAbandoned` event. Seven
> regression tests cover it; agents 766 pass, tell 245 pass, phpstan and psalm
> clean, pint clean. The diagnosis below is kept as written, because it is why
> the change exists.

Tell's only lifecycle observation mechanism is the hook stack, and hooks
cannot see abandonment. `AgentLoop::iterate()` has **no outer `try`/`finally`**
(`AgentLoop.php:112-162`). Its only `finally` is the per-step one at
`:141-144`, and the `yield` at `:152` sits *outside* it, so a generator
suspended at that yield unwinds through no `finally` at all. `AfterExecution`
is dispatched at `:158`, reachable only if the generator runs to completion.

Measured against the real `AgentLoop` with `FakeAgentDriver` (appendix B5), a
`CanInterceptAgentLifecycle` probe incrementing on `BeforeExecution` and
decrementing on `AfterExecution`:

| Case | Hooks fired | Counter after |
| --- | --- | --- |
| drained (5 yields) | `BeforeExecution`, `AfterExecution` | 0 — quiescent |
| abandoned after 2 steps | `BeforeExecution` only | **1 — never quiescent** |

This is a liveness failure, not a bookkeeping wart. A supervisor built on
hooks would see a phantom run forever and **deadlock on the first abandoned
stream** — never reconciling again for the life of the process.

Two ways out.

**(a) One outer `try`/`finally` in `AgentLoop::iterate()`**, in the `agents`
package. `iterate()` is the single chokepoint every Tell run path bottoms out
in — `execute()` is defined as a drain of it (`AgentLoop.php:104-110`) — so
one change covers every call site, and it is the natural home for the
guarantee.

**(b) A Tell-side wrapper generator per run-entry site.** Verified to work
(appendix B5, case 3): wrapping `$loop->iterate(...)` in a Tell generator that
increments on entry and decrements in `finally` returns the counter to 0 on
abandonment, because destruction cascades inward. No upstream change — but
`grep -n -- '->iterate('` over `packages/tell/src` returns **11 call sites**
across four files, plus three `->execute(` sites, and every generator path
reachable from `Tell::runStream()` needs the wrapper. An invariant maintained
by convention in eleven places is an invariant that will break.

(a) is the right answer, and it means the redesign has an **upstream
prerequisite in `agents`, not only in Cordis** — which belongs in O5 alongside
the Cordis prerequisites, and is a second reason the plan's first move should
not be adopting Cordis.

#### Why (b) is not merely tedious but insufficient

A Tell-side wrapper can decrement Tell's own counter. It **cannot make
`AfterExecution` fire**, because that dispatch lives inside `AgentLoop`. So
(b) fixes the one symptom that prompted this review and leaves every other
consumer of `iterate()` broken in the same way — any hook doing terminal work
(trace flush, cost accounting, execution history) silently does not run when a
stream is abandoned.

This is not hypothetical and not Cordis-specific. It is a live defect today:
`WorkspaceTurnRunner.php:57-58` calls `assertPublishable()` and `publish()`
*after* the `foreach`, so abandoning a `Tell::runStream()` generator silently
skips arena publication. Whether that is the desired policy is arguable —
"abandon means do not publish" is defensible — but at present it is neither
stated nor tested, and it is decided by generator mechanics rather than by
design.

#### The patch shape, and its two traps

Verified (appendix B6). Trap one: **`yield` inside `finally` is fatal on
force-close** — `Error: Cannot yield from finally in a force-closed
generator`. Since `AgentLoop.php:158-161` currently dispatches
`onAfterExecution()` and *then* conditionally yields, the tail cannot be moved
into a `finally` wholesale. The dispatch goes in the `finally`; the `yield`
stays on the normal path. Trap two: **the terminal dispatch needs an
idempotence guard**, or a generator abandoned while suspended at the final
yield — already settled, not yet resumed — dispatches twice.

```php
$settled = false;
$settle = function (string $why) use (&$settled): void {
    if ($settled) { return; }
    $settled = true;
    /* dispatch; MUST NOT yield */
};
try {
    while (true) { /* ... */ yield $state; }
    $settle('completed');
    if (/* ... */) { yield $finalState; }   // normal path only
} finally {
    $settle('abandoned');
}
```

Measured across four paths: drained (1 dispatch, final yield intact),
abandoned mid-stream (1 dispatch, clean destruction), abandoned at the final
yield (1 dispatch, guard holds), and consumer `->throw()` (1 dispatch).

#### One caveat on the trigger

The abandon path should **not** reuse `HookTrigger::AfterExecution`. That
trigger has a live consumer — `ExecutionHistoryHook` via
`UseExecutionHistory.php:41` records an `ExecutionSummary` into the
`ExecutionStore` — which would begin recording summaries for torn-down runs
whose state is still `InProgress`. A distinct terminal trigger is additive and
cannot silently change the behaviour of hooks that exist today.

#### What the fix does and does not settle

Implemented as `HookTrigger::OnAbandoned`, whose `mutableFields()` is
`['metadata']` only — a teardown hook can annotate but cannot mutate state,
because the state it returns is discarded. The dispatch is wrapped so a
throwing teardown hook cannot escape: an exception raised while a generator is
force-closed would otherwise surface at whatever statement happened to drop
the last reference. The error is not swallowed; it is reported on the
`AgentExecutionAbandoned` event's `teardownError`.

This makes abandonment **observable**, which is the precondition the
quiescence contract needs. It does not by itself build the supervisor, and it
does not decide the open policy question in Tell: `WorkspaceTurnRunner`'s
publication still sits after the `foreach`, so whether abandoning a run
publishes remains decided by generator mechanics rather than by design. That
is now a choice Tell can express, rather than a behaviour it inherits.

### N1 (new, open): disposal runs cleanup before binding removal

Unchanged by the upstream fixes, because it lives in `Fiber`/`ServiceRegistry`
rather than `EffectScope`. Within a single `$fiber->dispose()`:

- `ServiceRegistry.php:65` registers the binding-removal record during
  `apply()`;
- `Fiber.php:129-132` defers the plugin cleanup closure *after* `apply()`
  returns;
- `EffectScope.php:114` disposes `array_reverse($records)`.

So plugin cleanup runs **first** and binding removal second. Instrumented
(appendix B3):

```text
[provider cleanup] BEFORE close: consumer state=active, binding present=true
[provider] gateway->close() ran
[dispatch] Removed('gw') fired -> consumer pending, gateway closed
held reference threw: USE AFTER CLOSE
```

The hazard window is therefore *resolvable and closed*, not "missing or
closed": `has()` returns true, `get()` returns a live handle, the consumer is
still `Active`, and the object behind it is already shut. A consumer guarding
with `has()` is not protected. `Fiber::restart()` and `update()` share the
path via `unloadToPending()` (`Fiber.php:250`).

This does not affect Tell under the safe-point contract above, because nothing
dereferences a capability at that instant. It is recorded because it makes the
invariant one embedders must know rather than one Cordis enforces.

The proposed fix — **two-phase scope disposal**, removing binding records
first (dispatch and settle, so dependents unwire) and running the remaining
effect records second — is still worth doing, and the maintainer agrees it
should be tracked as a Cordis lifecycle-order defect before 1.0. It is not
being taken in a review-only turn, so **treat current Cordis as
cleanup-before-unpublish**.

One correction to an earlier version of this document. It previously said the
`plan/09` wording could be relaxed if the fix landed. That was wrong, and the
maintainer's objection is decisive: *unpublishing first cannot invalidate an
already-held reference*. Two-phase disposal narrows the window for consumers
that re-resolve, but a consumer that captured a handle earlier is unaffected
by ordering. The no-escaped-handle rule is therefore **unconditional**, not
contingent on the upstream fix, and `plan/09` carries the stronger sentence
either way.

## What the plan gets right

These are load-bearing and should survive any revision:

- The current-state diagnosis is accurate. `TellAgentFactory` and
  `TellRuntime` are composition roots hidden inside product classes, and
  `TellBranches`, `TellConversation`, `TellRef`, `TellBranchConfiguration`
  plus twelve command classes construct `ArenaStore`, `BranchResolver`, and
  `BranchConfigStore` directly. Verified by grep; see appendix A1.
- Keeping the canonical workspace cohesive rather than shattering it into
  storage micro-modules (`concept/capability-catalogue.md`,
  `plan/05` boundary note) is the correct call and is the plan's best
  judgement.
- Interfaces as capability identity, aggregator contracts for multi-binding,
  and refusing a public service locator are all right.
- Separating Agents capability discovery from host module discovery, and
  refusing to auto-mount host modules from Composer metadata, is right and
  is a genuine security boundary.
- The compatibility, conformance-suite, and packed-consumer-proof discipline
  in `concept/compatibility-testing-and-operations.md` is better than most
  migration plans get.

## Errors

Ordered by severity. E1–E3 are blocking as written.

### E1. Cordis is not yet a dependency-grade artifact

> **Status: materially improved during review.** CI added, tests extended,
> 1.0.0 in preparation, still untagged. See the update section above.

`concept/kernel-and-contracts.md` justifies the dependency with: "The local
Cordis implementation already tests scoped disposal, dependency restart,
configuration validation, health, isolation, interception, and
reconciliation."

Actual state of `~/projects/cordis-php`:

- three commits (`40ebc0c`, `49caf45`, `aa414ca "Initial release"`);
- **no git tags**;
- **no CI workflow at all** (no `.github/`);
- 835 lines of tests across 7 files;
- `tests/Runtime/FiberLifecycleTest.php` is 138 lines / 6 cases;
  `tests/Runtime/EffectScopeTest.php` is 4 cases.

There is no health test, no interception test, and no reconciliation test.
Those seven capabilities are demonstrated by `examples/01..09`, which are
narrative demo scripts run by `run-all.php` — not an assertion suite. The
plan's Step 3 prerequisite ("publish and tag a version") treats this as a
mechanical packaging step. It is a maturity question, and it should be
written as one: tag, CI matrix, a real test suite, and a second consumer.

### E2. Verified defect in Cordis that breaks the plan's headline use case

> **Status: FIXED upstream and re-verified.** Retained for the record.

`EffectScope::child()` (`src/Runtime/EffectScope.php`) defers a disposal
record onto the parent scope and discards the returned handle.
`Fiber::beginAttempt()` creates a fresh child context — and therefore a fresh
child scope — on **every** restart. `EffectScope::dispose()` marks
`EffectRecord::$active = false` but never prunes `$this->records`.

Consequence: each fiber restart permanently adds one dead record to the
parent scope. Reproduced (appendix B1): 500 consumer restarts grow the root
scope from **2 records to 502**, unbounded.

This is precisely the long-lived-worker reconciliation path that
`plan/09-enable-long-lived-reconciliation.md` exists to enable, and precisely
the "rotating model endpoint" behaviour cited in
`concept/lifecycle-and-state.md` as the reason to adopt Cordis. The feature
the plan is buying is the feature that currently leaks.

### E3. Cordis pins `symfony/yaml: ^7.3`, which excludes Symfony 8

> **Status: FIXED upstream** — now `^7.3 || ^8.0`. Retained for the record.

Root `composer.json` requires `symfony/yaml: ^7.3 || ^8.0`, and
`.github/workflows/php.yml` runs a `symfony: ['^7.3', '^8.0']` matrix.
Cordis's `composer.json` requires `symfony/yaml: ^7.3` only.

Adopting Cordis therefore either breaks the Symfony 8 lane or silently pins
`symfony/yaml` back to 7.x for every Tell consumer. The plan never mentions
that Cordis has a transitive dependency at all, and there is an in-flight
`research/2026-03-29-symfony-8-compat-local-ci-plan` that this collides with.
Widening Cordis to `^7.3 || ^8.0` is a hard prerequisite.

### E4. The PHP 8.4 floor is neither settled nor currently justified

> **Status: strengthened.** Cordis now declares `php: ^8.2`, so the sole
> driver for Tell's `^8.4` is gone. Revert the bump.

`concept/decisions-and-non-goals.md` and `plan/01` treat PHP `^8.4` as the
baseline. Reality:

- The bump is an **uncommitted working-tree edit** to
  `packages/tell/composer.json` (`^8.3` → `^8.4`), made in service of this
  plan. It is not a fact about the repo.
- Every other package in `packages/*` is `^8.3`. Tell's own dependency
  `cognesy/agents: ^2.8.3` is `^8.3`.
- No PHP 8.4-only syntax is in use in `packages/tell/src`. The **only**
  driver for the floor increase is Cordis.
- `plan/01` says to "decide how the repository-wide PHP 8.3 CI lane excludes
  Tell". The real blocker is `.github/workflows/split.yml`, whose package
  test matrix is `php: ['8.2', '8.3', '8.4']` and which runs
  `composer update` over `packages/*` in a loop. Two lanes break, not one,
  and **8.2 is not mentioned anywhere in the plan**.

Raising the floor of the flagship CLI package, ahead of the rest of the
monorepo, to satisfy an untagged dependency, is a large consumer-facing cost
for zero language benefit. It should be re-derived, not assumed.

### E5. `tell-contracts` cannot be Agents-free

`concept/architecture-boundaries.md` requires that `tell-contracts` has "no
Cordis, Symfony Console, filesystem, Polyglot provider, or concrete Agents
implementation dependency", and
`concept/decisions-and-non-goals.md` files it as an open question.

It is not open. `TellResult::state(): AgentState` is public and shipped
(`packages/tell/src/TellResult.php:28`), and `AgentState` is a
`final readonly class` in `cognesy/agents`. `CanRunTell` returns
`TellResult`, so contracts transitively expose a concrete Agents type.
`CanBuildTellAgent` returns `AgentLoop`, a concrete class with ~30 Agents
imports. Contracts will depend on `cognesy/agents` in full, or `TellResult`
takes a breaking change.

Decide it in Step 2 and state it plainly, rather than discovering it during
extraction.

### E6. The proposed composition API conflicts with Cordis restart semantics

`concept/modules-and-wiring.md` shows
`TellComposition::empty()->with(new StandardSecretsModule($paths))` — module
**instances**.

Cordis calls `PluginDefinition::instantiate()` on every `attemptStart()`,
including every restart (`src/Runtime/Fiber.php`). An instance-based
composition means a restarted module re-runs `apply()` on the *same object*,
carrying whatever state the previous incarnation left. That contradicts
`concept/lifecycle-and-state.md`'s "a dependent module restarts only after
the old scope is fully disposed" reading.

Modules must be registered as factories (`Closure(): TellModule`), not
instances, or restart must be documented as apply-only. Pick one; the current
docs assume both.

### E7. "Capability version" means two things, and neither exists

`concept/lifecycle-and-state.md` lists "capability binding identity and
version" as kernel-owned state, and
`plan/08` asks to test "incompatible capability versions".

Cordis's `ServiceRegistry::version()` is a monotonic global counter used
solely to detect that a binding *identity* changed, so dependents restart. It
carries no semantic meaning. There is no interface-compatibility versioning
mechanism designed anywhere in the plan, and Step 8's line item has nothing
to test against. Either design contract versioning in Step 2 (where it
belongs — it is the whole point of extracting contracts) or delete the line.

## Gaps

### G1. No alternative was evaluated; the closest one is already in the repo

`packages/utils/src/Context/` contains `Context`, `Layer`, and `Key`: an
immutable, strongly typed, `class-string`-keyed wiring mechanism with
`provides`, `providesFrom`, `dependsOn`, `merge`, qualified keys, generic
annotations, runtime `instanceof` validation, a `Result`-returning `tryGet`,
PSR-11 adapters, and a design document (`LAYER_CONTEXT.md`) that opens with
"keeps core code container-free and explicit". It is tested
(`packages/utils/tests/Unit/{Layer,Context,KeyedBindings}Test.php`).

This is the plan's target dependency direction, minus lifecycle. The plan
does not mention it once. That is a serious omission in a document whose
central decision is "adopt an external runtime".

There is a second, sharper signal: **`Context`/`Layer` is used by no product
code in the monorepo.** A previous attempt at exactly this idea was built,
tested, documented, and never adopted. A plan to redo it at ten times the
scope owes an explanation of why that happened and what is different now.
Without one, the most likely outcome of this plan is a second unused
abstraction — this time with an external dependency and a raised PHP floor
attached.

Minimum required: a section comparing (a) `Utils\Context`/`Layer`,
(b) a bespoke ~300-line Tell kernel, (c) Cordis, against the capabilities the
plan actually needs at each step. My reading is that (a) covers Steps 2, 4,
5, 6, and 7 entirely, and only Step 9 needs (c).

### G2. The plan has no forcing function and no user-visible payoff

Nine steps, roughly the whole of `packages/tell` (155 files, 15k LOC) plus a
new contracts layer, a kernel, twelve modules, conformance suites, and
architecture gates — and at the end, users can do exactly what they can do
today. The only new capability is module replacement, whose demand is
asserted rather than evidenced.

Meanwhile `examples/D11_TellHarness/GAPS.md` — Tell's own gap analysis —
names the two highest-value remaining features as **persistent shell jobs**
and **MCP lifecycle**, and says of both: *"Both require resource ownership,
cleanup, and approval policy—not just another SDK method."*

That is the argument for Cordis, and it is not in this plan. As written the
two documents are disconnected: the redesign never cites GAPS.md, and GAPS.md
never anticipates a kernel.

Re-sequence so that the first real consumer of scoped disposal is MCP process
lifecycle or a persistent shell job. That gives the kernel a genuine
requirement to be validated against, gives the migration a shippable outcome,
and tests the disposal path against a resource that actually leaks if you get
it wrong.

### G3. Execution leases are unenforceable as designed

> **Status: resolved.** Settled with the Cordis maintainer — quiescence and
> safe-point reconciliation, no leases, no new Cordis API. See the update
> section above.

`concept/lifecycle-and-state.md` makes leases the mechanism for quiescent
replacement, and `plan/04` acceptance says "kernel tests prove that model
swaps do not split one run across providers".

Cordis provides no hook for this. `Runtime::requestSettle()` is invoked
synchronously from the `ServiceRegistry` change listener, and
`Runtime::settle()` restarts any fiber whose dependency version moved, with
no consultation of anything. Verified (appendix B2): disposing a provider
takes its consumer to `pending` **immediately**, and remounting restarts it
immediately.

Worse, `Fiber::dispose()`, `Fiber::restart()`, `Fiber::update()`, and
`Context::provide()` are all public on objects modules are handed. A lease
can only be enforced if the Tell adapter is the sole path to mutation, which
means wrapping or hiding Cordis's `Context` from modules — directly at odds
with "a thin Tell adapter" and with the `TellModuleContext` sketch, which is
a pass-through.

Either the adapter owns and hides `Context` (and is therefore not thin), or
leases are advisory and `plan/04`'s acceptance criterion cannot be met.
Resolve this before Step 3, because it determines the kernel's shape.

### G4. Step 6 is badly undersized, and no step carries an effort estimate

`plan/06` is 41 lines and asks for five modules
(`tools.standard`, `observation.standard`, `configuration.standard`,
`extensions.composer`, `cli.symfony`) **plus** the rewrite of twenty Symfony
commands, twelve of which construct workspace internals directly, **plus**
ordered contributor aggregates with duplicate and priority validation,
**plus** lifecycle interception for redaction, **plus** a child CLI scope.
That is comfortably the largest step and reads as the smallest.

More generally: no step in the plan has an effort estimate, a size class, a
statement of what can run in parallel, or a numeric exit criterion (e.g. "N
characterization tests green on both compositions"). For a nine-step
migration of a shipped package this is the difference between a plan and a
wish list.

### G5. The frozen compatibility surface exceeds what was ever committed

`concept/compatibility-testing-and-operations.md` freezes roughly twenty
surfaces. `research/tell-as-sdk-refactoring/01-api-and-runtime.md` — the
prior design doc for this same package — says: *"Only these namespaces are
compatibility commitments in the first release"* and lists seven:
`Tell`, `TellRequest`, `TellResult`, `TellEvent`, `TellProgress`,
`TellConversation`, `TellWorkspace`.

`TellBranches`, `TellRef`, `TellBranchConfiguration`, `TellCatalogue`, and
`TellTools` are frozen by this plan but were explicitly *not* committed by
the prior one. Two research documents in the same repo disagree about the
public API, and nothing reconciles them. Freezing more than you owe makes
every subsequent step harder for no obligation — and Step 5 in particular
("convert `TellConversation`, `TellBranches` … into thin consumers") is much
easier if `TellBranches` can change shape.

### G6. Process-global state defeats the isolation story

`concept/configuration-discovery-and-security.md` builds secret isolation on
Cordis realms hiding capabilities from child contexts. Two process-global
escapes go unaddressed:

1. `TellAgentFactory::instantiateLoop()`
   (`packages/tell/src/Runtime/TellAgentFactory.php`) calls
   `putenv('OPENAI_API_KEY=tell-injected-driver-placeholder')` around loop
   construction and restores it in `finally`. Under the plan's "worker
   profile" and "tenant" scenarios this is a process-wide credential mutation
   with no scope, defeating any realm-level hiding, and it is a latent
   correctness bug the moment two hosts share a process.
2. `TellPaths::installed()` reads `TELL_HOME`, `HOME`, and `USERPROFILE` from
   the process environment. `concept/configuration-discovery-and-security.md`
   states "no layer scans arbitrary directories or environment variables
   outside its declared responsibility" — this is a direct violation that the
   migration map does not assign to any module.

Both are cheap to fix and both should be named. The `putenv` hack in
particular is the kind of thing a lifecycle redesign exists to eliminate; not
mentioning it suggests the plan was written from the class structure rather
than from the code.

### G7. Discovery errors are silently discarded today

`CapabilityDiscovery::discover()` returns a `DiscoveryResult` carrying an
`errors` list (duplicate names, unresolvable classes). `TellAgentFactory`
calls it as a bare statement and throws the result away.

`CanCatalogueTellExtensions` is the natural owner of that channel. The plan
describes the capability as "descriptive" but never says it must surface
discovery failures, so the modular version would preserve the bug.

### G8. The plan reorganises redundant work without removing it

Per `run()`, in the current code:

- `WorkspaceManager::discover()` runs up to three times (once in
  `withBranchConfig`, once in the mode router, once in `loop()` for
  delegation);
- `new ArenaStore($workspace)` is constructed up to three times —
  `TellRuntime.php:168` builds two in a single expression;
- `TellAgentFactory::definitions()` runs `autoDiscover()` twice per `build()`
  (once via `definition()`, once directly), each walking project, package,
  and user agent directories;
- `CapabilityDiscovery::discover()` re-reads Composer manifests on **every**
  agent build.

A host with a lifecycle is the natural place to cache all four, and that is a
real, measurable argument for the redesign. The plan does not mention any of
it, so as specified the modular version is no faster and quite possibly
slower.

### G9. No startup budget for the CLI

Tell's dominant use is a one-shot CLI where boot latency is user-visible.
The target adds a kernel mounting roughly twelve fibers through a
settle-to-fixed-point loop before any work happens. There is no budget, no
measurement, and no acceptance criterion anywhere in the plan. Add one to
`plan/01` (measure now) and gate `plan/07` on it.

### G10. `sessions.legacy` is scoped as a module rather than a deletion

`plan/06`/`concept/modules-and-wiring.md` create a module for legacy session
compatibility. Every module in this design costs a contract, a conformance
suite, an architecture gate, and a slot in every profile. For a format the
docs already describe as compatibility-only, a dated removal is cheaper than
a modular home. Decide the removal date in Step 1; if it lands before Step 6,
the module never needs to exist.

## Opportunities

### O1. Delete ~250 lines of `TellRuntime` before touching architecture

`TellRuntime` is 544 lines carrying eight near-identical mode methods
(`runAutomatic`/`streamAutomatic`, `runStateless`/`streamStateless`,
`runDurable`/`streamDurable`, `runTransient`/`streamTransient`) plus paired
workspace-turn, workspace-session, and legacy-session variants.

Every layer beneath it has already solved this:

- `AgentLoop::execute()` is literally `foreach ($this->iterate($state) …)`
  (`packages/agents/src/AgentLoop.php:104-110`);
- `WorkspaceTurnRunner::execute()` drains `iterate()`
  (`WorkspaceTurnRunner.php:35-42`);
- `WorkspaceTransientRunner::execute()` drains `iterate()`
  (`WorkspaceTransientRunner.php:29-40`).

Only `TellRuntime` duplicates. Collapsing `run()` to a drain of `stream()` is
a behaviour-preserving deletion of roughly half the file, it needs no
contracts, no kernel, and no new dependency — and it makes `CanRunTell` a
small interface instead of a large one. It also removes most of the risk from
`plan/04`.

Do this first. It is the cheapest, highest-confidence improvement available,
and it is independently valuable if the rest of the plan is deferred.

### O2. Land the dependency inversion without Cordis

Steps 2, 4, 5, 6, and 7 — contracts, modules, an external composition root,
`Tell::open()` as a facade over standard wiring, replaceable model/workspace/
observer/tools — need typed capability lookup and an explicit composition
root. They do not need pending states, restart-on-dependency-change,
reconciliation, or health streams. A one-shot CLI boots, runs, and exits.

`Utils\Context`/`Layer` already provides typed lookup and composition, or a
purpose-built `TellComposition` is a few hundred lines. Either gets the
target dependency direction, the architecture gates, the conformance suites,
and the replacement story, with **zero** new dependencies, **no** PHP floor
change, and **no** Symfony 8 risk.

Then adopt Cordis in a later, separately justified phase, when there is a
worker or MCP host that genuinely needs scoped disposal and restart — and
when Cordis has a tag, CI, and the leak in E2 fixed.

### O3. Spike the contract set through the two narrowest seams first

`TellToolDispatcher` already does
`(new TellRuntime($this->agents))->resolveDirectOptions($options)` — one
line, no persistence, no streaming. `Tell::testing()` already proves driver
substitution. Prove `CanResolveTellConfiguration`, `CanDispatchTellTool`, and
`CanBuildTellAgent` against those two seams before committing to twelve
modules. A one-week spike here will change the contract signatures, and it is
much cheaper to learn that before Step 4 than during it.

### O4. Make the host's caching an explicit, measured acceptance criterion

Turn G8 into a selling point: `plan/07` acceptance should include a measured
reduction in workspace discovery calls, agent-definition scans, and Composer
manifest reads per run. That converts "the architecture is nicer" into a
number, and gives the migration something to defend itself with.

### O5. Fix Cordis upstream as named prerequisites with owners

If Cordis is adopted, `plan/03`'s prerequisite should enumerate, not gesture:

1. prune disposed records in `EffectScope`, or reuse the child scope across
   restarts (E2);
2. widen `symfony/yaml` to `^7.3 || ^8.0` (E3);
3. add a transactional boot entry point — `mount()` currently never throws;
   failures become a `Failed` fiber the caller must go looking for
   (appendix B2);
4. decide instance-vs-factory module registration (E6);
5. add a CI workflow and a real test suite;
6. tag a release.

Items 1–4 are behaviour changes, not packaging. Sequencing Tell's migration
behind six unowned upstream changes to a three-commit library is the plan's
largest schedule risk and is currently invisible.

## Suggested re-sequencing

Keeping the plan's own step numbers where the content survives:

<!-- markdownlint-disable MD013 -->

| Phase | Content | Depends on Cordis |
| --- | --- | --- |
| 0 | O1 (collapse `TellRuntime` run/stream), G6 (`putenv`, `TellPaths`), G7 (discovery errors), G8 (per-run caching) | no |
| 1 | Step 1 minus the PHP floor decision; add startup-latency baseline (G9) and the compat-surface reconciliation (G5) | no |
| 2 | Step 2 contracts, with E5 and E7 decided explicitly; O3 spike first | no |
| 3 | Steps 4–7 over a plain typed composition root (O2) | no |
| 4 | MCP lifecycle or persistent shell jobs as the first scoped-resource feature (G2) | motivates |
| 5 | Step 3 kernel + Step 9 reconciliation, gated on O5 and a re-derived PHP floor | yes |
| 6 | Step 8 package proof | yes |

<!-- markdownlint-enable MD013 -->

The point of the reordering is that phases 0–3 are individually shippable and
individually valuable, phase 4 produces the requirement that justifies phase
5, and no consumer-facing break (PHP floor, Symfony pin, new dependency)
happens until something demonstrably needs it.

## Appendix A: source verification

- **A1** — concrete construction outside the composition root:
  `rg 'new ArenaStore|new BranchResolver|new BranchConfigStore|new TellRuntime'`
  over `packages/tell/src` returns 60+ sites across `TellRuntime`,
  `TellBranches`, `TellBranch`, `TellBranchConfiguration`, `TellConversation`,
  `TellRef`, `TellSubagentExecutor`, `TellToolDispatcher`, and twelve
  `Command/*` classes.
- **A2** — `packages/tell/composer.json` `php: ^8.4` is an uncommitted
  working-tree change (`git diff HEAD -- packages/tell/composer.json`); all
  other `packages/*` are `^8.3`.
- **A3** — `.github/workflows/split.yml` package test matrix:
  `php: ['8.2', '8.3', '8.4']`. `.github/workflows/php.yml`:
  `php: ['8.3','8.4','8.5']` × `symfony: ['^7.3','^8.0']`.
- **A4** — `packages/utils/src/Context/{Context,Layer,Key}.php` +
  `LAYER_CONTEXT.md`; referenced only by its own tests and PSR adapters.
- **A5** — `AgentLoop::execute()` drains `iterate()`
  (`packages/agents/src/AgentLoop.php:104-110`); same shape in
  `WorkspaceTurnRunner:35` and `WorkspaceTransientRunner:29`.
- **A6** — `TellResult::state(): AgentState`
  (`packages/tell/src/TellResult.php:28`); `AgentState` is
  `final readonly class` in `cognesy/agents`.

## Appendix B: Cordis reproductions

Both scripts were run against `~/projects/cordis-php` at `40ebc0c`.

**B1 — scope record accumulation on restart.** Mount a provider of `dep` and
a consumer requiring `dep`; call `$consumer->restart()` 500 times; read the
root `EffectScope`'s private `records` array by reflection.

```text
root scope records after mount: 2
after 500 consumer restarts:
  root scope records: 502
  consumer state:     active
  runtime fibers:     2
```

Growth is one dead `EffectRecord` per restart, never pruned.

**B2 — boot and swap semantics.** Mounting a plugin whose `apply()` throws,
and one whose requirement is never provided:

```text
mount() returned without throwing
  ok       state=active   missing=[]                  error=-
  bad      state=failed   missing=[]                  error=boom
  pending  state=pending  missing=["never-provided"]  error=-
```

Swapping a provider:

```text
  after provider dispose, consumer state=pending missing=["a"]
  after remount,          consumer state=active
```

Duplicate provider of the same id:

```text
  dup state=failed error=Service "a" already has a live provider in this realm.
```

Three consequences for the plan: `mount()` never throws, so all boot-failure
aggregation and transactional rollback is Tell-side work; duplicate-provider
detection surfaces as a failed fiber after the fact, so the plan's
"duplicate singleton providers fail composition admission" must be a Tell
pre-boot check; and the consumer restart on swap is immediate and
unconditional, with no lease consulted (G3).

**B3 — disposal runs cleanup before binding removal.** A provider publishes a
gateway and returns a cleanup closure that closes it; a consumer requires the
gateway and holds the resolved reference. Disposing the provider fiber:

```text
  [consumer] started, holds gateway
--- disposing provider fiber ---
  [provider cleanup] BEFORE close: consumer state=active, binding present=true
  [provider] gateway->close() ran
  [dispatch] Removed('gw') fired
    consumer fiber state at dispatch : pending
    gateway->closed at dispatch      : true
--- after dispose ---
  held reference threw: USE AFTER CLOSE
```

Still reproducible on the patched tree; see N1.

**B4 — generator abandonment and quiescence accounting.** An outer generator
(standing in for `TellRuntime::streamStateless()`) drives an inner generator
(standing in for `AgentLoop::iterate()`); both increment a supervisor counter
on entry and decrement it in `finally`. Five disposal paths:

```text
--- case A: fully drained ---
quiescent after drain: true
--- case B: abandoned mid-stream ---
mid-stream inflight=2 quiescent=false
after unset  inflight=0 quiescent=true
  enter outer (inflight=1)
  enter inner (inflight=2)
  leave inner (inflight=1)
  leave outer (inflight=0)
--- case C: parked, never abandoned ---
parked inflight=2 quiescent=false
--- case D: created but never advanced ---
never-advanced inflight=0 quiescent=true
--- case E: reference cycle holds the generator ---
after unset (cycle) inflight=2
after gc_collect    inflight=0
```

Case B confirms that destruction unwinds a suspended generator and cascades
inward, so abandonment is safe *provided* the counter is decremented in a
`finally` inside the generator. Cases C and E are the two ways a supervisor
can wait forever.

**B5 — hook observability of abandonment, on the real `AgentLoop`.** A real
`AgentLoop` with `FakeAgentDriver` (four `ping` tool calls then a final
response) and a `CanInterceptAgentLifecycle` probe that increments on
`BeforeExecution` and decrements on `AfterExecution`:

```text
=== case 1: run drained to completion ===
  yields=5 hooks=BeforeExecution,AfterExecution
  inflight AFTER drain      : 0
  supervisor sees quiescent : true

=== case 2: run abandoned mid-stream ===
  hooks so far              : BeforeExecution
  inflight while suspended  : 1
  hooks after destruction   : BeforeExecution
  inflight AFTER abandon    : 1
  supervisor sees quiescent : false

=== case 3: Tell-side wrapper generator with try/finally ===
  inflight while suspended  : 1
  inflight AFTER abandon    : 0
  supervisor sees quiescent : true
  hook-based counter still  : 1  (AfterExecution never fired)
```

Case 2 is the deadlock: the generator is destroyed and unreachable, yet
`AfterExecution` never fires and the counter never returns to zero. Case 3
confirms the wrapper in option (b) works, and that it works *because* the
`finally` is inside a generator body — the same mechanism as B4 case B.

**B6 — the patch shape for an outer `try`/`finally` in `iterate()`.** The
trap, first:

```text
=== trap: yield inside finally of a force-closed generator ===
  Error: Cannot yield from finally in a force-closed generator
```

Then the proposed shape, mirroring `AgentLoop::iterate()`'s control flow with
a guarded terminal dispatch:

```text
=== case 1: drained to completion ===
  yields=4
  hooks=BeforeExecution,step1,step2,step3,AfterExecution(completed)
  AfterExecution count=1
=== case 2: abandoned mid-stream ===
  destruction: clean
  hooks after abandon =BeforeExecution,step1,step2,AfterExecution(abandoned)
  AfterExecution count=1
=== case 3: abandoned AT the final yield (post-settle) ===
  hooks at final yield=BeforeExecution,step1,AfterExecution(completed)
  hooks after abandon =BeforeExecution,step1,AfterExecution(completed)
  AfterExecution count=1 (must be 1)
=== case 4: consumer throws into the generator ===
  propagated: consumer boom
  hooks=BeforeExecution,step1,AfterExecution(abandoned)
```

Case 3 is the one a naive implementation gets wrong: without the guard, a
generator abandoned while parked on the final yield settles twice.
