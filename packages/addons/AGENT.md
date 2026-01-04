# Agent System

Tool-calling agent with iterative execution, state management, and extensible capabilities.

## Quick Start

```php
use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

// Create agent with tools
$agent = AgentFactory::codingAgent(workDir: '/path/to/project');

// Initialize state with user message
$state = AgentState::empty()->withMessages(
    Messages::fromString('Read config.php and update the debug flag to true')
);

// Run to completion
$finalState = $agent->finalStep($state);
echo $finalState->currentStep()->outputMessages()->toString();
```

## Factory Methods

| Method | Tools | Use Case |
|--------|-------|----------|
| `default(tools, llmPreset)` | Custom | Base agent with provided tools |
| `withBash(baseDir, llmPreset)` | bash | Shell command execution |
| `withFileTools(baseDir, llmPreset)` | read_file, write_file, edit_file | File operations |
| `withTaskPlanning(llmPreset)` | todo_write | Task tracking |
| `withSkills(library, llmPreset)` | load_skill | Knowledge loading |
| `codingAgent(workDir, llmPreset)` | All above + spawn_subagent | Full coding assistant |

All factory methods accept an optional `llmPreset` parameter to specify the LLM connection preset from `/config/llm.php`.

```php
// Use default LLM
$agent = AgentFactory::default();

// Use specific preset
$agent = AgentFactory::default(llmPreset: 'openai');
$agent = AgentFactory::withFileTools(baseDir: '/path', llmPreset: 'anthropic');
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
new BashTool(baseDir: '/tmp', timeout: 120);
```

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

$agent = AgentFactory::default()->withContinuationCriteria(...$criteria->all());
```

### State Processors
Modify state after each step:
```php
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\Agent\StateProcessors\PersistTasksProcessor;

$processors = new StateProcessors(
    new AccumulateTokenUsage(),
    new AppendStepMessages(),
    new PersistTasksProcessor(),
);

$agent = AgentFactory::default()->with(processors: $processors);
```

### Custom Driver
```php
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\LLMProvider;

$driver = new ToolCallingDriver(
    llm: LLMProvider::new()->withConnection('anthropic'),
    model: 'claude-sonnet-4-20250514',
);

$agent = AgentFactory::default()->withDriver($driver);
```

### Sandbox Policy
```php
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

$policy = ExecutionPolicy::in('/project')
    ->withTimeout(120)
    ->withNetwork(true)
    ->withReadablePaths('/project', '/usr/share')
    ->withWritablePaths('/project/output')
    ->inheritEnvironment();

$agent = AgentFactory::withBash(policy: $policy);
```

## Subagent Types

| Type | Tools | Purpose |
|------|-------|---------|
| `Explore` | bash, read_file | Read-only exploration |
| `Code` | bash, read_file, write_file, edit_file, todo_write | Full coding |
| `Plan` | read_file | Planning without execution |

```php
use Cognesy\Addons\Agent\Enums\AgentType;
use Cognesy\Addons\Agent\Subagents\DefaultAgentCapability;

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

$agent = AgentFactory::default(events: $events);
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

$agent = AgentFactory::default(tools: new Tools(new MyTool()));
```

## Directory Structure

```
src/Agent/
├── Agent.php                   # Main orchestrator
├── AgentFactory.php            # Factory methods
├── ToolExecutor.php            # Tool execution logic
├── Collections/
│   ├── Tools.php               # Tool collection
│   └── ToolExecutions.php      # Execution results
├── Contracts/
│   ├── CanUseTools.php         # Driver interface
│   ├── CanExecuteToolCalls.php # Executor interface
│   └── ToolInterface.php       # Tool interface
├── Data/
│   ├── AgentState.php          # State container
│   ├── AgentStep.php           # Step container
│   ├── AgentExecution.php      # Single tool execution
│   ├── Task.php                # Todo task
│   └── TaskList.php            # Task collection
├── Drivers/
│   ├── ToolCalling/            # Native function calling
│   └── ReAct/                  # Reason+Act driver
├── Enums/
│   ├── AgentType.php           # Explore | Code | Plan
│   ├── AgentStatus.php         # Running | Completed | Failed
│   ├── AgentStepType.php       # ToolExecution | FinalResponse | Error
│   └── TaskStatus.php          # pending | in_progress | completed
├── Skills/
│   ├── Skill.php               # Skill data
│   └── SkillLibrary.php        # Skill loading/caching
├── StateProcessors/
│   └── PersistTasksProcessor.php
├── Subagents/
│   ├── AgentCapability.php     # Capability interface
│   └── DefaultAgentCapability.php
└── Tools/
    ├── BaseTool.php            # Base class
    ├── BashTool.php
    ├── ReadFileTool.php
    ├── WriteFileTool.php
    ├── EditFileTool.php
    ├── TodoWriteTool.php
    ├── LoadSkillTool.php
    ├── LlmQueryTool.php
    └── SpawnSubagentTool.php
```
