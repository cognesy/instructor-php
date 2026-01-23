# UseHooks Peer Review - Round 2

Revision 2 addresses most concerns from Round 1. This review focuses on remaining high-impact issues that could block implementation or cause problems in production.

---

## 1. Critical: Type Mismatch in HookAction Interface

**Issue:** The interface declares `execute(): Result` but all downstream logic expects `HookOutcome`.

```php
interface HookAction
{
    public function execute(HookContext $context): Result;  // ← Returns Result
}
```

But HookRunner logic assumes direct `HookOutcome`:
> "If decision is Deny, Stop, or AskUser, return immediately."

**Impact:** This will cause runtime errors or require awkward unwrapping everywhere.

**Recommendation:** Choose one:

```php
// Option A: Return HookOutcome directly (simpler)
interface HookAction
{
    public function execute(HookContext $context): HookOutcome;
}
// Failures become HookOutcome::deny() with error message

// Option B: Return Result<HookOutcome> (explicit error handling)
interface HookAction
{
    /** @return Result<HookOutcome> */
    public function execute(HookContext $context): Result;
}
// HookRunner unwraps Result, handles Failure per continueOnFailure policy
```

Option A is simpler and aligns with how Claude Code works (exit codes + stdout map to outcomes). Option B is more PHP-idiomatic but adds ceremony.

---

## 2. Critical: AskUser Has No Completion Path

**Issue:** `AskUser` decision is mentioned but there's no mechanism for receiving the user's answer.

> "Emit a `PermissionRequested` event for external handling."

Events are fire-and-forget. The hook execution is blocked waiting for a decision, but there's no way for the event handler to provide one.

**Impact:** AskUser will be unusable in practice, or will silently degrade to Deny.

**Recommendation:** Define a synchronous permission provider:

```php
interface PermissionProvider
{
    /**
     * @return HookDecision::Allow or HookDecision::Deny
     */
    public function requestPermission(
        HookContext $context,
        string $reason,
    ): HookDecision;
}

// In HookPolicy:
final readonly class HookPolicy
{
    public function __construct(
        public ?PermissionProvider $permissionProvider = null,
        public HookDecision $askUserFallback = HookDecision::Deny,
        // ...
    ) {}
}

// HookRunner handles AskUser:
if ($outcome->decision === HookDecision::AskUser) {
    $finalDecision = $this->policy->permissionProvider
        ?->requestPermission($context, $outcome->reason)
        ?? $this->policy->askUserFallback;
}
```

For CLI: implement a `CliPermissionProvider` that prompts stdin.
For non-interactive: use `askUserFallback = Deny`.

---

## 3. High Impact: SubagentStop Integration Path Unclear

**Issue:** The document says "Add hook execution in SpawnSubagentTool after `finalStep()` returns" but doesn't explain how SpawnSubagentTool gets access to HookRunner.

Current capability install pattern:
```php
$builder->onBuild(function (Agent $agent) use ($runner) {
    // Can wrap toolExecutor, but can't inject into individual tools
});
```

SpawnSubagentTool is constructed during capability install, not during onBuild.

**Recommendation:** Two options:

**Option A:** Pass HookRunner to UseSubagents capability
```php
// UseSubagents accepts optional hook runner
class UseSubagents implements AgentCapability
{
    public function __construct(
        private readonly SubagentProvider $provider,
        private readonly ?HookRunner $hookRunner = null,  // ← inject
    ) {}
}

// Coordinate during builder setup
$hookRunner = new HookRunner($registry);
$builder
    ->withCapability(new UseHooks($registry))
    ->withCapability(new UseSubagents($provider, $hookRunner));
```

**Option B:** SpawnSubagentTool implements hook-aware interface
```php
interface HookAware
{
    public function withHookRunner(HookRunner $runner): static;
}

// onBuild injects into all HookAware tools
$builder->onBuild(function (Agent $agent) use ($runner) {
    $tools = $agent->tools()->map(fn($tool) =>
        $tool instanceof HookAware
            ? $tool->withHookRunner($runner)
            : $tool
    );
    return $agent->withTools($tools);
});
```

Option B is more flexible but requires tool modification.

---

## 4. High Impact: State Aggregation "Last Write Wins" Is Dangerous

**Issue:**
> "For updatedState, last write wins."

If Hook A sets `$state->metadata['safety_check'] = true` and Hook B sets `$state->metadata['audit_log'] = [...]`, Hook A's change is silently lost.

**Impact:** Unpredictable state mutations, hard-to-debug issues, security implications if safety hooks are overwritten.

**Recommendation:** Either:

**Option A:** Fail-fast on conflicting state updates
```php
// In HookRunner:
if ($priorOutcome->updatedState !== null && $outcome->updatedState !== null) {
    throw new ConflictingHookStateException(
        "Multiple hooks attempted state modification"
    );
}
```

**Option B:** Merge states intelligently
```php
// Define merge strategy
$mergedState = $this->stateMerger->merge(
    $priorOutcome->updatedState,
    $outcome->updatedState,
);
// Merger deep-merges metadata, appends messages, etc.
```

