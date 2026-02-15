# Key Reference Files

## Monorepo Root

- **CONTRIBUTOR_GUIDE.md** - Development workflows, package management, and contribution guidelines
- **CONTENTS.md** - Project structure and package overview
- **README.md** - Main project documentation

## Individual Subpackages

Each package in `packages/` may contain:
- **README.md** - Package-specific documentation
- **OVERVIEW.md** - Package overview and architecture
- **INTERNALS.md** - Implementation details and internal structure
- **CHEATSHEET.md** - Quick reference for package usage

# Code Style

- Use strict types and type hints for arguments and return values everywhere
- Do not nest control structures (if, loops) beyond 1 level, exceptionally 2 levels
- Use `match` for complex conditionals - avoid `if`/`else if` chains or `switch` statements; avoid ternary operators
- Prefer immutable data structures and functional programming paradigms
- Avoid using arrays as collections - use dedicated collection classes
- Avoid exceptions for control flow - do not wrap everything in try/catch; either let exceptions bubble up or use monadic Cognesy\Utils\Result\Result for error handling if error needs to be handled on the same level
- Use namespaces and PSR-4 autoloading

## Agents Package Style

- Data classes are `readonly final class` — all properties readonly, all mutations return new instances via `with*()` methods
- Interfaces use `Can-` prefix (`CanUseTools`, `CanEmitAgentEvents`, `CanInterceptAgentLifecycle`)
- Hooks use `Hook` suffix (`StepsLimitHook`, `ErrorPolicyHook`)
- Accessors have no prefix: `state()`, `execution()`, `currentStep()` — not `getState()`
- Mutators use `with` prefix and return `self`: `withState()`, `withCurrentStep()`
- Named constructors for defaults: `::empty()`, `::fresh()`; for hydration: `::fromArray()`
- Minimal docblocks — only on class/interface declarations; methods are self-documenting via types and naming
- Method order: constructors → lifecycle/transitions → accessors → mutators → private helpers → serialization (`toArray`/`fromArray`)
- Enums are string-backed for serialization; put logic (priority, comparison) as methods on the enum itself
- Use `match(true) { ... }` for multi-condition branching
- Use `$this->field ?? Default::empty()` pattern ("ensure pattern") to provide defaults instead of null-checking

# Design Principles

- Always start with extremely simple code, refactor when it is required - do not over-engineer solutions (YAGNI)
- Use DDD (Domain Driven Design) principles - aggregate roots, value objects, entities, repositories, services
- Use Clean Code and SOLID principles
- Use interfaces for contracts, avoid concrete class dependencies
- Prefer using monadic designs for complex fragments of code - e.g. to avoid null checks and make the code cleaner and simpler.

## Agents Package Architecture

### Mental Model

- **AgentLoop** is a step-based iterator: each step = one LLM inference + tool execution cycle
- Loop lifecycle: `beforeExecution` → [`beforeStep` → `handleToolUse` → `afterStep` → check `shouldStop()`]* → `afterExecution`
- The loop yields intermediate states via `iterate()` (generator pattern) — callers can observe/persist between steps

### State Layering

- **AgentState** = session-level (persistent across executions): agentId, messages, metadata, optional `ExecutionState`
- **ExecutionState** = transient per-run: executionId, status, steps, continuation signals — null between executions
- **AgentStep** = immutable snapshot of one step: input/output messages, inference response, tool executions, errors
- **StepExecution** = wraps AgentStep with timing and continuation metadata — keeps step data immutable while tracking execution details
- State updates are always atomic — `withCurrentStepCompleted()` chains through the layers to prevent partial/inconsistent states
- `AgentLoop` auto-resets terminal executions (completed/failed) on entry — no manual `forNextExecution()` needed

### Stop/Continuation

- **StopSignal** = value object with reason (enum) + message + context + source
- **StopReason** enum has priority ordering for conflict resolution (ErrorForbade > StopRequested > StepsLimitReached > ...)
- **ExecutionContinuation** aggregates stop signals; `shouldStop()` = has signals AND no continuation override
- **AgentStopException** is a control-flow exception (not an error) — caught in loop, converted to StopSignal

### Invariant vs Variant Behavior

The boundary between `\Core` and `\Hook` is **whether the behavior is optional**:

- **Core state transitions** = invariant. Always happens, can't be removed, not configurable. Folded into `with*()` methods on state objects. Examples: `withCurrentStep()` routes step output to the correct message section; `withCurrentStepCompleted()` archives the step; `AgentLoop.ensureNextExecution()` auto-resets terminal executions on entry.
- **Hooks** = variant. Configurable, optional, composable via builder. Examples: step limits, token limits, summarization, finish reason stopping. If you could imagine an agent that doesn't need it, it's a hook.

**State transitions must be complete.** Every `with*()` call leaves the state fully consistent — no follow-up call required. If behavior always follows a transition, fold it into that transition. A separate "remember to also call X" method implies the behavior is optional; if it isn't optional, the separation is wrong.

**The litmus test:** if you're writing a hook that always runs, can't be removed, and has ordering dependencies with other hooks — it's a missing state transition, not a hook.

**AgentLoop is domain-agnostic.** It orchestrates lifecycle phases (beforeStep → handleToolUse → afterStep → shouldStop) but has zero knowledge of message sections, buffer routing, or output formats. Domain behavior lives in state objects (invariant) or hooks (variant), never in the loop.

### Hooks

- Hooks implement `CanInterceptAgentLifecycle` and receive/return `HookContext`
- **HookContext** carries state + trigger type + tool data; hooks mutate context (block tools, add errors, emit stop signals)
- Hook categories by convention: Guard hooks (priority 200, run first — check limits), Transform hooks (modify state), Block hooks (prevent tool execution), Decision hooks (policy-based retry/stop/ignore)
- **HookStack** chains hooks in priority order (higher = earlier); immutable — `with()` returns new stack

### Composition

- AgentBuilder composes: Tools + Driver + EventEmitter + HookStack + ToolExecutor → AgentLoop
- Many small interfaces (`CanUseTools`, `CanExecuteToolCalls`, `CanInterceptAgentLifecycle`, `CanEmitAgentEvents`) — one role each
- Events are readonly DTOs dispatched via event bus; emitter is a thin wrapper creating event objects from state
- Extend via hooks, not subclassing — AgentLoop is not designed for inheritance (test subclass `TestAgentLoop` is the sole exception)

# Tests and Quality

- Use Pest for testing
- Test only services and domain logic, not external dependencies or displayed information
- Before writing tests make sure that the code is easily testable - if not propose refactorings
- Use PHPStan and Psalm for static analysis

# Development Tools

- ast-grep - import documentation from: @./notes/tools/AST_GREP.md
- task-master - import documentation from: @./docs-internal/taskmaster.md
- tbd (to-be-done) - issue tracking system, see: @./docs-internal/tbd/tbd_cheatsheet.md


# Commit Message Guidelines

- Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) format
- Include a brief description of the change, its motivation, and any relevant issue references
- Never mention CLAUDE in commit messages or code comments

## Landing the Plane (Session Completion)

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd sync
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
