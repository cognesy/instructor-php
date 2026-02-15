# Peer Review: Capability Installation Contract

## Goal

Decouple capabilities from concrete `AgentBuilder` by introducing a tiny installation interface that exposes only composition primitives.

## Problem Today

Current contract:

```php
interface AgentCapability
{
    public function install(AgentBuilder $builder): void;
}
```

This hard-codes capabilities to one concrete class and makes it harder to:
- test capabilities in isolation,
- evolve/replace builder internals,
- reuse capability install logic in alternate assemblers.

## Proposed Contract

Introduce a minimal interface (using existing naming convention):

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\Collections\Tools;use Cognesy\Agents\Context\CanCompileMessages;use Cognesy\Agents\Drivers\CanUseTools;use Cognesy\Agents\Hook\Collections\HookTriggers;use Cognesy\Agents\Hook\Contracts\HookInterface;use Cognesy\Agents\Tool\Tools\BaseTool;use Cognesy\Events\Contracts\CanHandleEvents;

interface CanInstallAgentCapabilities
{
    public function withTools(Tools|array $tools): self;

    /**
     * @param callable(Tools, CanUseTools, CanHandleEvents): BaseTool $factory
     */
    public function addToolFactory(callable $factory): self;

    public function addHook(
        HookInterface $hook,
        HookTriggers $triggers,
        int $priority = 0,
        ?string $name = null,
    ): self;

    public function withDriver(CanUseTools $driver): self;

    public function withContextCompiler(CanCompileMessages $compiler): self;

    public function contextCompiler(): ?CanCompileMessages;
}
```

Then change capability contract to:

```php
interface AgentCapability
{
    public function install(CanInstallAgentCapabilities $builder): void;
}
```

## Why This Is "Tiny"

It only contains operations capabilities actually need:
- register tools,
- register hooks,
- register deferred tool factories,
- set/wrap driver,
- set/wrap context compiler.

It intentionally excludes build/lifecycle concerns (`build()`, `withEvents()`, `eventHandler()`, `hookStack()`, etc.).

## Implementation Plan

1. Add `CanInstallAgentCapabilities` in `packages/agents/src/AgentBuilder/Contracts/`.
2. Make `AgentBuilder` implement `CanInstallAgentCapabilities`.
3. Update `AgentCapability::install(...)` signature to use the new interface.
4. Update all `Use*` capabilities to type-hint the interface instead of `AgentBuilder`.
5. Keep behavior unchanged (mechanical refactor only).

## Backward Compatibility

This is source-breaking for custom capabilities if done in one shot. Two safe options:

1. Short migration window (recommended):
- bump minor version,
- provide upgrade notes with one-line change:
  `install(AgentBuilder $builder)` -> `install(CanInstallAgentCapabilities $builder)`.

2. Soft transition:
- keep `AgentCapability` as-is temporarily,
- add `AgentCapabilityV2` with new signature,
- allow `withCapability()` to accept either.

Given low complexity, option 1 is likely sufficient.

## Testing Benefits

With the interface in place, capability tests can use a tiny fake installer:

```php
final class RecordingCapabilityInstaller implements CanInstallAgentCapabilities
{
    // record calls to tools/hooks/factories/compiler/driver for assertions
}
```

This removes the need to instantiate full `AgentBuilder` for unit tests of installation behavior.

## Follow-up (Optional)

If needed later, split into write/read interfaces:
- `CanComposeAgentLoop` (mutating methods),
- `CanReadAgentComposition` (`contextCompiler()` only).

Start with one interface now; split only if a real pressure emerges.

