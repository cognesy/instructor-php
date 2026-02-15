# Peer Review: `CanComposeAgentLoop` Contract

## Thesis

`AgentBuilder` should expose only its essence: compose capabilities, then build a controllable loop.
Anything else repeats the old anti-pattern (builder as a grab-bag of operational knobs).

## Core Contracts

### 1) Composition contract (builder-facing)

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

This mirrors the elegance of `CanControlAgentLoop`: one clear responsibility, minimal surface.

### 2) Channel contracts (feature-facing)

Instead of one "god installer" interface or one large value object, define small feature channels.

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

Note: exact collection types can be adjusted to existing package types; the key is segregated channel interfaces.

### 3) Capability contracts by channel

Capabilities should depend only on the channels they actually need.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

interface AgentCapability {}

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

A capability can implement one or many of these interfaces.

Examples:
- `UseBash`: `CanInstallTools` (and later `CanInstallHooks` if it adds guard/audit hooks).
- `UseSummarization`: `CanInstallHooks`.
- `UseLlmConfig`: `CanInstallDriver`.
- `UseSubagents`: `CanInstallToolFactories`.
- Interceptor policy capability: `CanInstallInterceptor`.

## Build Orchestration

`AgentBuilder` remains minimal publicly (`withCapability`, `build`), but internally owns channel state and applies capabilities by interface.

Pseudo-flow:

1. collect capabilities in registration order,
2. for each capability, dispatch to supported install interfaces,
3. normalize dependencies in `build()`:
- resolve compiler,
- resolve driver,
- rebind events/compiler to driver,
- resolve tool factories with final driver,
- build loop.

This keeps runtime assembly deterministic without exposing low-level mutators on the builder contract.

## Why This Is Better

1. Preserves a tiny, elegant builder essence (`CanComposeAgentLoop`).
2. Removes direct coupling to concrete `AgentBuilder`.
3. Avoids replacing one god object with another god interface.
4. Gives capabilities narrow, explicit dependencies.
5. Keeps future evolution local to channels.

## What Should Not Be in `CanComposeAgentLoop`

Do not expose:
- `withHook()`
- `withTools()`
- `withToolFactory()`
- `withDriver()`
- `withContextCompiler()`

Those belong to internal feature channels, not to the public builder essence.

## Migration Plan

1. Keep current `AgentCapability::install(AgentBuilder $builder)` temporarily.
2. Introduce channel-based interfaces above.
3. Migrate built-in capabilities to channel install interfaces.
4. In `AgentBuilder`, support both paths during transition:
- legacy `install(...)`,
- new per-channel install interfaces.
5. Remove legacy path after one cycle.

## Suggested Tests

1. Channel dispatch test: a capability implementing multiple install interfaces is applied to all relevant channels.
2. Deterministic ordering test: hook priority and insertion order are stable.
3. Builder essence test: only `withCapability` and `build` are required by public composition contract.
4. Compatibility test: legacy and channel-based capabilities produce equivalent loops.
