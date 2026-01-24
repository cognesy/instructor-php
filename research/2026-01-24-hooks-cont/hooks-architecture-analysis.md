# Hooks Architecture Analysis

## Context

After completing Phases A-E of the StepByStep incorporation into `packages/agents`, we analyzed the hooks system architecture. The hooks system (28 files, ~1,200 lines) was designed but only partially integrated.

**Date:** 2026-01-24
**Status:** Concept for team discussion

---

## Current State Analysis

### What's Working
- **ToolExecutor.php** uses hooks correctly for `PreToolUse`/`PostToolUse`
- HookStack, contexts, matchers, concrete hooks are all functional

### What's NOT Working
- **Agent.php** does NOT fire lifecycle hooks (BeforeStep, AfterStep, ExecutionStart, ExecutionEnd, Stop, AgentFailed)
- **AgentBuilder** has `onStop()`, `onExecutionStart()`, etc. methods that register hooks, but Agent never fires them
- **Adapters** (ContinuationCriteriaAdapter, StateProcessorAdapter) exist but are unused - designed for a transition that never happened

### Parallel Systems Running

| System | Purpose | Location |
|--------|---------|----------|
| ContinuationCriteria | Stop decisions | Agent.php uses directly |
| StateProcessors | State mutations | Agent.php uses directly |
| Events | Notifications | Agent.php emits |
| HookStack | Tool lifecycle | ToolExecutor only |

---

## Two Directions Evaluated

### Direction A: Incorporate Hooks into Core

Make hooks THE unified mechanism for all extension points.

**Architecture:**
```
Agent
├── HookStack (unified)           # Single hook stack for everything
│   ├── ExecutionStart hooks
│   ├── BeforeStep hooks          # Replace StateProcessors (pre)
│   ├── AfterStep hooks           # Replace StateProcessors (post)
│   ├── Stop hooks                # Replace ContinuationCriteria
│   ├── PreToolUse hooks
│   ├── PostToolUse hooks
│   ├── ExecutionEnd hooks
│   └── AgentFailed hooks
└── ToolExecutor
    └── (uses same HookStack)
```

**Pros:**
- Single mental model
- Consistent API via HookStack
- Flexible composition with priority-based ordering
- Cross-cutting concerns handled by single hook
- Already designed - just needs integration

**Cons:**
- More indirection for simple cases
- Heavier core (~1,200 lines required)
- Migration cost for existing code
- Must learn hooks before customizing
- Harder debugging (hook chains)

### Direction B: Externalize Hooks as Pluggable Capability

Move hooks to separate namespace, keep Agent minimal (microkernel pattern).

**Architecture:**
```
Agent (minimal core)
├── ContinuationCriteria          # Direct, simple stop logic
├── StateProcessors               # Direct, simple state mutations
├── ToolExecutor (minimal)        # Basic tool execution
└── EventBus                      # Notifications only

Hooks (optional layer, separate namespace)
├── HookableAgent extends/wraps Agent
│   └── Integrates HookStack
├── HookableToolExecutor extends/wraps ToolExecutor
│   └── Fires tool hooks
└── HookStack + all hook infrastructure
```

**Pros:**
- Minimal core, easy to understand
- Progressive complexity (start simple, add hooks when needed)
- Clear separation - hooks are opt-in
- Easier onboarding
- Testability (Agent without hook machinery)
- Aligns with microkernel pattern

**Cons:**
- Two mental models (ContinuationCriteria + hooks)
- Duplication of concepts (processors vs step hooks)
- API fragmentation
- Integration complexity
- More code overall
- Feature parity risk

---

## Key Insight: Tool Hooks are ESSENTIAL

| Capability | ContinuationCriteria/StateProcessors | Hooks |
|------------|--------------------------------------|-------|
| Step limits | ✅ | ✅ |
| Custom stop conditions | ✅ | ✅ |
| Logging/metrics | Events work | Better (richer context) |
| **Tool call blocking** | ❌ Not possible | ✅ Required |
| **Tool call modification** | ❌ Not possible | ✅ Required |
| Security sandboxing | ❌ | ✅ |
| Cost control (block expensive) | ❌ | ✅ |
| Priority-based ordering | Limited | Native |
| Conditional execution | Manual | Matchers |

**Tool hooks are non-negotiable** - there is no other way to intercept/modify tool calls. This is critical for:
- Security (blocking dangerous commands)
- Sandboxing (modifying tool calls)
- Cost control (blocking expensive operations)
- Logging with full context

---

## Recommended: Hybrid Approach (Direction A-Lite)

### Principle

1. **Tool hooks are ESSENTIAL** → Keep in ToolExecutor (already done)
2. **Lifecycle hooks are OPTIONAL** → Complete Agent.php integration, but don't replace existing systems
3. **ContinuationCriteria remains PRIMARY** for stop logic (clear semantics: ForbidContinuation, AllowContinuation, etc.)
4. **StateProcessors remain PRIMARY** for state mutations (simpler middleware API)
5. **Remove adapters** - they were workarounds for a transition we're not doing

