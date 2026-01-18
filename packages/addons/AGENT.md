# Agent System

Tool-calling agent with iterative execution, state management, and extensible capabilities.

## Quick Start

```php
use Cognesy\Addons\Agent\AgentBuilder;use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;use Cognesy\Addons\Agent\Core\Data\AgentState;use Cognesy\Messages\Messages;

// Create agent with tools using builder
$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools('/path/to/project'))
    ->withCapability(new UseTaskPlanning())
    ->build();

// Initialize state with user message
$state = AgentState::empty()->withMessages(
    Messages::fromString('Read config.php and update the debug flag to true')
);

// Run to completion
$finalState = $agent->finalStep($state);
echo $finalState->currentStep()->outputMessages()->toString();
```

## Building Agents

Use `AgentBuilder` for composable agent configuration with fluent API. The builder uses **Capabilities** to add modular features to the agent.

```php
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;

// Basic agent with custom tools
$agent = AgentBuilder::base()
    ->withTools($tools)
    ->build();

// Agent with file operations
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools('/path/to/project'))
    ->build();

// Using withCapability for explicit configuration
$agent = AgentBuilder::base()
    ->withCapability(new UseBash(baseDir: '/project'))
    ->withCapability(new UseFileTools(baseDir: '/project'))
    ->build();

// Full-featured coding agent
$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools('/project'))
    ->withCapability(new UseTaskPlanning())
    ->withCapability(new UseSkills($library))
    ->withCapability(UseSubagents::withDepth(3, $registry, summaryMaxChars: 8000))
    ->withSystemPrompt('You are a code reviewer. Be concise and actionable.')
    ->withMaxSteps(20)
    ->withMaxTokens(32768)
    ->withTimeout(300)
    ->withLlmPreset('anthropic')
    ->build();
```

### Builder Methods

| Method | Description |
|--------|-------------|
| `base()` | Create a new builder instance with sane defaults |
| `new()` | Create a completely blank builder instance |
| `withCapability($capability)` | Apply an `AgentCapability` (e.g., `UseBash`, `UseSkills`) |
| `withTools($tools)` | Add custom tools |
| `addProcessor($processor)` | Add a state processor |
| `addContinuationCriteria($criteria)` | Add a stop condition |
| `withMaxSteps($n)` | Set maximum execution steps |
| `withMaxTokens($n)` | Set token usage limit |
| `withTimeout($seconds)` | Set execution time limit |
| `withMaxRetries($n)` | Set retry limit on errors |
| `withLlmPreset($preset)` | Use LLM preset from config |
| `withDriver($driver)` | Use custom tool-calling driver |
| `withEvents($eventBus)` | Set event handler |
| `withCachedContext($cache)` | Set cached context for stable prompt parts |
| `withSystemPrompt($prompt)` | Add system prompt to cached context |
| `withResponseFormat($format)` | Set cached response format (applied when request format is empty) |

### Cached Context

Cached context is used for stable prompt parts that should persist across steps and benefit from provider prompt caching.

```php
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Polyglot\Inference\Data\CachedContext;

$cache = new CachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You are a precise assistant.'],
    ],
);

$agent = AgentBuilder::base()
    ->withCachedContext($cache)
    ->build();
```

Shorthand convenience methods:

```php
$agent = AgentBuilder::base()
    ->withSystemPrompt('You are a precise assistant.')
    ->withResponseFormat(['type' => 'json_object'])
    ->build();
```

## Capabilities

Capabilities are modular features that can be added to an agent. They can register tools, add state processors, or define continuation criteria.

| Capability | Namespace | Description |
|------------|-----------|-------------|
| `UseBash` | `...\Bash` | Adds `BashTool` for command execution |
| `UseFileTools` | `...\File` | Adds `ReadFileTool`, `WriteFileTool`, `EditFileTool` |
| `UseTaskPlanning` | `...\Tasks` | Adds `TodoWriteTool` and task tracking processors |
| `UseSkills` | `...\Skills` | Adds `LoadSkillTool` and skill metadata processor |
| `UseSubagents` | `...\Subagent` | Adds `SpawnSubagentTool` for nested execution |
| `UseSelfCritique` | `...\SelfCritique` | Adds `SelfCriticProcessor` and evaluation loop |
| `UseMetadataTools` | `...\Metadata` | Adds scratchpad for storing data between tool calls |
| `UseStructuredOutputs` | `...\StructuredOutput` | Adds LLM-powered structured data extraction |

