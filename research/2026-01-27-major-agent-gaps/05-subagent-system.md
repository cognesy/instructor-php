# PRD: Subagent System

**Priority**: P2
**Impact**: Medium
**Effort**: High
**Status**: Proposed

## Problem Statement

instructor-php has no native support for subagent orchestration. Complex workflows requiring multiple specialized agents must be manually implemented via custom tools, leading to:
1. No built-in parent-child coordination
2. No shared context or memory between agents
3. No depth limits or resource management
4. No standard patterns for agent delegation

## Current State

```php
// Current: Manual subagent via tool
class SubagentTool implements ToolInterface, CanAccessAgentState {
    private Agent $subagent;
    private ?AgentState $parentState = null;

    public function withAgentState(AgentState $state): self {
        $clone = clone $this;
        $clone->parentState = $state;
        return $clone;
    }

    public function use(mixed ...$args): Result {
        $task = $args['task'] ?? '';

        // Manual state management
        $childState = new AgentState(
            parentAgentId: $this->parentState?->agentId(),
        );
        $childState = $childState->withUserMessage($task);

        // No depth limiting
        // No shared context
        // No resource budgets
        $result = $this->subagent->execute($childState);

        return Result::success([
            'output' => $result->currentStep()?->outputMessages()->toString(),
        ]);
    }

    // Boilerplate: name(), description(), toToolSchema()...
}
```

**Limitations**:
1. Each subagent requires custom tool implementation
2. No automatic depth limit enforcement
3. No shared memory or context inheritance
4. No token budget propagation
5. No standard event coordination

## Proposed Solution

### SubAgent Definition

```php
class SubAgentDefinition {
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Agent $agent,
        public readonly ?int $maxDepth = null,
        public readonly ?int $tokenBudget = null,
        public readonly array $sharedContext = [],
        public readonly bool $inheritMessages = false,
    ) {}
}
```

### SubAgentTool (Built-in)

```php
class SubAgentTool implements ToolInterface, CanAccessAgentState {
    private SubAgentDefinition $definition;
    private ?AgentState $parentState = null;
    private int $currentDepth = 0;

    public function __construct(SubAgentDefinition $definition) {
        $this->definition = $definition;
    }

    public function withAgentState(AgentState $state): self {
        $clone = clone $this;
        $clone->parentState = $state;
        $clone->currentDepth = $this->calculateDepth($state);
        return $clone;
    }

    public function use(mixed ...$args): Result {
        $task = $args['task'] ?? '';

        // Check depth limit
        if ($this->definition->maxDepth !== null &&
            $this->currentDepth >= $this->definition->maxDepth) {
            return Result::failure(new MaxDepthExceededException(
                $this->definition->name,
                $this->currentDepth,
                $this->definition->maxDepth
            ));
        }

        // Calculate remaining budget
        $remainingBudget = $this->calculateRemainingBudget();
        if ($remainingBudget !== null && $remainingBudget <= 0) {
            return Result::failure(new TokenBudgetExceededException(
                $this->definition->name
            ));
        }

        // Build child state with inheritance
        $childState = $this->buildChildState($task, $remainingBudget);

        // Emit spawning event
        $this->emitSpawningEvent($childState, $task);

        // Execute subagent
        $startedAt = new DateTimeImmutable();
        $result = $this->definition->agent->execute($childState);

        // Emit completion event
        $this->emitCompletionEvent($result, $startedAt);

        return $this->formatResult($result);
    }

    private function buildChildState(string $task, ?int $tokenBudget): AgentState {
        $childState = new AgentState(
            parentAgentId: $this->parentState?->agentId(),
        );

        // Inherit shared context
        foreach ($this->definition->sharedContext as $key) {
            $value = $this->parentState?->metadata()->get($key);
            if ($value !== null) {
                $childState = $childState->withMetadata($key, $value);
            }
        }

        // Track depth
        $childState = $childState->withMetadata('_depth', $this->currentDepth + 1);

        // Set token budget if specified
        if ($tokenBudget !== null) {
            $childState = $childState->withMetadata('_tokenBudget', $tokenBudget);
        }

        // Optionally inherit messages
        if ($this->definition->inheritMessages) {
            $parentMessages = $this->parentState?->messages() ?? Messages::empty();
            $childState = $childState->withMessages($parentMessages);
        }

        // Add task as user message
        return $childState->withUserMessage($task);
    }
}
```

