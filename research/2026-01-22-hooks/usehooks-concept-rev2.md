# UseHooks capability concept (rev2)

This revision integrates peer review feedback and aligns the design with existing
AgentBuilder patterns, the StepByStep processor model, and Claude Code parity.

## Goals

- Provide deterministic lifecycle hook points without changing core agent logic.
- Use existing extension points: processors, continuation criteria, tool executor, event bus.
- Support multiple hook action types: callable, shell command, prompt-based LLM.
## Hook events and integration map

Event set for v1 and v2, with mapping to existing pipeline points.

| HookEvent | Integration point | Notes |
| --- | --- | --- |
| ExecutionStart | preProcessor when `stepCount === 0` | once per `finalStep()` loop |
| ExecutionEnd | AgentFinished event listener | side effects only |
| StepStart | preProcessor | before driver inference |
| StepEnd | processor | after `performStep()` |
| PreToolUse | HookedToolExecutor | can block or mutate args |
| PostToolUse | HookedToolExecutor | can inject metadata/messages |
| Stop | continuation criterion | can force continuation |
| SubagentStop | SpawnSubagentTool | after subagent completes |
| UserInput | wrapper around `AgentState::withUserMessage()` | prompt validation |
| AgentFailed | AgentFailed event listener | side effects only |
| SessionStart | optional session runner | outside core agent |
| SessionEnd | optional session runner | outside core agent |

Notes:

- ExecutionEnd and AgentFailed are side-effect hooks because the core agent does
  not allow state mutation at that point without a wrapper.
- UserInput and SessionStart/End require a higher-level runner or facade that
  owns user input and session lifecycles.

## Core architecture

### HookAction and HookRegistration

Hook actions execute logic. Registrations bind actions to events with matcher,
priority, and timeout policy.

```php
interface HookAction
{
    public function execute(HookContext $context): Result;
}

final readonly class HookRegistration
{
    public function __construct(
        public HookEvent $event,
        public HookAction $action,
        public ?HookMatcher $matcher = null,
        public int $priority = 0,
        public ?int $timeoutSeconds = null,
        public bool $continueOnFailure = true,
    ) {}
}
```

### HookMatcher

Matchers are reusable and composable.

```php
interface HookMatcher
{
    public function matches(HookContext $context): bool;
}
```

### HookRegistry and HookRunner

Registry stores registrations per event. Runner executes in priority order,
applies timeouts, and aggregates outcomes with short-circuiting.

Execution rules:

- Order by priority descending.
- Skip when matcher fails.
- If hook fails and `continueOnFailure` is false, return deny.
- If decision is Deny, Stop, or AskUser, return immediately.
- Allow decisions can modify tool args or state and propagate to subsequent hooks.

## HookContext

Use a single context type with event-specific fields.

```php
final readonly class HookContext
{
    public function __construct(
        public HookEvent $event,
        public AgentState $state,
        public DateTimeImmutable $timestamp,
        public ?ToolCall $toolCall = null,
        public ?AgentExecution $toolExecution = null,
        public ?AgentStep $currentStep = null,
        public ?Throwable $exception = null,
        public ?string $userInput = null,
        public array $metadata = [],
    ) {}
}
```

Provide static constructors such as `forPreToolUse`, `forPostToolUse`,
`forStepStart`, `forStop`, and `forSubagentStop` to avoid ad-hoc field setup.

## HookOutcome and decisions

Hook decisions mirror Claude Code semantics with explicit "ask user" handling.

```php
enum HookDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Stop = 'stop';
    case Continue = 'continue';
    case AskUser = 'ask_user';
}

final readonly class HookOutcome
{
    public function __construct(
        public HookDecision $decision,
        public ?string $reason = null,
        public ?array $updatedToolArgs = null,
        public ?AgentState $updatedState = null,
        public ?string $additionalContext = null,
        public ?Messages $messagesToInject = null,
        public array $metadata = [],
    ) {}
}
```

`AskUser` is mapped by policy. Options:

- Treat as `Deny` with a reason when no UI is available.
- Emit a `PermissionRequested` event for external handling.

## Standard hook actions

Define a small set of action types to match Claude Code capabilities.

1. CallableHookAction
2. ShellCommandHookAction
3. PromptHookAction
4. SubagentHookAction

ShellCommandHookAction should accept JSON context on stdin and return JSON
outcomes via stdout, similar to Claude Code.

PromptHookAction should use a fast LLM preset and parse JSON output using a
response schema. Use StructuredOutput or a JSON schema response format.

## Matcher set

Baseline matchers for v1:

- ToolNameMatcher with exact match, wildcard, and regex patterns.
- StepTypeMatcher for FinalResponse vs ToolExecution.
- MetadataMatcher for presence or equality checks.
- CompositeMatcher with AND/OR composition.

## Integration points in detail

### StepStart and StepEnd

Use processors to hook into step lifecycle.

- StepStart runs before `$next`.
- StepEnd runs after `$next` and can inject messages or metadata.

### ExecutionStart

Use a preProcessor with a metadata marker to ensure it runs once per execution.

### PreToolUse and PostToolUse

Use a HookedToolExecutor wrapper that delegates to the inner executor.

Flow:

1. Build PreToolUse context.
2. Run hooks and apply updated args.
3. Execute tool call.
4. Run PostToolUse hooks with execution data.

### Stop

Use a continuation criterion that runs Stop hooks when stopping is evaluated.
If a hook returns Continue, return AllowContinuation with the hook reason.

### SubagentStop

Add hook execution in SpawnSubagentTool after `finalStep()` returns.
Allow hooks to update the returned summary or deny with an error string.

### UserInput

Provide a session runner or facade that intercepts `withUserMessage()` to run
UserInput hooks and update the state or block input.

### ExecutionEnd and AgentFailed

Run via event bus listeners to allow logging and cleanup. These hooks should
not mutate state in v1.

## Hook configuration sources

Support multiple sources with merge order:

- User settings
- Project settings
- Plugin hooks
- Skill or agent scoped hooks

Use a `HookConfigLoader` to merge registrations by priority and source order.

## Outcome aggregation policy

Recommended aggregation rules:

- First Deny or Stop wins.
- AskUser wins over Allow.
- For Allow with updatedToolArgs, later hooks see updated args.
- For updatedState, last write wins.
- `messagesToInject` are appended in registration order.
- `additionalContext` is concatenated with separators.

## Capability integration

`UseHooks` installs:

- StepStartHookProcessor and StepEndHookProcessor.
- StopHookCriterion.
- HookedToolExecutor via `AgentBuilder::onBuild`.
- Optional EventBusHookRunner wrapper for observability.

## Open questions for implementation

- Should ShellCommandHookAction be allowed by default or gated by policy?
- How should AskUser be routed in non-interactive environments?
- Should hook actions be able to modify continuation criteria output directly?
- Do we want an async hook runner for observability-only hooks?