(Note: `...` represents `Cognesy\Addons\Agent\Capabilities`)

### Example: Using Self-Critique
```php
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\UseSelfCritique;

$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseSelfCritique(maxIterations: 3))
    ->build();
```

### Example: Using Metadata Tools
```php
use Cognesy\Addons\Agent\Capabilities\Metadata\UseMetadataTools;
use Cognesy\Addons\Agent\Capabilities\Metadata\MetadataPolicy;

// Basic usage - agent gets scratchpad for storing intermediate results
$agent = AgentBuilder::base()
    ->withCapability(new UseMetadataTools())
    ->build();

// Custom policy with limits
$policy = new MetadataPolicy(maxKeys: 50, maxValueSizeBytes: 65536);
$agent = AgentBuilder::base()
    ->withCapability(new UseMetadataTools($policy))
    ->build();
```

### Example: Using Structured Outputs
```php
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\UseStructuredOutputs;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaRegistry;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaDefinition;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\StructuredOutputPolicy;

// Register schemas for extraction
$schemas = new SchemaRegistry();
$schemas->register('lead', LeadForm::class);
$schemas->register('contact', new SchemaDefinition(
    class: ContactForm::class,
    prompt: 'Extract contact information, prioritizing email and phone',
    maxRetries: 5,
));

$agent = AgentBuilder::base()
    ->withCapability(new UseStructuredOutputs(
        schemas: $schemas,
        policy: new StructuredOutputPolicy(llmPreset: 'anthropic'),
    ))
    ->build();
```

## Execution Patterns

### Final Result
```php
$finalState = $agent->finalStep($state);
```

### Step-by-Step
```php
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    echo "Step {$state->stepCount()}: {$state->currentStep()->stepType()->value}\n";
}
```

### Iterator
```php
foreach ($agent->iterator($state) as $currentState) {
    $step = $currentState->currentStep();
    // Process each step
}
```

## Built-in Tools

### BashTool
Execute shell commands via sandboxed process.
```php
use Cognesy\Addons\Agent\Capabilities\Bash\BashTool;
use Cognesy\Addons\Agent\Capabilities\Bash\BashPolicy;

$bashPolicy = new BashPolicy(maxOutputChars: 50000, headChars: 8000, tailChars: 40000);
new BashTool(baseDir: '/tmp', timeout: 120, outputPolicy: $bashPolicy);
```
Network access is disabled by default; pass a custom `ExecutionPolicy` to enable it.
Output is truncated with head/tail sampling when it exceeds `maxOutputChars`.

### ReadFileTool
Read file contents with line numbers.
```php
ReadFileTool::inDirectory('/path'); // or ::withPolicy($policy)
```

### WriteFileTool
Create/overwrite files.
```php
WriteFileTool::inDirectory('/path');
```

### EditFileTool
Replace strings in files.
```php
EditFileTool::inDirectory('/path');
```

### SearchFilesTool
Search for files by name/path pattern (not content).
```php
SearchFilesTool::inDirectory('/path', maxResults: 10);
```
Supports glob patterns: `*.php`, `**/*.php`, `src/**/*.ts`, and substring search.

### ListDirTool
List directory contents with file/directory markers.
```php
ListDirTool::inDirectory('/path', maxEntries: 50);
```

### TodoWriteTool
Structured task management.
- Max 20 tasks
- Only 1 task can be `in_progress` at a time
- Required fields: `content`, `status`, `activeForm`
Limits and render cadence can be customized via `TodoPolicy` passed to `withTaskPlanning()`.

```php
use Cognesy\Addons\Agent\Capabilities\Tasks\TodoPolicy;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Registry\AgentRegistry;

$policy = new TodoPolicy(
    maxItems: 25,
    maxInProgress: 2,
    renderEverySteps: 10,
    reminderEverySteps: 10,
);
$agent = AgentBuilder::base()
    ->withCapability(new UseTaskPlanning($policy))
    ->build();

$registry = new AgentRegistry();
$subagentPolicy = new SubagentPolicy(maxDepth: 3, summaryMaxChars: 12000);
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents($registry, $subagentPolicy))
    ->build();
```