### Architecture After

```
Agent.php (minimal integration)
├── ContinuationCriteria          # Primary stop logic (unchanged)
├── StateProcessors               # Primary state mutations (unchanged)
├── HookStack $lifecycleHooks     # Optional lifecycle hooks (NEW)
│   └── Fires: ExecutionStart, BeforeStep, AfterStep, Stop, ExecutionEnd, AgentFailed
└── Events                        # Notifications (unchanged)

ToolExecutor.php (already complete)
└── HookStack $toolHooks          # Required for tool control
    └── Fires: PreToolUse, PostToolUse
```

### What to Remove

**Delete these adapter files** (unused workarounds):
- `packages/agents/src/Agent/Hooks/Adapters/ContinuationCriteriaAdapter.php`
- `packages/agents/src/Agent/Hooks/Adapters/StateProcessorAdapter.php`
- `packages/agents/src/Agent/Hooks/Adapters/` (directory)

### What to Keep (No Changes)

- `HookStack` - works well
- `HookOutcome`, `HookContext`, `HookEvent` - core abstractions
- All 10 concrete hook types - all useful
- All 4 matchers - useful for conditional execution
- `ContinuationCriteria` - primary stop API
- `StateProcessors` - primary mutation API

### What to Add

**Agent.php changes (~30 lines)**:
1. Add optional `HookStack $lifecycleHooks` constructor parameter
2. Fire `ExecutionStart` at beginning of `finalStep()`/`iterator()`
3. Fire `BeforeStep` before `makeNextStep()`
4. Fire `AfterStep` after step completion
5. Fire `Stop` during continuation evaluation
6. Fire `ExecutionEnd` at end of execution
7. Fire `AgentFailed` in `onFailure()`

**AgentBuilder.php changes (~20 lines)**:
1. Separate `$toolHooks` (for ToolExecutor) from `$lifecycleHooks` (for Agent)
2. Pass `$lifecycleHooks` to Agent constructor

---

## API After Implementation

```php
// Basic usage - no hooks needed
$agent = AgentBuilder::make()
    ->withTools($tools)
    ->withContinuationCriteria(new StepsLimit(10))  // Primary API
    ->build();

// Tool hooks - essential for security
$agent = AgentBuilder::make()
    ->withTools($tools)
    ->onBeforeToolUse(function($context) {          // → ToolExecutor
        if ($context->toolCall()->name() === 'dangerous') {
            return HookOutcome::block('Not allowed');
        }
    })
    ->build();

// Lifecycle hooks - optional for advanced use cases
$agent = AgentBuilder::make()
    ->withTools($tools)
    ->onExecutionStart(function($context) { ... })  // → Agent (optional)
    ->onStop(function($context) { ... })            // → Agent (optional)
    ->build();
```

---

## Comparison Summary

| Aspect | Direction A | Direction B | Hybrid (Recommended) |
|--------|-------------|-------------|---------------------|
| Mental models | 1 (hooks) | 2 (hooks + legacy) | 1.5 (legacy primary, hooks secondary) |
| Core complexity | Higher | Lower | Medium |
| Tool blocking | Yes | Yes | Yes |
| Breaking changes | Moderate | Low | Minimal |
| Aligns with microkernel | No | Yes | Partial |
| Implementation effort | Medium | High | Low |
| Maintains existing APIs | Adapters | Yes | Yes |

---

## Discussion Points for Team

1. **Do we want a single unified mechanism (hooks) or prefer the current multi-mechanism approach?**
   - Unified: Simpler mental model, more powerful
   - Multi: Simpler for basic cases, progressive complexity

2. **Should lifecycle hooks replace or complement ContinuationCriteria/StateProcessors?**
   - Replace: Direction A
   - Complement: Hybrid (recommended)
   - Separate: Direction B

3. **What is the fate of the adapters?**
   - Currently unused - designed for a transition that never happened
   - Recommendation: Delete them

4. **Is the microkernel pattern a goal?**
   - User intuition suggests yes
   - Hybrid approach partially supports this

---

## Files Reference

### Core Files
- `/packages/agents/src/Agent/Agent.php` - Core orchestrator (467 lines)
- `/packages/agents/src/Agent/ToolExecutor.php` - Tool execution with hooks (271 lines)
- `/packages/agents/src/AgentBuilder/AgentBuilder.php` - Builder (722 lines)

### Hooks System (28 files)
```
packages/agents/src/Agent/Hooks/
├── Contracts/           # Hook, HookContext, HookMatcher
├── Data/                # HookEvent, HookOutcome, contexts
├── Stack/               # HookStack
├── Hooks/               # 10 concrete hook types
├── Matchers/            # 4 matchers
└── Adapters/            # 2 adapters (candidates for deletion)
```

### Existing Systems
- `/packages/agents/src/Agent/Continuation/` - ContinuationCriteria system
- `/packages/agents/src/Agent/StateProcessing/` - StateProcessors system
