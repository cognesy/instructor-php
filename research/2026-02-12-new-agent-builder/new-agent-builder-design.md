# New AgentBuilder Design (Target State)

## Purpose

Define a clean, final design for `AgentBuilder` aligned with one principle:
- `AgentLoop` controls execution (`CanControlAgentLoop`)
- `AgentBuilder` controls composition (`CanComposeAgentLoop`)

No legacy API retention in this target design.

---

## Design Principles

1. Minimal public contract reflects essence, not wiring details.
2. Capabilities are installed via narrow feature channels.
3. No direct capability dependency on concrete `AgentBuilder`.
4. No ordering footguns (`withEvents()` before X, etc.).
5. Build is deterministic and reproducible.
6. Optional behavior lives in capabilities, not hardcoded builder defaults.

---

## Public Contracts

### 1) Composition Contract

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\CanControlAgentLoop;

interface CanComposeAgentLoop
{
    public function withCapability(CanProvideAgentCapability $capability): self;

    public function build(): CanControlAgentLoop;
}
```

This is the only public builder contract.

### 2) Capability Marker

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

interface AgentCapability {}
```

Capabilities contribute through channel install interfaces below.

---

## Channel Contracts (Feature Surfaces)

Capabilities should depend only on channels they need.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\Collections\Tools;use Cognesy\Agents\Context\CanCompileMessages;use Cognesy\Agents\Drivers\CanUseTools;use Cognesy\Agents\Hook\Collections\HookTriggers;use Cognesy\Agents\Hook\Collections\RegisteredHooks;use Cognesy\Agents\Hook\Contracts\HookInterface;use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;use Cognesy\Agents\Tool\Tools\BaseTool;

interface CanAcceptHooks
{
    public function withHook(
        HookInterface $hook,
        HookTriggers $triggers,
        int $priority = 0,
        ?string $name = null,
    ): self;

    public function hooks(): RegisteredHooks;
}

interface CanAcceptContextCompiler
{
    public function withContextCompiler(CanCompileMessages $compiler): self;

    public function contextCompiler(): ?CanCompileMessages;
}

interface CanAcceptTools
{
    public function withTools(Tools|array $tools): self;

    public function tools(): Tools;
}

interface CanAcceptToolFactories
{
    /** @param callable(Tools, CanUseTools): BaseTool $factory */
    public function withToolFactory(callable $factory): self;

    /** @return array<callable(Tools, CanUseTools): BaseTool> */
    public function toolFactories(): array;
}

interface CanAcceptDriver
{
    public function withDriver(CanUseTools $driver): self;

    public function driver(): ?CanUseTools;
}

interface CanAcceptInterceptor
{
    public function withInterceptor(CanInterceptAgentLifecycle $interceptor): self;

    public function interceptor(): ?CanInterceptAgentLifecycle;
}
```

---

## Capability Install Contracts

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

interface CanInstallHooks extends CanProvideAgentCapability
{
    public function installHooks(CanAcceptHooks $hooks): void;
}

interface CanInstallContextCompiler extends CanProvideAgentCapability
{
    public function installContextCompiler(CanAcceptContextCompiler $compiler): void;
}

interface CanInstallTools extends CanProvideAgentCapability
{
    public function installTools(CanAcceptTools $tools): void;
}

interface CanInstallToolFactories extends CanProvideAgentCapability
{
    public function installToolFactories(CanAcceptToolFactories $factories): void;
}

interface CanInstallDriver extends CanProvideAgentCapability
{
    public function installDriver(CanAcceptDriver $driver): void;
}

interface CanInstallInterceptor extends CanProvideAgentCapability
{
    public function installInterceptor(CanAcceptInterceptor $interceptor): void;
}
```

A capability may implement one or many install contracts.

---

## AgentBuilder Responsibilities

`AgentBuilder` implements `CanComposeAgentLoop` and internally implements all channel interfaces.

Publicly:
- `withCapability()`
- `build()`

Internally:
- stores channel state (tools/hooks/factories/driver/compiler/interceptor/events),
- applies capabilities by checking implemented install contracts,
- resolves and normalizes final runtime graph.

No public mutator API for low-level channels.

---

## Deterministic Build Algorithm

1. Start from empty composition state.
2. Apply capabilities in registration order:
- if capability implements `CanInstallHooks`, call `installHooks(...)`
- if capability implements `CanInstallContextCompiler`, call `installContextCompiler(...)`
- if capability implements `CanInstallTools`, call `installTools(...)`
- if capability implements `CanInstallToolFactories`, call `installToolFactories(...)`
- if capability implements `CanInstallDriver`, call `installDriver(...)`
- if capability implements `CanInstallInterceptor`, call `installInterceptor(...)`
3. Resolve compiler:
- selected compiler from channel state, else default compiler.
4. Resolve driver:
- selected driver from channel state, else default driver.
- normalize by rebinding final compiler/events when supported.
5. Resolve tools:
- merge direct tools.
- execute deferred tool factories after driver is finalized.
6. Resolve interceptor:
- explicit interceptor channel if provided, else hook-based interceptor, else pass-through.
7. Build tool executor from finalized tools/events/interceptor.
8. Build `AgentLoop`.

All decisions are explicit, order-aware, and deterministic.

---

## Event Wiring Rule

Event wiring must be order-independent.

Target behavior:
- capabilities can be registered before or after external events configuration,
- final built driver/executor/interceptor always use the same resolved event bus,
- no "call X before Y" constraints.

Implementation requirement:
- late normalization in `build()` is mandatory for event-bearing components.

---

## Defaults and Profiles

Two entry points:

1. `AgentBuilder::base()`
- no opinionated capabilities installed.

2. `AgentBuilder::standard()`
- installs standard capability profile (e.g., guards) explicitly via capabilities.

No hidden hardcoded policy injection in `build()`.

---

## Explicit Non-Goals

1. No legacy fluent config methods on `AgentBuilder` (target state is clean API only).
2. No public `addHook()`, `withTools()`, `withDriver()`, `withContextCompiler()`, etc.
3. No `CanAcceptToolExecutor` channel at this stage.
- executor remains infrastructure owned by builder assembly.
- if needed later, add a narrow policy channel, not raw executor injection.

---

## Capability Mapping (Examples)

1. `UseBash`
- `CanInstallTools` (later optionally `CanInstallHooks`).

2. `UseSummarization`
- `CanInstallHooks`.

3. `UseLlmConfig`
- `CanInstallDriver`.

4. `UseSubagents`
- `CanInstallToolFactories`.

5. Guard profile capability
- `CanInstallHooks`.

---

## Required Test Suite

1. Builder essence:
- only capability composition + build exposed via composition contract.

2. Channel dispatch:
- capabilities implementing multiple install contracts are applied correctly.

3. Determinism:
- same capability order gives same loop wiring.

4. Hook ordering:
- priority desc, stable insertion order for ties.

5. Event commutativity:
- external event configuration order does not change emitted behavior.

6. Tool factory timing:
- factories receive finalized driver.

---

## Summary

The target design removes the historical anti-pattern (builder as mixed policy/wiring API) and replaces it with:
- a minimal composition essence (`CanComposeAgentLoop`),
- narrow feature channels for capability installation,
- deterministic, order-safe assembly into `AgentLoop`.

