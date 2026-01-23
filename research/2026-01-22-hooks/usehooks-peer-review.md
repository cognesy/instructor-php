# UseHooks Capability Peer Review

This document provides a critical assessment of the `usehooks-concept.md` proposal, comparing it against Claude Code hooks capabilities and evaluating its fit within the existing InstructorPHP Agent framework.

## Executive Summary

The `usehooks-concept.md` document presents a solid foundation for adding hook support to the Agent runtime. However, several gaps exist when compared to Claude Code's mature hooks implementation, and the current design underutilizes existing framework patterns.

**Key Strengths:**
- Clear mapping to existing pipeline extension points
- Proper use of Result types for error handling
- Phased implementation approach

**Critical Gaps:**
- Missing 5 of 11 Claude Code hook events
- No prompt-based hooks (LLM evaluation)
- Limited matcher/filter capabilities
- No hook composition or priority system
- Missing decision control for continuation scenarios

**Recommendations:**
1. Expand event coverage to match critical Claude Code events
2. Design hook actions as interface implementations with standard adapters
3. Leverage existing event bus and processor patterns more fully
4. Add matcher system with regex and metadata filtering
5. Implement priority-based execution with short-circuit support

---

## 1. Hook Events Comparison

### Current Proposal vs Claude Code

| Event | usehooks-concept | Claude Code | Gap Analysis |
|-------|------------------|-------------|--------------|
| ExecutionStart | ✅ | SessionStart | Semantically different - CC has session lifecycle |
| StepStart | ✅ | — | Not in CC (closest: processor middleware) |
| StepEnd | ✅ | — | Not in CC (closest: processor middleware) |
| PreToolUse | ✅ | ✅ PreToolUse | Good match |
| PostToolUse | ✅ | ✅ PostToolUse | Good match |
| Stop | ✅ | ✅ Stop | Good match |
| AgentFailed | ✅ | — | CC doesn't have explicit failure hook |
| — | ❌ | ✅ UserPromptSubmit | Missing: input validation/transformation |
| — | ❌ | ✅ PermissionRequest | Missing: permission system integration |
| — | ❌ | ✅ SubagentStop | Missing: subagent lifecycle control |
| — | ❌ | ✅ Notification | Missing: notification interception |
| — | ❌ | ✅ PreCompact | N/A for PHP (no context compaction) |
| — | ❌ | ✅ Setup | Missing: initialization hooks |
| — | ❌ | ✅ SessionStart/End | Missing: session lifecycle |

### Missing Events - Priority Assessment

**High Priority (should add):**
1. **UserPromptSubmit** - Essential for input validation, context injection, and prompt transformation. The concept notes this gap but dismisses it. In PHP, this maps to `AgentState::withUserMessage()` wrapping.

2. **SubagentStop** - Critical for multi-agent orchestration. With `UseSubagents` capability, we need hooks to intercept subagent completion, validate results, or trigger follow-up actions.

3. **SessionStart/SessionEnd** - For multi-turn conversations, session lifecycle hooks enable context loading, cleanup, and state persistence.

**Medium Priority:**
4. **PermissionRequest** - While PreToolUse can approximate this, explicit permission hooks provide cleaner separation for safety-critical operations.

**Low Priority (not applicable):**
- PreCompact - No context window compaction in PHP runtime
- Notification - Depends on notification subsystem existence
- Setup - Could be handled via constructor/factory patterns

### Recommendation: Expanded Event Enum

```php
enum HookEvent: string
{
    // Execution lifecycle
    case ExecutionStart = 'execution_start';
    case ExecutionEnd = 'execution_end';

    // Step lifecycle
    case StepStart = 'step_start';
    case StepEnd = 'step_end';

    // Tool lifecycle
    case PreToolUse = 'pre_tool_use';
    case PostToolUse = 'post_tool_use';

    // Control flow
    case Stop = 'stop';
    case SubagentStop = 'subagent_stop';

    // Input handling
    case UserInput = 'user_input';  // UserPromptSubmit equivalent

    // Error handling
    case AgentFailed = 'agent_failed';

    // Session lifecycle (optional)
    case SessionStart = 'session_start';
    case SessionEnd = 'session_end';
}
```

---

## 2. Hook Architecture Analysis

