# Final Design: New AgentBuilder

## 1. Design Goal

Keep the architecture explicit and minimal:

- `AgentLoop` owns execution (`CanControlAgentLoop`)
- `AgentBuilder` owns composition (`CanComposeAgentLoop`)
- `AgentCapability` is a real concept (not marker-only)
- Capability installation is direct and low-ceremony

No legacy API in target state.

---

## 2. Core Contracts

```php
interface CanComposeAgentLoop
{
    public function withCapability(AgentCapability $capability): self;
    public function build(): CanControlAgentLoop;
}

interface AgentCapability
{
    public function id(): string;
    public function installInto(CanInstallAgentCapability $install): void;
}

interface CanInstallAgentCapability
{
    public function addTools(ToolInterface ...$tools): void;
    public function addHook(HookInterface $hook, HookTriggers $triggers, int $priority = 0, ?string $name = null): void;
    public function addToolFactory(callable $factory): void;

    public function setDriver(CanUseTools $driver): void;
    public function setContextCompiler(CanCompileMessages $compiler): void;
    public function setInterceptor(CanInterceptAgentLifecycle $interceptor): void;

    public function decorateDriver(callable $decorator): void;
    public function decorateContextCompiler(callable $decorator): void;
}
```

### Why this shape

- `CanComposeAgentLoop` stays tiny and elegant.
- `AgentCapability` keeps clear essence (identity + install action).
- `CanInstallAgentCapability` is direct and practical: one line capability installation remains one line.

---

## 3. Builder Structure

`AgentBuilder` implements both:

- `CanComposeAgentLoop` (public role)
- `CanInstallAgentCapability` (capability install role)

Internal state is flat accumulators, not a heavy composition object graph:

- `tools[]`
- `hooks[]`
- `toolFactories[]`
- `driver` + `driverDecorators[]`
- `compiler` + `compilerDecorators[]`
- `interceptor`
- `events`

This keeps implementation small and easy to reason about.

---

## 4. Build Algorithm (Deterministic)

1. Resolve compiler:
- explicit compiler if set, otherwise default
- apply compiler decorators in insertion order

2. Resolve driver:
- explicit driver if set, otherwise default
- normalize: rebind final compiler/events when supported
- apply driver decorators in insertion order

3. Resolve tools:
- start with direct tools
- run tool factories after driver/events are finalized

4. Resolve interceptor:
- explicit interceptor if provided
- otherwise build hook stack
- fallback to pass-through if no hooks

5. Create `ToolExecutor` with finalized tools/events/interceptor.
6. Create `AgentLoop`.

---

## 5. Event Rule

Event wiring must be order-independent.

Practical requirement:
- final `build()` normalization is source of truth for event-bearing components (driver/executor/interceptor).
- no API ordering contract like "call X before Y."

---

## 6. Capability Example

```php
final class UseBash implements AgentCapability
{
    public function id(): string
    {
        return 'use_bash';
    }

    public function installInto(CanInstallAgentCapability $install): void
    {
        $install->addTools(new BashTool());
    }
}
```

This is intentionally one line and stays one line.

---

## 7. Non-Goals

1. No public low-level builder API (`addHook`, `withTools`, `withDriver`, etc.) on composition contract.
2. No marker-only `AgentCapability`.
3. No `CanAcceptToolExecutor` at this stage (executor stays infrastructure owned by build assembly).

---

## 8. Implementation Plan

### Phase 1: Contracts

1. Add `CanComposeAgentLoop`.
2. Redefine `AgentCapability` as `id() + installInto(...)`.
3. Add `CanInstallAgentCapability`.

### Phase 2: Builder Refactor

1. Refactor `AgentBuilder` to implement both contracts.
2. Replace internal mixed config behavior with flat accumulator fields.
3. Implement deterministic `build()` normalization sequence.

### Phase 3: Capabilities

1. Migrate built-in `Use*` capabilities to `installInto(...)`.
2. Ensure capabilities use only installer commands needed by their feature.

### Phase 4: Tests

1. Composition contract tests (`withCapability`, `build`).
2. Deterministic build ordering tests.
3. Event commutativity tests.
4. Capability behavior parity tests for core built-ins.

### Phase 5: Docs

1. Update examples to capability-first composition.
2. Document direct installer idiom (`addTools(...)`, `addHook(...)`).

---

## 9. Summary

This final design preserves elegant surface area while avoiding overengineering:

- tiny public composer contract,
- strong capability contract,
- direct installer commands,
- deterministic assembly,
- no legacy complexity carried forward.