**Option C:** Disallow direct state mutation, only allow specific fields
```php
final readonly class HookOutcome
{
    // Instead of updatedState, provide specific mutation options
    public ?array $metadataToMerge = null;      // Merged into existing
    public ?Messages $messagesToAppend = null;  // Appended
    // No way to overwrite entire state
}
```

Option C is safest and most explicit.

---

## 5. Medium Impact: Missing Agent Identifiers in HookContext

**Issue:** HookContext doesn't include `agentId` or `parentAgentId`, but these exist in AgentState and are essential for:
- Multi-agent scenarios (which agent triggered the hook?)
- Logging and observability
- Subagent-specific hook filtering

**Recommendation:** Add explicit fields:

```php
final readonly class HookContext
{
    public function __construct(
        public string $agentId,                    // ← Add
        public ?string $parentAgentId,             // ← Add
        public HookEvent $event,
        public AgentState $state,
        // ...
    ) {}
}
```

Or provide accessors that delegate to state:
```php
public function agentId(): string {
    return $this->state->agentId;
}
```

---

## 6. Medium Impact: Hook Priority Tiebreaker Undefined

**Issue:**
> "Order by priority descending."

What happens when two hooks have equal priority? Execution order becomes non-deterministic, which violates the determinism goal.

**Recommendation:** Define explicit tiebreaker:

```php
// Sort by: priority DESC, then registration order ASC
usort($hooks, fn($a, $b) =>
    $b->priority <=> $a->priority
    ?: $a->registrationIndex <=> $b->registrationIndex
);
```

Add `registrationIndex` to HookRegistration (auto-assigned by registry).

---

## 7. Medium Impact: UserInput Hook Requires Significant Scaffolding

**Issue:**
> "Provide a session runner or facade that intercepts `withUserMessage()` to run UserInput hooks"

This is mentioned but not designed. UserInput hooks are in the v1 event list but depend on infrastructure that doesn't exist.

**Recommendation:** Either:

1. **Move UserInput to v2** - It requires a session runner that's out of scope for core agent hooks.

2. **Design the minimal wrapper now:**

```php
final class HookedAgentState
{
    public function __construct(
        private readonly AgentState $inner,
        private readonly HookRunner $hookRunner,
    ) {}

    public function withUserMessage(string|array $message): AgentState
    {
        $context = HookContext::forUserInput($this->inner, $message);
        $outcome = $this->hookRunner->run(HookEvent::UserInput, $context);

        if ($outcome->decision === HookDecision::Deny) {
            throw new UserInputRejectedException($outcome->reason);
        }

        $effectiveMessage = $outcome->transformedInput ?? $message;
        return $this->inner->withUserMessage($effectiveMessage);
    }
}
```

This can be optional and introduced when needed.

---

## 8. Low-Medium Impact: ExecutionStart Detection Is Fragile

**Issue:**
> "Use a preProcessor with a metadata marker to ensure it runs once per execution."

If metadata is cleared or the marker key collides, ExecutionStart may fire multiple times or not at all.

**Recommendation:** Use state's step count directly (already works):

```php
// In StepStartHookProcessor:
public function process(object $state, ?callable $next = null): object
{
    if ($state->stepCount() === 0) {
        $this->hookRunner->run(HookEvent::ExecutionStart, ...);
    }

    $this->hookRunner->run(HookEvent::StepStart, ...);

    return $next ? $next($state) : $state;
}
```

No marker needed - stepCount is reliable.

---

## 9. Answered Open Questions

The document lists open questions. Here are pragmatic answers:

> Should ShellCommandHookAction be allowed by default or gated by policy?

**Answer:** Gated by policy, disabled by default. Shell commands are a security risk (code injection, resource exhaustion). Require explicit opt-in:
```php
new HookPolicy(allowShellCommands: true)
```

> How should AskUser be routed in non-interactive environments?

**Answer:** See recommendation #2. Use `PermissionProvider` interface with fallback to Deny.

> Should hook actions be able to modify continuation criteria output directly?

**Answer:** No. Hooks should return decisions; HookContinuationCriterion translates to ContinuationEvaluation. Coupling hooks to criterion internals creates fragility.

> Do we want an async hook runner for observability-only hooks?

**Answer:** Defer to v2. For v1, observability hooks (ExecutionEnd, AgentFailed) already run via event listeners which can be async if the event bus supports it. Don't add async complexity to the core runner.

---

## Summary

| Issue | Severity | Recommendation |
|-------|----------|----------------|
| HookAction returns Result vs HookOutcome | Critical | Pick one, suggest HookOutcome directly |
| AskUser has no completion path | Critical | Add PermissionProvider interface |
| SubagentStop integration unclear | High | Pass HookRunner to UseSubagents |
| State aggregation "last write wins" | High | Use specific mutation fields, not full state |
| Missing agentId in context | Medium | Add or delegate to state |
| Priority tiebreaker undefined | Medium | Use registration order as tiebreaker |
| UserInput needs session runner | Medium | Move to v2 or design minimal wrapper |
| ExecutionStart marker fragile | Low-Medium | Use stepCount === 0 directly |

The revision is solid. Addressing the critical items (type mismatch, AskUser) and the high-impact state aggregation issue will make this ready for implementation.
