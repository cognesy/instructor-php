# AgentBuilder Redesign: Overview

## Problem Statement

AgentBuilder has grown into a chaotic mix of concerns. It simultaneously acts as:
- A tool registry
- A hook composition engine
- A limit/guard configurator (with hardcoded defaults)
- A driver factory (LLM preset, retries)
- A context configurator (system prompt, response format)
- A capability plugin system

The result: 12+ specific properties, matching fluent methods, and private `buildHookStack()`/`addGuardHooks()`/`addContextHooks()`/`addMessageHooks()` methods that hardcode behavior that should be modular.

## Design Principles

### AgentLoop is a stateless engine
AgentLoop processes `AgentState` but carries no configuration. System prompt lives in `AgentContext` (inside `AgentState`). Guards are optional behavior-shaping hooks — not loop behavior. Nothing moves from AgentBuilder into AgentLoop.

### AgentBuilder is a composition engine, not a configuration surface
AgentBuilder's role is precisely:
1. **Knows HookStack** as the core interceptor implementation
2. **Installs packaged capabilities** via `AgentCapability`
3. **Returns a configured AgentLoop** with all features wired together

Everything opinionated — guards, system prompt, driver config, finish reasons — lives in `Use*` capabilities. AgentBuilder itself has no opinions.

### Capabilities are stable extension points, not current-complexity wrappers
A capability like `UseBash` is trivial today (one tool). Tomorrow it grows: security policies, BeforeToolUse guards, logging hooks, output sanitization. The capability boundary is where complexity accretes — in one place, not N call sites. Evaluating capabilities by current line count is short-sighted.

## Document Index

- [01-architecture.md](./01-architecture.md) — Target architecture and class design
- [02-capabilities.md](./02-capabilities.md) — Capability inventory and new capabilities
- [03-examples.md](./03-examples.md) — Usage examples demonstrating the pluggable model
- [04-technical-challenges.md](./04-technical-challenges.md) — Migration risks and solutions
