# Agent Contract for Laravel Jobs (Library Groundwork)

Goal: make agents instantiable in workers **without serialization**, while still supporting iterative execution and self‑description.

---

## Problem

`AgentBuilder` produces a dynamic `Agent` instance that is not easy to serialize into a queue job. We need deterministic **agent factories** that can be instantiated via `new`/static methods and that expose both execution and metadata.

---

## Proposed Contract

Define a small interface that wraps builder usage and provides:

1) **Execution** (iterative or full)
2) **Self‑description** (name, type, tools, capabilities)
3) **Serialization** (stable config snapshot)
4) **Event hooks** (wiretap/onEvent)

```php
<?php

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

interface AgentContract
{
    // deterministic metadata
    public function descriptor(): AgentDescriptor;

    // execution
    public function build(): Agent;
    public function run(AgentState $state): AgentState;

    // events
    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self;
    public function wiretap(?callable $listener): self;
    public function onEvent(string $class, ?callable $listener): self;

    // serialization
    public function serializeConfig(): array;
    public static function fromConfig(array $config): Result;
}
```

Notes:
- `build()` constructs the underlying `Agent` via `AgentBuilder`.
- `run()` can default to `build()->finalStep($state)` for convenience.
- `serializeConfig()` should return a minimal, stable configuration for worker instantiation.
- `fromConfig()` uses `Result` to avoid exception‑driven control flow.

---

## Factory / Registry

Provide a registry that returns deterministic instances by name:

```php
interface AgentFactory
{
    public function create(string $agentName, array $config = []): AgentContract;
}
```

This allows Laravel jobs to store only:

- `agent_name`
- `agent_config` (array)

and re‑instantiate in a worker without serialized closures or dynamic builders.

---

## Minimal Implementation Pattern

```php
final class CodeAssistantAgent implements AgentContract
{
    public function descriptor(): AgentDescriptor
    {
        return new AgentDescriptor(
            name: 'code-assistant',
            description: 'Code helper with file + bash tools',
            tools: NameList::fromArray(['read_file', 'write_file', 'bash']),
            capabilities: NameList::fromArray(['file', 'bash', 'tasks']),
        );
    }

    public function build(): Agent
    {
        return AgentBuilder::base()
            ->withCapability(new UseBash())
            ->withCapability(new UseFileTools('/workspace'))
            ->withCapability(new UseTaskPlanning())
            ->withMaxSteps(50)
            ->build();
    }

    public function run(AgentState $state): AgentState
    {
        return $this->build()->finalStep($state);
    }

    public function serializeConfig(): array
    {
        return ['workspace' => '/workspace', 'max_steps' => 50];
    }

    public static function fromConfig(array $config): Result
    {
        return Result::ok(new self());
    }
}
```

---

## Laravel Job Flow

1) Job payload contains `agent_name` + `agent_config`
2) Worker resolves agent via `AgentFactory`
3) Worker builds `AgentState` (from input or snapshot)
4) `AgentContract->run($state)` or `->build()->iterator(...)`
5) Optional: attach logging pipeline via `wiretap()` / `onEvent()`

---

## Why This Works

- Deterministic: job payload is plain JSON.
- No serialized closures or builder state.
- Agent metadata available for UI or logs.
- Contract fits current design (AgentBuilder + capabilities).

---

## Next Step (Library Work)

- Implement `AgentContract` and a simple `AgentFactory` registry.
- Provide 1–2 sample agent classes to show the pattern.
- Add short docs for Laravel integration referencing the contract.