### MetadataReadTool / MetadataWriteTool / MetadataListTool
Scratchpad for storing data between tool calls.
```php
use Cognesy\Addons\Agent\Capabilities\Metadata\MetadataWriteTool;
use Cognesy\Addons\Agent\Capabilities\Metadata\MetadataReadTool;
use Cognesy\Addons\Agent\Capabilities\Metadata\MetadataListTool;

// Usually added via UseMetadataTools capability
// Tools: metadata_write, metadata_read, metadata_list
```
Useful for multi-step workflows where one tool produces data another needs.

### StructuredOutputTool
Extract structured data from unstructured text using Instructor.
```php
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\StructuredOutputTool;
use Cognesy\Addons\Agent\Capabilities\StructuredOutput\SchemaRegistry;

// Usually added via UseStructuredOutputs capability
// Tool: extract_data
```
Extracts data into registered schema classes with optional storage to metadata.

### LoadSkillTool
Load SKILL.md files on-demand.
```php
LoadSkillTool::withLibrary(new SkillLibrary('./skills'));
```

### SpawnSubagentTool
Spawn isolated subagents from registered agent specifications.
```php
use Cognesy\Addons\Agent\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Addons\Agent\Registry\AgentRegistry;

// Usually added via UseSubagents capability with AgentRegistry
// The registry defines available subagent types and their tools/prompts
```

### LlmQueryTool
Send queries to the LLM for knowledge questions, reasoning, or text generation.
```php
new LlmQueryTool(); // Uses default LLM
LlmQueryTool::using('openai'); // Uses specific preset
```
Note: This tool is **opt‑in** and not included in default agent builds. It creates a nested LLM call inside the agent loop, which adds cost and latency. Prefer the main agent loop unless you have a specialized workflow.

## Configuration

### Continuation Criteria
Controls when agent stops:
```php
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;

$criteria = new ContinuationCriteria(
    new StepsLimit(20, fn(AgentState $state) => $state->stepCount()),
    new TokenUsageLimit(32768, fn(AgentState $state) => $state->usage()->total()),
    new ExecutionTimeLimit(300, fn(AgentState $state) => $state->executionStartedAt() ?? $state->startedAt()),
    ErrorPolicyCriterion::withPolicy(ErrorPolicy::retryToolErrors(3)),
);

// Build agent with custom criteria
$agent = AgentBuilder::base()
    ->withTools($tools)
    ->addContinuationCriteria($customCriteria)
    ->build();
```
Custom criteria can implement `CanProvideStopReason` to set explicit `stopReason` values in `ContinuationOutcome`.

### State Processors
Modify state after each step:

```php
$agent = AgentBuilder::base()
    ->withTools($tools)
    ->addProcessor(new MyCustomProcessor())
    ->build();
```

### Custom Driver
```php
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\LLMProvider;

$driver = new ToolCallingDriver(
    llm: LLMProvider::new()->withConnection('anthropic'),
    model: 'claude-sonnet-4-20250514',
);

$agent = AgentBuilder::base()
    ->withDriver($driver)
    ->build();
```

### Sandbox Policy
```php
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;

$policy = ExecutionPolicy::in('/project')
    ->withTimeout(120)
    ->withNetwork(true)
    ->withReadablePaths('/project', '/usr/share')
    ->withWritablePaths('/project/output')
    ->inheritEnvironment();

$agent = AgentBuilder::base()
    ->withCapability(new UseBash(policy: $policy))
    ->build();
```

## Agent Registry

The `AgentRegistry` manages named agent specifications that can be spawned as subagents. Define agents with specific tools, prompts, and models.

### AgentSpec
```php
use Cognesy\Addons\Agent\Registry\AgentSpec;
use Cognesy\Addons\Agent\Registry\AgentRegistry;

// Define an agent specification
$spec = new AgentSpec(
    name: 'code-reviewer',
    description: 'Reviews code for quality and suggests improvements',
    systemPrompt: 'You are a code reviewer. Analyze code for bugs, style issues, and improvements.',
    tools: ['read_file', 'search_files'],  // null = inherit all parent tools
    model: 'anthropic',  // preset name, 'inherit', or LLMConfig object
    skills: ['code-review'],
);

$registry = new AgentRegistry();
$registry->register($spec);
```

### Loading from Markdown Files
```php
// Load single agent spec from markdown file
$registry->loadFromFile('/path/to/agent.md');

// Load all agents from directory
$registry->loadFromDirectory('/path/to/agents', recursive: true);

// Auto-discover from standard locations:
// - Project: .claude/agents/
// - Package: vendor/cognesy/instructor-php/agents/
// - User: ~/.instructor-php/agents/
$registry->autoDiscover();
```