### SubAgentBuilder

```php
class SubAgentBuilder {
    private string $name;
    private string $description;
    private ?Agent $agent = null;
    private ?int $maxDepth = null;
    private ?int $tokenBudget = null;
    private array $sharedContext = [];
    private bool $inheritMessages = false;

    public static function create(string $name): self {
        return (new self())->withName($name);
    }

    public function withName(string $name): self { ... }
    public function withDescription(string $description): self { ... }
    public function withAgent(Agent $agent): self { ... }
    public function withMaxDepth(int $depth): self { ... }
    public function withTokenBudget(int $tokens): self { ... }
    public function sharingContext(string ...$keys): self { ... }
    public function inheritingMessages(bool $inherit = true): self { ... }

    public function build(): SubAgentTool {
        $definition = new SubAgentDefinition(
            name: $this->name,
            description: $this->description,
            agent: $this->agent ?? throw new \InvalidArgumentException('Agent required'),
            maxDepth: $this->maxDepth,
            tokenBudget: $this->tokenBudget,
            sharedContext: $this->sharedContext,
            inheritMessages: $this->inheritMessages,
        );

        return new SubAgentTool($definition);
    }
}

// Usage
$researchSubagent = SubAgentBuilder::create('research')
    ->withDescription('Research a topic in depth')
    ->withAgent($researchAgent)
    ->withMaxDepth(2)
    ->withTokenBudget(10000)
    ->sharingContext('userId', 'projectId')
    ->build();

$mainAgent = new Agent(
    tools: new Tools($researchSubagent, $writeSubagent),
    ...
);
```

### Orchestrator Pattern

```php
class AgentOrchestrator {
    /** @var array<string, Agent> */
    private array $agents = [];
    private Agent $coordinator;
    private int $maxDepth;

    public function __construct(
        Agent $coordinator,
        int $maxDepth = 3,
    ) {
        $this->coordinator = $coordinator;
        $this->maxDepth = $maxDepth;
    }

    public function register(string $name, Agent $agent): self {
        $this->agents[$name] = $agent;
        return $this;
    }

    public function execute(string $task): OrchestratorResult {
        // Build subagent tools
        $tools = [];
        foreach ($this->agents as $name => $agent) {
            $tools[] = SubAgentBuilder::create($name)
                ->withDescription("Delegate to {$name} agent")
                ->withAgent($agent)
                ->withMaxDepth($this->maxDepth)
                ->build();
        }

        // Add to coordinator
        $coordinatorWithSubagents = $this->coordinator->with(
            tools: $this->coordinator->tools()->withTools(...$tools)
        );

        // Execute
        $state = (new AgentState())->withUserMessage($task);
        $finalState = $coordinatorWithSubagents->execute($state);

        return new OrchestratorResult(
            finalState: $finalState,
            subagentExecutions: $this->collectSubagentExecutions($finalState),
        );
    }
}

// Usage
$orchestrator = new AgentOrchestrator($plannerAgent, maxDepth: 3);
$orchestrator
    ->register('researcher', $researchAgent)
    ->register('writer', $writerAgent)
    ->register('reviewer', $reviewerAgent);

$result = $orchestrator->execute('Write a comprehensive report on AI trends');
```

## How Other Libraries Implement This

### AutoGen

**Location**: `autogen/agentchat/groupchat.py`

```python
# Multi-agent group chat
from autogen import AssistantAgent, UserProxyAgent, GroupChat, GroupChatManager

researcher = AssistantAgent("researcher", ...)
writer = AssistantAgent("writer", ...)
critic = AssistantAgent("critic", ...)

group_chat = GroupChat(
    agents=[researcher, writer, critic],
    messages=[],
    max_round=10,
)

manager = GroupChatManager(groupchat=group_chat)

# Execution with automatic routing
user_proxy.initiate_chat(manager, message="Write a report")
```

**Key Implementation Details**:
1. GroupChatManager coordinates agents
2. Automatic speaker selection based on conversation
3. Shared message history across all agents
4. Round-based execution with limits

### LangGraph

**Location**: `langgraph/prebuilt/chat_agent_executor.py`

```python
# Hierarchical agent via graph
from langgraph.graph import StateGraph

# Define parent and child agents as nodes
graph = StateGraph(State)
graph.add_node("parent", parent_agent)
graph.add_node("research_child", research_agent)
graph.add_node("write_child", write_agent)

# Conditional routing
def route_to_child(state):
    if "needs_research" in state:
        return "research_child"
    if "needs_writing" in state:
        return "write_child"
    return END

graph.add_conditional_edges("parent", route_to_child)
graph.add_edge("research_child", "parent")  # Return to parent
graph.add_edge("write_child", "parent")
```