### 2.1 Hook Interface Design

**Current Proposal:**
```php
interface Hook {
    public function event(): HookEvent;
    public function matches(HookContext $context): bool;
    public function handle(HookContext $context): Result<HookOutcome>;
}
```

**Issues:**
1. Mixing concerns: matching logic embedded in hook
2. No timeout handling
3. No priority support
4. Single event per hook (inflexible)

**Recommended Design:**

Separate the **action** (what to do) from the **registration** (when to do it):

```php
/**
 * The hook action - implements the actual logic
 */
interface HookAction
{
    /**
     * Execute the hook action with the provided context
     */
    public function execute(HookContext $context): HookOutcome;
}

/**
 * Hook registration - binds action to event with configuration
 */
final readonly class HookRegistration
{
    public function __construct(
        public HookEvent $event,
        public HookAction $action,
        public ?HookMatcher $matcher = null,
        public int $priority = 0,
        public ?int $timeout = null,
        public bool $continueOnFailure = true,  // fail-open by default
    ) {}
}

/**
 * Matcher for conditional hook execution
 */
interface HookMatcher
{
    public function matches(HookContext $context): bool;
}
```

This enables:
- Clean separation of matching vs execution
- Reusable matchers (ToolNameMatcher, MetadataMatcher, etc.)
- Standard actions (SubagentHookAction, ShellCommandHookAction, CallableHookAction)
- Priority-based execution ordering
- Per-hook timeout configuration

### 2.2 Standard Hook Actions

**Missing from concept:** Claude Code supports two hook types - command and prompt. The concept should define standard action types:

```php
/**
 * Execute a callable (closure, invokable class)
 */
final class CallableHookAction implements HookAction
{
    public function __construct(
        private readonly Closure|callable $callable,
    ) {}

    public function execute(HookContext $context): HookOutcome
    {
        return ($this->callable)($context);
    }
}

/**
 * Execute a shell command (Claude Code parity)
 */
final class ShellCommandHookAction implements HookAction
{
    public function __construct(
        private readonly string $command,
        private readonly ?int $timeout = 60,
    ) {}

    public function execute(HookContext $context): HookOutcome
    {
        // Serialize context to JSON, pipe to command, parse output
        // Match Claude Code's stdin/stdout/exit-code protocol
    }
}

/**
 * Execute via LLM evaluation (Claude Code prompt hooks)
 */
final class PromptHookAction implements HookAction
{
    public function __construct(
        private readonly string $prompt,
        private readonly ?string $llmPreset = null,
    ) {}

    public function execute(HookContext $context): HookOutcome
    {
        // Use fast LLM to evaluate prompt with context
        // Parse structured JSON response
    }
}

/**
 * Spawn subagent for complex hook logic
 */
final class SubagentHookAction implements HookAction
{
    public function __construct(
        private readonly string $agentSpec,
        private readonly ?SubagentProvider $provider = null,
    ) {}

    public function execute(HookContext $context): HookOutcome
    {
        // Spawn subagent with context, await result
    }
}
```

### 2.3 Hook Context Design

**Current Proposal:** Multiple context types (ExecutionContext, StepContext, ToolContext, FailureContext)

**Issues:**
1. Type proliferation - each context type needs separate handling
2. Missing common fields from Claude Code (session_id, transcript_path, permission_mode)
3. No extension mechanism

**Recommended Design:**

Single context type with event-specific data:

```php
final readonly class HookContext
{
    public function __construct(
        // Common fields (always present)
        public string $agentId,
        public ?string $parentAgentId,
        public HookEvent $event,
        public AgentState $state,
        public DateTimeImmutable $timestamp,

        // Event-specific data (nullable, populated based on event)
        public ?ToolCall $toolCall = null,
        public ?AgentExecution $toolExecution = null,
        public ?AgentStep $currentStep = null,
        public ?Throwable $exception = null,
        public ?string $userInput = null,

        // Metadata for matchers
        public array $metadata = [],
    ) {}

    // Convenience accessors
    public function toolName(): ?string { ... }
    public function toolArgs(): ?array { ... }
    public function stepIndex(): int { ... }
    public function isFirstStep(): bool { ... }
}
```

### 2.4 HookOutcome Design