## Agent Contracts (Laravel-Friendly)

For queue workers and jobs, use deterministic agent classes implementing `AgentContract`.
These expose self‑description plus execution and can be instantiated without serialization.

### AgentContract Interface

```php
interface AgentContract
{
    public function descriptor(): AgentDescriptor;
    public function build(): Agent;
    public function run(AgentState $state): AgentState;
    public function nextStep(object $state): object;
    public function hasNextStep(object $state): bool;
    public function finalStep(object $state): object;
    public function iterator(object $state): iterable;
    public function withEventHandler($events): self;
    public function wiretap(?callable $listener): self;
    public function onEvent(string $class, ?callable $listener): self;
}
```

### AbstractAgentDefinition

Base class that implements `AgentContract` with event handling and lazy agent building.

```php
use Cognesy\Addons\Agent\Definitions\AbstractAgentDefinition;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;

class CodeAssistantAgent extends AbstractAgentDefinition
{
    public function __construct(
        private readonly string $workspace,
    ) {}

    #[\Override]
    public function descriptor(): AgentDescriptor {
        return new AgentDescriptor(
            name: 'code-assistant',
            description: 'Assists with code review and refactoring',
            tools: NameList::from(['read_file', 'write_file', 'bash']),
            capabilities: NameList::from(['file-ops', 'shell-access']),
        );
    }

    #[\Override]
    protected function buildAgent(): Agent {
        return AgentBuilder::base()
            ->withCapability(new UseFileTools($this->workspace))
            ->withCapability(new UseBash())
            ->withMaxSteps(15)
            ->build();
    }
}
```

### AgentContractRegistry

Registry for agent contract classes, enabling dynamic instantiation.

```php
use Cognesy\Addons\Agent\Registry\AgentContractRegistry;

$registry = new AgentContractRegistry();
$registry = $registry->register('code-assistant', \App\Agents\CodeAssistantAgent::class);

// Create instance with constructor args
$result = $registry->create('code-assistant', ['workspace' => '/var/app']);
// $result is Result<AgentContract>

if ($result->isSuccess()) {
    $agent = $result->unwrap();
    $descriptor = $agent->descriptor();
    $finalState = $agent->run($initialState);
}
```

### AgentFactory Interface

Implement this for custom agent creation logic.

```php
use Cognesy\Addons\Agent\Contracts\AgentFactory;
use Cognesy\Utils\Result\Result;

class MyAgentFactory implements AgentFactory
{
    public function create(string $agentName, array $config = []): Result {
        return match ($agentName) {
            'code-assistant' => Result::success(new CodeAssistantAgent($config['workspace'])),
            'researcher' => Result::success(new ResearcherAgent($config['sources'])),
            default => Result::failure("Unknown agent: {$agentName}"),
        };
    }
}
```

### Event Hooks

Agent contract instances support the same event hooks as Inference/StructuredOutput:

```php
$agent->wiretap(fn($e) => logger()->info($e));
$agent->onEvent(\Cognesy\Addons\Agent\Events\AgentStepCompleted::class, fn($e) => dump($e));
```

### Agent Markdown Format
```markdown
---
name: code-reviewer
description: Reviews code for quality issues
tools: [read_file, search_files]
model: anthropic
skills: [code-review]
---

You are a code reviewer. Analyze code for:
1. Bugs and logic errors
2. Style consistency
3. Performance issues
```

## Skills

SKILL.md format:
```markdown
---
name: skill-name
description: When to use this skill
tags: [php, testing]
resources: [src/Example.php]
---

# Skill Content

Instructions, examples, patterns...
```

Load skills:
```php
use Cognesy\Addons\Agent\Capabilities\Skills\SkillLibrary;

$library = new SkillLibrary('./skills');
$skill = $library->get('skill-name');
echo $skill->content();
```

## State & Steps

### AgentState
Immutable container for conversation and execution history.
```php
$state = AgentState::empty()
    ->withMessages($messages)
    ->withMetadata($metadata);

$state->stepCount();        // Number of steps
$state->currentStep();      // Latest AgentStep
$state->stepAt(0);          // Step by index
$state->messages();         // All messages
$state->usage();            // Token usage
$state->metadata();         // Key-value store
$state->recordStep($step);  // Add step + stamp start time
$state->failWith($error);   // Mark failed + record failure step
```
AgentState implements optional execution hook interfaces (`CanMarkExecutionStarted`, `CanMarkStepStarted`, `CanTrackExecutionTime`) used by StepByStep for timing and lifecycle tracking.