**Key Implementation Details**:
1. Graph-based orchestration
2. State flows between nodes
3. Conditional routing logic
4. Explicit return paths

### CrewAI

**Location**: `crewai/crew.py`

```python
from crewai import Agent, Task, Crew

researcher = Agent(
    role='Researcher',
    goal='Find comprehensive information',
    tools=[search_tool],
)

writer = Agent(
    role='Writer',
    goal='Create compelling content',
    tools=[write_tool],
)

research_task = Task(
    description='Research the topic',
    agent=researcher,
)

write_task = Task(
    description='Write the report',
    agent=writer,
    context=[research_task],  # Depends on research
)

crew = Crew(
    agents=[researcher, writer],
    tasks=[research_task, write_task],
    process=Process.sequential,  # Or hierarchical
)

result = crew.kickoff()
```

**Key Implementation Details**:
1. Task-based delegation
2. Explicit task dependencies via `context`
3. Process types: sequential, hierarchical
4. Shared crew memory

## Implementation Considerations

### Depth Tracking

```php
class DepthTracker {
    public static function getDepth(AgentState $state): int {
        return (int)($state->metadata()->get('_depth') ?? 0);
    }

    public static function incrementDepth(AgentState $state): AgentState {
        $current = self::getDepth($state);
        return $state->withMetadata('_depth', $current + 1);
    }
}

// In SubAgentTool
private function calculateDepth(AgentState $state): int {
    return DepthTracker::getDepth($state);
}
```

### Token Budget Propagation

```php
class TokenBudgetManager {
    public static function getRemainingBudget(AgentState $state): ?int {
        $budget = $state->metadata()->get('_tokenBudget');
        if ($budget === null) {
            return null;
        }

        $used = $state->usage()->total();
        return max(0, $budget - $used);
    }

    public static function createBudgetCriterion(int $budget): CanEvaluateContinuation {
        return new TokenBudget($budget);
    }
}

// Add to subagent's continuation criteria
$subagentCriteria = $baseCriteria->withCriteria(
    TokenBudgetManager::createBudgetCriterion($remainingBudget)
);
```

### Event Coordination

```php
// Parent subscribes to subagent events
$parentEventEmitter->onEvent(SubagentCompleted::class, function (SubagentCompleted $event) {
    $this->logger->info('Subagent completed', [
        'subagent' => $event->subagentName,
        'steps' => $event->steps,
        'tokens' => $event->usage?->total(),
    ]);

    // Update parent state if needed
    $this->updateParentMetrics($event);
});
```

### Result Aggregation

```php
class OrchestratorResult {
    public function __construct(
        public readonly AgentState $finalState,
        public readonly array $subagentExecutions,
    ) {}

    public function totalTokens(): int {
        $total = $this->finalState->usage()->total();
        foreach ($this->subagentExecutions as $execution) {
            $total += $execution->usage()->total();
        }
        return $total;
    }

    public function totalSteps(): int {
        $total = $this->finalState->stepCount();
        foreach ($this->subagentExecutions as $execution) {
            $total += $execution->stepCount();
        }
        return $total;
    }

    public function executionTree(): array {
        // Build hierarchical view of execution
    }
}
```

## Migration Path

1. **Phase 1**: Create `SubAgentDefinition` and `SubAgentTool` classes
2. **Phase 2**: Add depth tracking to `AgentState`
3. **Phase 3**: Implement token budget propagation
4. **Phase 4**: Add subagent events to `AgentEventEmitter`
5. **Phase 5**: Create `SubAgentBuilder` for ergonomic API
6. **Phase 6**: Implement `AgentOrchestrator` for complex patterns

## Success Metrics

- [ ] Subagents can be defined declaratively
- [ ] Depth limits enforced automatically
- [ ] Token budgets propagate to children
- [ ] Parent receives subagent events
- [ ] Context sharing works correctly
- [ ] No infinite recursion possible

## Open Questions

1. Should subagents share the same event bus as parent?
2. How to handle subagent failures (retry, skip, fail parent)?
3. Should we support parallel subagent execution?
4. How to implement hierarchical vs flat orchestration?