**Current Proposal:**
```php
// decision: allow | deny | stop | continue
// updatedToolArgs: array or null
// updatedState: AgentState or null
// reason: string or null
```

**Issues:**
1. `allow` vs `continue` semantics unclear
2. Missing `ask` decision from Claude Code (prompt user)
3. No `additionalContext` injection
4. No support for message injection

**Recommended Design:**

```php
enum HookDecision: string
{
    case Allow = 'allow';           // Proceed normally
    case Deny = 'deny';             // Block the action
    case Stop = 'stop';             // Stop the agent
    case Continue = 'continue';     // Force continuation (for Stop hooks)
    case AskUser = 'ask_user';      // Prompt user for decision
}

final readonly class HookOutcome
{
    public function __construct(
        public HookDecision $decision,
        public ?string $reason = null,

        // Modifications (when decision is Allow)
        public ?array $updatedToolArgs = null,
        public ?AgentState $updatedState = null,
        public ?string $additionalContext = null,
        public ?Messages $messagesToInject = null,

        // Metadata for downstream hooks/processors
        public array $metadata = [],
    ) {}

    // Factory methods
    public static function allow(): self { ... }
    public static function deny(string $reason): self { ... }
    public static function stop(string $reason): self { ... }
    public static function continue(): self { ... }
    public static function withModifiedArgs(array $args): self { ... }
    public static function withContext(string $context): self { ... }
}
```

---

## 3. Integration Point Analysis

### 3.1 ToolExecutor Integration

**Current Proposal:** `HookedToolExecutor` wraps `ToolExecutor`

**Assessment:** Good approach, but should use composition over inheritance:

```php
final class HookedToolExecutor implements CanExecuteToolCalls
{
    public function __construct(
        private readonly CanExecuteToolCalls $inner,
        private readonly HookRunner $hookRunner,
    ) {}

    public function useTool(ToolCall $toolCall, AgentState $state): AgentExecution
    {
        // 1. Build PreToolUse context
        $context = HookContext::forPreToolUse($state, $toolCall);

        // 2. Run PreToolUse hooks
        $preOutcome = $this->hookRunner->run(HookEvent::PreToolUse, $context);

        // 3. Handle decision
        if ($preOutcome->decision === HookDecision::Deny) {
            return AgentExecution::denied($toolCall, $preOutcome->reason);
        }

        // 4. Apply modifications
        $effectiveCall = $preOutcome->updatedToolArgs
            ? $toolCall->withArgs($preOutcome->updatedToolArgs)
            : $toolCall;

        // 5. Execute tool
        $execution = $this->inner->useTool($effectiveCall, $state);

        // 6. Run PostToolUse hooks
        $postContext = HookContext::forPostToolUse($state, $execution);
        $postOutcome = $this->hookRunner->run(HookEvent::PostToolUse, $postContext);

        // 7. Return possibly enriched execution
        return $postOutcome->additionalContext
            ? $execution->withAddedContext($postOutcome->additionalContext)
            : $execution;
    }
}
```

### 3.2 Processor Integration

**Current Proposal:** `HookProcessor` as preProcessor

**Issue:** Processors operate on state transitions, not all hook events fit this model.

**Recommendation:** Use processor for StepStart/StepEnd hooks only. Other hooks should integrate at their natural extension points:

| Hook Event | Integration Point |
|------------|------------------|
| ExecutionStart | `StepByStep::finalStep()` entry |
| StepStart | PreProcessor |
| StepEnd | PostProcessor |
| PreToolUse | HookedToolExecutor |
| PostToolUse | HookedToolExecutor |
| Stop | ContinuationCriteria |
| SubagentStop | Subagent completion handler |
| UserInput | State mutation wrapper |
| AgentFailed | `onFailure()` override |

### 3.3 Continuation Criteria Integration

**Current Proposal:** `HookContinuationCriterion` for Stop hooks

**Issue:** Stop hooks in Claude Code can **block stopping** (force continuation). The current continuation system doesn't support this well.

**Recommendation:** Add a new decision type:

```php
// In ContinuationDecision enum, add:
case HookForbadeStop = 'hook_forbade_stop';  // Hook wants to continue despite criteria

// In HookContinuationCriterion:
public function evaluate(object $state): ContinuationEvaluation
{
    $context = HookContext::forStop($state);
    $outcome = $this->hookRunner->run(HookEvent::Stop, $context);

    return match ($outcome->decision) {
        HookDecision::Continue => new ContinuationEvaluation(
            decision: ContinuationDecision::AllowContinuation,
            reason: $outcome->reason ?? 'Hook requested continuation',
        ),
        default => new ContinuationEvaluation(
            decision: ContinuationDecision::AllowStop,
            reason: 'No hook blocked stopping',
        ),
    };
}
```

---

## 4. Missing Features from Claude Code

### 4.1 Matcher System

Claude Code supports regex matchers for tool names. The concept mentions "Tool name matchers" but doesn't detail implementation.

**Recommendation:**

```php
interface HookMatcher
{
    public function matches(HookContext $context): bool;
}

final class ToolNameMatcher implements HookMatcher
{
    public function __construct(
        private readonly string $pattern,  // Regex or exact match
    ) {}

    public function matches(HookContext $context): bool
    {
        $toolName = $context->toolName();
        if ($toolName === null) return false;

        // Check exact match first
        if ($this->pattern === $toolName || $this->pattern === '*') {
            return true;
        }

        // Try regex
        return (bool) preg_match('/' . $this->pattern . '/', $toolName);
    }
}

final class MetadataMatcher implements HookMatcher
{
    public function __construct(
        private readonly string $key,
        private readonly mixed $expectedValue = null,
    ) {}

    public function matches(HookContext $context): bool
    {
        $value = $context->metadata[$this->key] ?? null;
        return $this->expectedValue === null
            ? $value !== null
            : $value === $this->expectedValue;
    }
}

final class CompositeMatcher implements HookMatcher
{
    public function __construct(
        private readonly array $matchers,
        private readonly bool $requireAll = true,  // AND vs OR
    ) {}

    public function matches(HookContext $context): bool
    {
        foreach ($this->matchers as $matcher) {
            $result = $matcher->matches($context);
            if ($this->requireAll && !$result) return false;
            if (!$this->requireAll && $result) return true;
        }
        return $this->requireAll;
    }
}
```

### 4.2 Hook Configuration Sources

Claude Code supports hooks from multiple sources with precedence:
- User settings (`~/.claude/settings.json`)
- Project settings (`.claude/settings.json`)
- Plugin hooks
- Skill/agent frontmatter

**Missing from concept:** No discussion of configuration loading or precedence.

**Recommendation:** Add `HookConfigLoader` that supports:

```php
interface HookConfigSource
{
    public function load(): HookRegistrationCollection;
    public function priority(): int;  // Higher = later in merge
}

final class HookConfigLoader
{
    public function __construct(
        private readonly array $sources,  // HookConfigSource[]
    ) {}

    public function loadAll(): HookRegistry
    {
        $registry = new HookRegistry();

        // Sort by priority, load in order
        $sorted = $this->sortByPriority($this->sources);
        foreach ($sorted as $source) {
            $registrations = $source->load();
            $registry = $registry->merge($registrations);
        }

        return $registry;
    }
}
```

### 4.3 Hook Timeout Handling

Claude Code has per-hook timeouts (default 60s). The concept mentions timeouts in error handling but doesn't detail implementation.

**Recommendation:**

```php
final class HookRunner
{
    public function run(HookEvent $event, HookContext $context): HookOutcome
    {
        $hooks = $this->registry->forEvent($event);
        $aggregatedOutcome = HookOutcome::allow();

        foreach ($hooks as $registration) {
            if (!$this->matchesContext($registration, $context)) {
                continue;
            }

            try {
                $outcome = $this->executeWithTimeout(
                    $registration->action,
                    $context,
                    $registration->timeout ?? $this->defaultTimeout,
                );

                $aggregatedOutcome = $this->aggregateOutcome(
                    $aggregatedOutcome,
                    $outcome,
                );

                // Short-circuit on deny/stop
                if ($outcome->decision->isTerminal()) {
                    return $aggregatedOutcome;
                }
            } catch (TimeoutException $e) {
                if (!$registration->continueOnFailure) {
                    return HookOutcome::deny("Hook timed out: " . $e->getMessage());
                }
                // Log and continue
            }
        }

        return $aggregatedOutcome;
    }
}
```