### AgentStep
Single execution step with inference response and tool results.
```php
$step->stepType();          // ToolExecution | FinalResponse | Error
$step->outputMessages();    // LLM response messages
$step->hasToolCalls();      // Whether tools were called
$step->toolCalls();         // ToolCalls collection
$step->toolExecutions();    // Execution results
$step->usage();             // Step token usage
$step->hasErrors();         // Whether step failed
```

## Events

```php
use Cognesy\Events\EventBus;

$events = new EventBus();
$events->onEvent(AgentStepCompleted::class, function($event) {
    echo "Step completed: " . json_encode($event->payload()) . "\n";
});

$agent = AgentBuilder::base()
    ->withEvents($events)
    ->build();
```

| Event | Payload |
|-------|---------|
| `AgentStepStarted` | agentId, stepNumber, messageCount, availableTools |
| `AgentStepCompleted` | stepNumber, hasToolCalls, errorCount, usage, finishReason, durationMs |
| `AgentStateUpdated` | agentId, status, stepCount |
| `AgentFinished` | agentId, status, totalSteps, totalUsage |
| `AgentFailed` | agentId, exception, status, stepsCompleted |
| `ToolCallStarted` | tool, args, startedAt |
| `ToolCallCompleted` | tool, success, error, startedAt, endedAt |
| `ContinuationEvaluated` | agentId, stepNumber, outcome |
| `TokenUsageReported` | agentId, operation, usage |

## Broadcasting

The broadcasting system enables real-time UI updates by adapting agent events to a broadcast-friendly envelope format. Use `AgentEventEnvelopeAdapter` to broadcast events over WebSockets, Pusher, Laravel Reverb, or any custom transport.

### Quick Start

```php
use Cognesy\Addons\Agent\Broadcasting\AgentEventEnvelopeAdapter;
use Cognesy\Addons\Agent\Broadcasting\BroadcastConfig;

// Create adapter
$adapter = new AgentEventEnvelopeAdapter(
    broadcaster: $myBroadcaster,  // Implements CanBroadcastAgentEvents
    sessionId: $sessionId,
    executionId: $executionId,
);

// Wire to agent (single line!)
$agent->wiretap($adapter->wiretap());

// Run agent - all events automatically broadcast
$result = $agent->run($state);
```

### Configuration Presets

```php
use Cognesy\Addons\Agent\Broadcasting\BroadcastConfig;

// Minimal: status events only (no streaming)
$adapter = new AgentEventEnvelopeAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::minimal(),
);

// Standard: status + streaming (default)
$adapter = new AgentEventEnvelopeAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::standard(),
);

// Debug: everything including continuation trace and full tool args
$adapter = new AgentEventEnvelopeAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::debug(),
);

// Custom configuration
$adapter = new AgentEventEnvelopeAdapter(
    broadcaster: $broadcaster,
    sessionId: $sessionId,
    executionId: $executionId,
    config: new BroadcastConfig(
        includeStreamChunks: true,
        includeContinuationTrace: false,
        includeToolArgs: true,
        maxArgLength: 200,
        autoStatusTracking: true,
    ),
);
```

### Broadcast Events

| Event Type | Trigger | Payload |
|------------|---------|---------|
| `agent.status` | Lifecycle transitions | `{status, previous_status}` |
| `agent.step.started` | AgentStepStarted | `{step_number, message_count, available_tools}` |
| `agent.step.completed` | AgentStepCompleted | `{step_number, has_tool_calls, errors, finish_reason, usage, duration_ms}` |
| `agent.tool.started` | ToolCallStarted | `{tool_name, tool_call_id, args_summary}` |
| `agent.tool.completed` | ToolCallCompleted | `{tool_name, tool_call_id, success, error, duration_ms}` |
| `agent.stream.chunk` | StreamEventReceived | `{content, is_complete, chunk_index}` |
| `agent.continuation` | ContinuationEvaluated | `{step_number, should_continue, stop_reason, evaluations}` |

### Status Values

| Status | Meaning |
|--------|---------|
| `idle` | Initial state, waiting for execution |
| `processing` | Agent is executing steps |
| `completed` | Agent finished successfully |
| `failed` | Agent encountered an error |
| `cancelled` | User requested stop |
| `stopped` | Other stop reasons (steps limit, etc.) |

### Envelope Format

All events are wrapped in a standard envelope:

```json
{
    "type": "agent.stream.chunk",
    "session_id": "sess-abc123",
    "execution_id": "exec-xyz789",
    "timestamp": "2026-01-16T12:00:00.123Z",
    "payload": {
        "content": "Hello",
        "is_complete": false,
        "chunk_index": 0
    }
}
```

### Implementing a Broadcaster

Implement `CanBroadcastAgentEvents` to connect to your transport:

```php
use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;

// Laravel Reverb example
final class LaravelReverbBroadcaster implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void
    {
        broadcast(new AgentEvent("private-{$channel}", $envelope));
    }
}

// Pusher example
final class PusherBroadcaster implements CanBroadcastAgentEvents
{
    public function __construct(private Pusher $pusher) {}

    public function broadcast(string $channel, array $envelope): void
    {
        $this->pusher->trigger(
            "private-{$channel}",
            $envelope['type'],
            $envelope
        );
    }
}

// Console/Debug example
final class ConsoleBroadcaster implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void
    {
        $type = $envelope['type'];
        $payload = json_encode($envelope['payload']);
        echo "[{$type}] {$payload}\n";
    }
}
```

### Legacy Per-Event Wiring

Individual event handlers are still supported for fine-grained control:

```php
$agent->onEvent(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
$agent->onEvent(AgentStepCompleted::class, [$adapter, 'onAgentStepCompleted']);
$agent->onEvent(ToolCallStarted::class, [$adapter, 'onToolCallStarted']);
$agent->onEvent(ToolCallCompleted::class, [$adapter, 'onToolCallCompleted']);
$agent->onEvent(ContinuationEvaluated::class, [$adapter, 'onContinuationEvaluated']);
$agent->onEvent(StreamEventReceived::class, [$adapter, 'onStreamChunk']);
```

## Architecture

```
Agent (extends StepByStep)
├── CanUseTools driver          # Generates tool calls from state
│   ├── ToolCallingDriver       # Native function calling
│   └── ReActDriver             # Reason+Act prompting
├── Tools collection            # Available tools
├── ToolExecutor                # Executes tool calls
├── StateProcessors             # Post-step state transformations
└── ContinuationCriteria        # Stop conditions

AgentState
├── Messages                    # Conversation history
├── Steps[]                     # Execution history
├── Metadata                    # Key-value store
├── Usage                       # Accumulated tokens
└── Status                      # InProgress | Completed | Failed

AgentStep
├── InputMessages               # Context for this step
├── OutputMessages              # Generated messages
├── ToolCalls                   # Requested tool calls
├── ToolExecutions              # Execution results
├── InferenceResponse           # Raw LLM response
└── StepType                    # ToolExecution | FinalResponse | Error
```

## Custom Tools

```php
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Addons\Agent\Core\Collections\Tools;

class MyTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'my_tool',
            description: 'Does something useful',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed {
        $param = $args['param'] ?? $args[0] ?? 'default';
        return "Result: {$param}";
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param' => ['type' => 'string', 'description' => 'Input parameter'],
                    ],
                    'required' => ['param'],
                ],
            ],
        ];
    }
}

$agent = AgentBuilder::base()
    ->withTools(new Tools(new MyTool()))
    ->build();
```

## Testing

### DeterministicAgentDriver

Test agent behavior without LLM API calls by replaying pre-scripted responses.

```php
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;
use Cognesy\Addons\Agent\AgentBuilder;

// Create agent with deterministic responses
$driver = DeterministicAgentDriver::fromResponses(
    'First response',
    'Second response',
    'Final response'
);

$agent = AgentBuilder::base()
    ->withDriver($driver)
    ->withTools($tools)
    ->build();

// Or build a scenario with tool calls
$driver = DeterministicAgentDriver::fromSteps(
    ScenarioStep::toolCall('read_file', ['path' => '/tmp/test.txt']),
    ScenarioStep::final('Task completed successfully')
);

$agent = AgentBuilder::base()
    ->withDriver($driver)
    ->build();
```

**ScenarioStep Factory Methods:**

| Method | Description |
|--------|-------------|
| `ScenarioStep::final($response)` | Creates a final response step |
| `ScenarioStep::tool($response)` | Creates a tool execution step |
| `ScenarioStep::error($response)` | Creates an error step |
| `ScenarioStep::toolCall($name, $args, $response)` | Creates a step with tool call |

### MockTool

Simple mock tool for testing that returns fixed values or executes custom handlers.

