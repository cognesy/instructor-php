# Agent System

Tool-calling agent with iterative execution, state management, and extensible capabilities.

## Quick Start

```php
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

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
use Cognesy\Addons\Agent\Capabilities\UseBash;
use Cognesy\Addons\Agent\Capabilities\UseFileTools;

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
    ->withCapability(new UseSubagents($registry, 3, summaryMaxChars: 8000))
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
use Cognesy\Addons\Agent\Tools\BashPolicy;

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

$policy = new TodoPolicy(maxItems: 25, maxInProgress: 2, reminderEverySteps: 10);
$agent = AgentBuilder::base()
    ->withCapability(new UseTaskPlanning($policy))
    ->build();

$subagentPolicy = new SubagentPolicy(maxDepth: 3, summaryMaxChars: 12000);
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents($registry, $subagentPolicy))
    ->build();
```

### LoadSkillTool
Load SKILL.md files on-demand.
```php
LoadSkillTool::withLibrary(new SkillLibrary('./skills'));
```

### SpawnSubagentTool
Spawn isolated subagents with filtered capabilities.
```php
new SpawnSubagentTool($parentAgent, $capability);
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
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\*;

$criteria = new ContinuationCriteria(
    new StepsLimit(20, fn($s) => $s->stepCount()),
    new TokenUsageLimit(32768, fn($s) => $s->usage()->total()),
    new ExecutionTimeLimit(300, fn($s) => $s->startedAt()),
    new RetryLimit(3, fn($s) => $s->steps(), fn($step) => $step->hasErrors()),
);

// Build agent with custom criteria
$agent = AgentBuilder::base()
    ->withTools($tools)
    ->addContinuationCriteria($customCriteria)
    ->build();
```

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

## Subagent Types

| Type | Tools | Purpose |
|------|-------|---------|
| `Explore` | bash, read_file | Read-only exploration |
| `Code` | bash, read_file, write_file, edit_file, todo_write | Full coding |
| `Plan` | read_file | Planning without execution |

```php
use Cognesy\Addons\Agent\Enums\AgentType;
use Cognesy\Addons\Agent\Agents\DefaultAgentCapability;

$capability = new DefaultAgentCapability();
$tools = $capability->toolsFor(AgentType::Explore, $allTools);
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
```

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
| `AgentStepStarted` | step, messages, tools |
| `AgentStepCompleted` | step, hasToolCalls, errors, usage |
| `AgentStateUpdated` | state, step |
| `AgentFinished` | status, steps, usage |
| `AgentFailed` | error, status, steps |
| `ToolCallStarted` | toolName, callId |
| `ToolCallCompleted` | toolName, callId, result, duration |

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
└── Status                      # Running | Completed | Failed

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

## Directory Structure

```
├── src/Agent/
│   ├── Agent.php                   # Main orchestrator
│   ├── AgentBuilder.php            # Fluent builder for agents
│   ├── ToolExecutor.php            # Tool execution logic
│   ├── Capabilities/               # MODULAR FEATURES
│   │   ├── Bash/                   # Bash capability & tool
│   │   ├── File/                   # File capability & tools
│   │   ├── Skills/                 # Skills capability & tool
│   │   ├── Subagent/               # Subagent capability & tool
│   │   ├── Tasks/                  # Task planning capability & tool
│   │   └── SelfCritique/           # Self-critique capability & processor
│   ├── Collections/
│   │   ├── Tools.php               # Tool collection
│   │   └── ToolExecutions.php      # Execution results
│   ├── Contracts/
│   │   ├── AgentCapability.php     # Capability interface
│   │   ├── CanUseTools.php         # Driver interface
│   │   ├── CanExecuteToolCalls.php # Executor interface
│   │   └── ToolInterface.php       # Tool interface
│   ├── Data/
│   │   ├── AgentState.php          # State container
│   │   ├── AgentStep.php           # Step container
│   │   ├── AgentExecution.php      # Single tool execution
│   ├── Drivers/
│   │   ├── ToolCalling/            # Native function calling
│   │   └── ReAct/                  # Reason+Act driver
│   ├── Enums/
│   │   ├── AgentType.php           # Explore | Code | Plan
│   │   ├── AgentStatus.php         # Running | Completed | Failed
│   │   ├── AgentStepType.php       # ToolExecution | FinalResponse | Error
│   └── Tools/
│       ├── BaseTool.php            # Base class for tools
│       ├── FunctionTool.php        # Wrap PHP functions as tools
│       └── LlmQueryTool.php        # Opt-in LLM reasoning tool
```