### 4.4 Prompt-Based Hooks

Claude Code's prompt hooks use a fast LLM (Haiku) for intelligent decisions. This is **completely missing** from the concept.

**Recommendation:** Add `PromptHookAction` (see section 2.2) with:

```php
final class PromptHookAction implements HookAction
{
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private readonly string $prompt,
        private readonly ?string $llmPreset = 'haiku',  // Fast model
        private readonly ?int $timeout = self::DEFAULT_TIMEOUT,
    ) {}

    public function execute(HookContext $context): HookOutcome
    {
        $expandedPrompt = $this->expandPrompt($context);

        $llm = LLMProvider::using($this->llmPreset ?? 'haiku');
        $response = $llm->chat()
            ->withMessages([
                ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                ['role' => 'user', 'content' => $expandedPrompt],
            ])
            ->withResponseFormat($this->responseSchema())
            ->create();

        return $this->parseResponse($response);
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'hook_decision',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ok' => ['type' => 'boolean'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['ok'],
                ],
            ],
        ];
    }
}
```

---

## 5. Underutilized Existing Features

### 5.1 Event Bus

The concept mentions using the event bus but doesn't fully leverage it.

**Current:** "Emits Hook events (optional) for observability"

**Underutilization:** The existing event bus could:
- Dispatch `HookExecuted` events for observability
- Allow external hook registration via event listeners
- Support async hook execution via event queue

**Recommendation:**

```php
// Emit events for each hook execution
final class ObservableHookRunner implements HookRunner
{
    public function __construct(
        private readonly HookRunner $inner,
        private readonly CanHandleEvents $events,
    ) {}

    public function run(HookEvent $event, HookContext $context): HookOutcome
    {
        $this->events->dispatch(new HookExecutionStarted(
            event: $event,
            context: $context,
        ));

        $outcome = $this->inner->run($event, $context);

        $this->events->dispatch(new HookExecutionCompleted(
            event: $event,
            context: $context,
            outcome: $outcome,
        ));

        return $outcome;
    }
}
```

### 5.2 Existing Processor Pattern

The concept proposes new classes when existing patterns could be extended.

**Underutilization:** Processors already support:
- Pre/post processing (via `addPreProcessor`/`addProcessor`)
- State transformation
- Middleware composition

**Recommendation:** Hook-enabled processors that delegate to HookRunner:

```php
final class StepStartHookProcessor implements CanProcessAnyState
{
    public function __construct(
        private readonly HookRunner $hookRunner,
    ) {}

    public function canProcess(object $state): bool
    {
        return $state instanceof AgentState;
    }

    public function process(object $state, ?callable $next = null): object
    {
        // Run StepStart hooks BEFORE calling next
        $context = HookContext::forStepStart($state);
        $outcome = $this->hookRunner->run(HookEvent::StepStart, $context);

        // Apply state modifications
        $modifiedState = $outcome->updatedState ?? $state;

        // Continue chain
        $resultState = $next ? $next($modifiedState) : $modifiedState;

        return $resultState;
    }
}
```

### 5.3 Continuation Criteria Pattern

**Underutilization:** The concept proposes `HookContinuationCriterion` but doesn't leverage the full `ContinuationEvaluation` capabilities.

**Recommendation:** Full integration with evaluation context:

```php
final class StopHookCriterion implements CanEvaluateContinuation
{
    public function __construct(
        private readonly HookRunner $hookRunner,
    ) {}

    public function evaluate(object $state): ContinuationEvaluation
    {
        // Only run Stop hooks when we're about to stop
        // This is evaluated after all other criteria

        $context = HookContext::forStop($state);
        $outcome = $this->hookRunner->run(HookEvent::Stop, $context);

        return match ($outcome->decision) {
            HookDecision::Continue => new ContinuationEvaluation(
                decision: ContinuationDecision::AllowContinuation,
                reason: $outcome->reason ?? 'Stop hook requested continuation',
                context: [
                    'hook_triggered' => true,
                    'hook_reason' => $outcome->reason,
                ],
            ),
            HookDecision::Stop => new ContinuationEvaluation(
                decision: ContinuationDecision::ForbidContinuation,
                reason: $outcome->reason ?? 'Stop hook forced termination',
                stopReason: StopReason::HookForbade,
            ),
            default => new ContinuationEvaluation(
                decision: ContinuationDecision::AllowStop,
                reason: 'No stop hook intervention',
            ),
        };
    }
}
```