```php
use Cognesy\Addons\Agent\Tools\Testing\MockTool;

// Static return value
$tool = MockTool::returning(
    name: 'get_weather',
    description: 'Returns weather data',
    value: ['temp' => 72, 'conditions' => 'sunny']
);

// Custom handler
$tool = new MockTool(
    name: 'calculate',
    description: 'Performs calculation',
    handler: fn($a, $b) => $a + $b
);

$agent = AgentBuilder::base()
    ->withTools(new Tools($tool))
    ->build();
```

## Directory Structure

```
├── src/Agent/
│   ├── Agent.php                   # Main orchestrator
│   ├── AgentBuilder.php            # Fluent builder for agents
│   ├── Broadcasting/               # REAL-TIME UI EVENTS
│   │   ├── BroadcastConfig.php     # Configuration with presets
│   │   ├── CanBroadcastAgentEvents.php  # Broadcaster interface
│   │   └── AgentEventEnvelopeAdapter.php  # Event-to-envelope adapter
│   ├── Capabilities/               # MODULAR FEATURES
│   │   ├── Bash/                   # Bash capability & tool
│   │   ├── File/                   # File tools (read, write, edit, search, list_dir)
│   │   ├── Metadata/               # Scratchpad capability & tools
│   │   ├── SelfCritique/           # Self-critique capability & processor
│   │   ├── Skills/                 # Skills capability & tool
│   │   ├── StructuredOutput/       # LLM-powered data extraction
│   │   ├── Subagent/               # Subagent capability & tool
│   │   └── Tasks/                  # Task planning capability & tool
│   ├── Contracts/
│   │   ├── AgentCapability.php     # Capability interface
│   │   ├── AgentContract.php       # Agent contract interface for Laravel/jobs
│   │   ├── AgentFactory.php        # Factory interface for custom agent creation
│   │   └── ToolInterface.php       # Tool interface
│   ├── Definitions/
│   │   └── AbstractAgentDefinition.php  # Base class for AgentContract implementations
│   ├── Core/                       # CORE INFRASTRUCTURE
│   │   ├── Collections/
│   │   │   ├── AgentSteps.php      # Step collection
│   │   │   ├── NameList.php        # Named list for tools/capabilities
│   │   │   ├── Tools.php           # Tool collection
│   │   │   └── ToolExecutions.php  # Execution results
│   │   ├── Continuation/
│   │   │   └── ToolCallPresenceCheck.php  # Continue if tool calls present
│   │   ├── Contracts/
│   │   │   ├── CanAccessAgentState.php    # State access interface
│   │   │   ├── CanAccessAnyState.php      # Generic state access
│   │   │   ├── CanExecuteToolCalls.php    # Executor interface
│   │   │   ├── CanUseTools.php            # Driver interface
│   │   │   ├── HasStepToolCalls.php       # Step tool calls trait
│   │   │   └── HasStepToolExecutions.php  # Step executions trait
│   │   ├── Data/
│   │   │   ├── AgentDescriptor.php # Agent metadata (name, description, tools, capabilities)
│   │   │   ├── AgentExecution.php  # Single tool execution
│   │   │   ├── AgentState.php      # State container
│   │   │   └── AgentStep.php       # Step container
│   │   ├── Enums/
│   │   │   ├── AgentStatus.php     # InProgress | Completed | Failed
│   │   │   └── AgentStepType.php   # ToolExecution | FinalResponse | Error
│   │   ├── ToolExecutor.php        # Tool execution logic
│   │   └── Traits/
│   │       ├── State/
│   │       │   └── HandlesAgentSteps.php
│   │       └── Step/
│   │           ├── HandlesStepToolCalls.php
│   │           └── HandlesStepToolExecutions.php
│   ├── Drivers/
│   │   ├── ReAct/                  # Reason+Act driver
│   │   │   ├── Actions/            # MakeReActPrompt, MakeToolCalls
│   │   │   ├── ContinuationCriteria/  # StopOnFinalDecision
│   │   │   ├── Contracts/          # Decision interface
│   │   │   ├── Data/               # DecisionWithDetails, ReActDecision
│   │   │   ├── ReActDriver.php
│   │   │   └── Utils/              # ReActFormatter, ReActValidator
│   │   ├── Testing/                # Testing utilities
│   │   │   └── DeterministicAgentDriver.php  # Deterministic test driver & ScenarioStep
│   │   └── ToolCalling/            # Native function calling
│   │       ├── ToolCallingDriver.php
│   │       └── ToolExecutionFormatter.php
│   ├── Events/
│   │   ├── AgentEvent.php          # Base event
│   │   ├── AgentFailed.php
│   │   ├── AgentFinished.php
│   │   ├── AgentStateUpdated.php
│   │   ├── AgentStepCompleted.php
│   │   ├── AgentStepStarted.php
│   │   ├── TokenUsageReported.php
│   │   ├── ToolCallCompleted.php
│   │   └── ToolCallStarted.php
│   ├── Exceptions/
│   │   ├── AgentException.php
│   │   ├── AgentNotFoundException.php
│   │   ├── InvalidToolArgumentsException.php
│   │   ├── InvalidToolException.php
│   │   └── ToolExecutionException.php
│   ├── Registry/
│   │   ├── AgentContractRegistry.php  # Registry for AgentContract classes
│   │   ├── AgentRegistry.php       # Manages agent specifications
│   │   ├── AgentSpec.php           # Agent definition (name, tools, prompt)
│   │   └── AgentSpecParser.php     # Parse agents from markdown
│   └── Tools/
│       ├── BaseTool.php            # Base class for tools
│       ├── FunctionTool.php        # Wrap PHP functions as tools
│       ├── LlmQueryTool.php        # Opt-in LLM reasoning tool
│       └── Testing/
│           └── MockTool.php        # Mock tool for testing
```

## Troubleshooting

### Why did my agent stop after one step?
- Default continuation stops when no tool calls are present after a step.
- Guard criteria (steps, tokens, time limits) can forbid continuation.
- Error policy stops on any error by default.

If you expect multiple steps, add a continuation criterion that requests continuation,
or ensure the model produces tool calls:
```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

$agent = AgentBuilder::base()
    ->addContinuationCriteria(
        ContinuationCriteria::when(
            static fn(AgentState $state): ContinuationDecision => ContinuationDecision::RequestContinuation
        )
    )
    ->build();
```

### How to debug continuation decisions
- Listen for `ContinuationEvaluated` events to inspect `ContinuationOutcome`.
- Call `ContinuationCriteria::evaluate($state)` in tests to see all evaluations.

### Configuring error policies
Use `withErrorPolicy()` to control retries and stop behavior:
```php
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;

$agent = AgentBuilder::base()
    ->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
    ->build();
```

### Using cumulative time for pause/resume
Use cumulative timeout for long-lived sessions:
```php
$agent = AgentBuilder::base()
    ->withCumulativeTimeout(120)
    ->build();
```
Persist `AgentState` with `StateInfo::cumulativeExecutionSeconds()` to resume accurately.

## Migration Guide (2026-01-16)

### ErrorPresenceCheck → ErrorPolicyCriterion
Old:
```php
new ErrorPresenceCheck(static fn($state) => $state->currentStep()?->hasErrors() ?? false);
```
New (builder default):
```php
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;

ErrorPolicyCriterion::withPolicy(ErrorPolicy::stopOnAnyError());
```

### RetryLimit → ErrorPolicy::maxRetries
Old:
```php
new RetryLimit($maxRetries, fn($s) => $s->steps(), fn($step) => $step->hasErrors());
```
New:
```php
ErrorPolicy::retryToolErrors($maxRetries);
```

### Slim serialization
Use `SlimAgentStateSerializer` for compact state payloads:
```php
use Cognesy\Addons\Agent\Serialization\SlimAgentStateSerializer;
use Cognesy\Addons\Agent\Serialization\SlimSerializationConfig;

$serializer = new SlimAgentStateSerializer(
    SlimSerializationConfig::minimal()
);
$payload = $serializer->serialize($state);
```

### Broadcasting with wiretap()
The `AgentEventEnvelopeAdapter` now supports single-line integration via `wiretap()`:

```php
// Old approach (verbose)
$agent->onEvent(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
$agent->onEvent(AgentStepCompleted::class, [$adapter, 'onAgentStepCompleted']);
// ... repeat for each event

// New approach (single line)
$agent->wiretap($adapter->wiretap());
```

New features:
- `StreamEventReceived` handling for real-time chat streaming
- Automatic status transitions (`idle` → `processing` → `completed/failed`)
- `BroadcastConfig` presets: `minimal()`, `standard()`, `debug()`
- `reset()` method for reusing adapter across executions

See the [Broadcasting](#broadcasting) section for full documentation.