### 5.4 Capability Pattern

The concept mentions `UseHooks` capability but doesn't detail how it composes with other capabilities.

**Recommendation:** Full capability implementation:

```php
final class UseHooks implements AgentCapability
{
    public function __construct(
        private readonly HookRegistry $registry,
        private readonly ?HookPolicy $policy = null,
    ) {}

    public function install(AgentBuilder $builder): void
    {
        $runner = new HookRunner(
            registry: $this->registry,
            policy: $this->policy ?? HookPolicy::default(),
        );

        // StepStart/StepEnd via processors
        $builder->addPreProcessor(new StepStartHookProcessor($runner));
        $builder->addProcessor(new StepEndHookProcessor($runner));

        // Stop hooks via continuation criteria
        $builder->addContinuationCriteria(new StopHookCriterion($runner));

        // PreToolUse/PostToolUse via ToolExecutor wrapper
        $builder->onBuild(function (Agent $agent) use ($runner) {
            $hookedExecutor = new HookedToolExecutor(
                inner: $agent->toolExecutor(),
                hookRunner: $runner,
            );
            return $agent->with(toolExecutor: $hookedExecutor);
        });
    }
}
```

---

## 6. Implementation Recommendations

### Phase 1: Core Infrastructure (Week 1-2)
1. Define `HookEvent` enum with initial events
2. Implement `HookAction` interface and `CallableHookAction`
3. Implement `HookContext` with common + event-specific data
4. Implement `HookOutcome` with decision types
5. Implement basic `HookRunner` with sequential execution
6. Implement `HookRegistry` with event-based lookup

### Phase 2: Tool Hooks (Week 2-3)
1. Implement `HookedToolExecutor` wrapper
2. Add `ToolNameMatcher` for PreToolUse/PostToolUse filtering
3. Wire into `UseHooks` capability
4. Add tests for allow/deny/modify scenarios

### Phase 3: Step & Stop Hooks (Week 3-4)
1. Implement `StepStartHookProcessor` and `StepEndHookProcessor`
2. Implement `StopHookCriterion`
3. Add ExecutionStart hook support
4. Test continuation control scenarios

### Phase 4: Advanced Features (Week 4-5)
1. Add `ShellCommandHookAction` for Claude Code parity
2. Add `PromptHookAction` for LLM-evaluated hooks
3. Implement timeout handling
4. Add priority-based execution ordering

### Phase 5: Configuration & Polish (Week 5-6)
1. Implement hook configuration loading
2. Add `SubagentStop` hook support
3. Add `UserInput` hook support
4. Documentation and examples

---

## 7. Open Questions

1. **Hook isolation:** Should hooks run in isolated context to prevent state corruption? What about shared resources?

2. **Async hooks:** Should some hooks (especially observability) run asynchronously to avoid blocking?

3. **Hook composition:** How should multiple hooks' outcomes be merged? First-deny-wins? Last-write-wins for modifications?

4. **Error propagation:** When a hook fails, should the error include the hook's identity for debugging?

5. **Testing hooks:** How do we support testing hook logic in isolation?

6. **Performance:** What's the acceptable overhead per hook execution? Should we batch hook calls?

---

## 8. Summary

The `usehooks-concept.md` provides a reasonable starting point but needs expansion to achieve parity with Claude Code and full integration with InstructorPHP patterns.

**Must-haves for v1:**
- PreToolUse, PostToolUse, Stop hooks
- HookAction interface with CallableHookAction
- Basic matcher support
- Integration via UseHooks capability

**Should-haves for v1:**
- StepStart, StepEnd hooks
- ToolNameMatcher
- Timeout handling
- Priority-based execution

**Nice-to-haves (v2):**
- PromptHookAction (LLM evaluation)
- ShellCommandHookAction
- UserInput, SubagentStop hooks
- Configuration file loading
- Async/queued execution

The key insight is that hooks should provide **deterministic control points** in the agent lifecycle - guaranteeing certain computations happen at specific moments, beyond what prompting alone can achieve.
