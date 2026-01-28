# PRD: Dynamic Tool Filtering

**Priority**: P1
**Impact**: Medium
**Effort**: Low
**Status**: Proposed

## Problem Statement

instructor-php provides all registered tools to the LLM for every step. There's no built-in mechanism to:
1. Limit available tools per step
2. Implement progressive tool revelation
3. Enforce role-based tool access per request
4. Guide agents through multi-phase workflows

## Current State

```php
// Current: All tools always available
$tools = new Tools($searchTool, $analyzeTool, $summarizeTool, $deleteTool);
$agent = new Agent(tools: $tools, ...);

// No way to say "only use search in step 1, then summarize in step 2"
$state = $agent->execute($initialState);
```

**Workarounds**:
```php
// Option 1: Create different agents for different phases (awkward)
$searchAgent = new Agent(tools: new Tools($searchTool), ...);
$summarizeAgent = new Agent(tools: new Tools($summarizeTool), ...);

// Option 2: Use observer to block tools (hacky)
class PhaseObserver extends PassThroughObserver {
    public function beforeToolUse(ToolCall $call, AgentState $state): ToolUseDecision {
        $step = $state->stepCount();
        $allowed = $step < 3 ? ['search'] : ['summarize'];
        if (!in_array($call->name(), $allowed)) {
            return ToolUseDecision::block($call, 'Not available in this phase');
        }
        return ToolUseDecision::allow($call);
    }
}
```

**Limitations**:
1. LLM still sees all tools in schema (wastes tokens, causes confusion)
2. Blocking after selection is suboptimal UX
3. No compile-time safety for tool names
4. Logic scattered across observers

## Proposed Solution

### API Design

```php
// Option 1: activeTools at execution time
$state = $agent->execute($initialState, activeTools: ['search', 'analyze']);

// Option 2: prepareStep callback (preferred)
$agent = $agent->with(
    prepareStep: function (PrepareStepContext $context): PrepareStepResult {
        $step = $context->stepNumber;
        $previousResults = $context->previousSteps;

        if ($step === 1) {
            return new PrepareStepResult(activeTools: ['search']);
        }

        if ($step === 2) {
            return new PrepareStepResult(activeTools: ['analyze']);
        }

        // Default to summarize for final steps
        return new PrepareStepResult(activeTools: ['summarize', 'format']);
    }
);
```

### PrepareStepContext

```php
class PrepareStepContext {
    public function __construct(
        public readonly int $stepNumber,
        public readonly AgentState $state,
        public readonly AgentSteps $previousSteps,
        public readonly Tools $allTools,
        public readonly mixed $context,  // experimental_context equivalent
    ) {}

    public function previousToolCalls(): ToolCalls {
        return $this->previousSteps->flatMap(fn($s) => $s->toolCalls());
    }

    public function hasUsedTool(string $name): bool {
        foreach ($this->previousSteps as $step) {
            if ($step->toolCalls()->hasToolNamed($name)) {
                return true;
            }
        }
        return false;
    }
}
```

### PrepareStepResult

```php
class PrepareStepResult {
    public function __construct(
        /** @var string[]|null Tool names to make available (null = all) */
        public readonly ?array $activeTools = null,

        /** @var string|null Override system prompt for this step */
        public readonly ?string $system = null,

        /** @var array|null Override options for this step */
        public readonly ?array $options = null,

        /** @var mixed|null Override context for this step */
        public readonly mixed $context = null,
    ) {}

    public static function unchanged(): self {
        return new self();
    }

    public static function withTools(string ...$tools): self {
        return new self(activeTools: $tools);
    }
}
```

### Integration Points

```php
// Agent configuration
class Agent {
    public function __construct(
        // Existing params...
        /** @var callable(PrepareStepContext): PrepareStepResult|null */
        private ?callable $prepareStep = null,
    ) {}
}

// In execution loop
private function performStep(AgentState $state): AgentState {
    // Prepare step
    $context = new PrepareStepContext(
        stepNumber: $state->stepCount() + 1,
        state: $state,
        previousSteps: $state->steps(),
        allTools: $this->tools,
        context: $state->metadata()->get('context'),
    );

    $preparation = $this->prepareStep !== null
        ? ($this->prepareStep)($context)
        : PrepareStepResult::unchanged();

    // Filter tools
    $activeTools = $this->resolveActiveTools($preparation);

    // Execute with filtered tools
    return $this->useTools($state, $activeTools);
}

private function resolveActiveTools(PrepareStepResult $prep): Tools {
    if ($prep->activeTools === null) {
        return $this->tools;
    }

    $filtered = [];
    foreach ($prep->activeTools as $name) {
        if ($this->tools->has($name)) {
            $filtered[] = $this->tools->get($name);
        }
    }

    return new Tools(...$filtered);
}
```

## How Other Libraries Implement This

### Vercel AI SDK

**Location**: `packages/ai/src/generate-text/generate-text.ts`

```typescript
// Static active tools
const result = await generateText({
    model: openai('gpt-4o'),
    tools: allTools,
    activeTools: ['search', 'calculate'],  // Only these available
    prompt: 'Research this topic',
});

// Dynamic via prepareStep
const agent = new ToolLoopAgent({
    model: openai('gpt-4o'),
    tools: allTools,
    prepareStep: async ({ steps, stepNumber }) => {
        if (stepNumber === 0) {
            return { activeTools: ['search'] };
        }
        if (steps.some(s => s.toolCalls.length > 5)) {
            return {
                activeTools: ['summarize'],
                system: 'Synthesize the findings.',
            };
        }
        return undefined;  // No changes
    },
});
```

**Key Implementation Details**:
1. `activeTools` array filters before sending to LLM
2. `prepareStep` called before each step
3. Can also modify `system`, `model`, `context`
4. Returns `undefined` for no changes
5. Type-safe: `activeTools` typed as `(keyof TOOLS)[]`

### LangChain

**Location**: `langchain/agents/agent.py`

```python
# Tool selection via agent type
from langchain.agents import create_tool_calling_agent

# Dynamic tool selection
class DynamicToolAgent(AgentExecutor):
    def _get_available_tools(self, state):
        step = len(state.get("intermediate_steps", []))
        if step < 2:
            return [self.tools["search"]]
        return [self.tools["summarize"]]
```

### AutoGen

**Location**: `autogen/agentchat/conversable_agent.py`

```python
# Per-message tool registration
agent.register_for_llm(
    name="search",
    description="Search the web",
)(search_function)

# Conditional registration
if user_role == "admin":
    agent.register_for_llm(name="delete")(delete_function)
```

## Implementation Considerations

### Type Safety

```php
// Validate tool names at prepare time
private function resolveActiveTools(PrepareStepResult $prep): Tools {
    if ($prep->activeTools === null) {
        return $this->tools;
    }

    $filtered = [];
    $missing = [];

    foreach ($prep->activeTools as $name) {
        if ($this->tools->has($name)) {
            $filtered[] = $this->tools->get($name);
        } else {
            $missing[] = $name;
        }
    }

    if (!empty($missing)) {
        throw new InvalidToolException(
            "Unknown tools in activeTools: " . implode(', ', $missing)
        );
    }

    return new Tools(...$filtered);
}
```

### Role-Based Access

```php
// Example: User role determines tools
$agent = $agent->with(
    prepareStep: function (PrepareStepContext $ctx): PrepareStepResult {
        $user = $ctx->context['user'] ?? null;

        $baseTools = ['search', 'read'];

        if ($user?->hasRole('editor')) {
            $baseTools[] = 'write';
        }

        if ($user?->hasRole('admin')) {
            $baseTools[] = 'delete';
            $baseTools[] = 'execute';
        }

        return PrepareStepResult::withTools(...$baseTools);
    }
);
```

### Progressive Revelation

```php
// Guide agent through research phases
$agent = $agent->with(
    prepareStep: function (PrepareStepContext $ctx): PrepareStepResult {
        // Phase 1: Search and gather
        if (!$ctx->hasUsedTool('analyze')) {
            return new PrepareStepResult(
                activeTools: ['search', 'read_url'],
                system: 'Gather information from multiple sources.',
            );
        }

        // Phase 2: Analyze
        if (!$ctx->hasUsedTool('synthesize')) {
            return new PrepareStepResult(
                activeTools: ['analyze', 'compare'],
                system: 'Analyze the gathered information.',
            );
        }

        // Phase 3: Output
        return new PrepareStepResult(
            activeTools: ['synthesize', 'format'],
            system: 'Create the final output.',
        );
    }
);
```

### Caching Tool Schemas

```php
// Optimization: Cache filtered tool schemas
class ToolSchemaCache {
    private array $cache = [];

    public function getSchema(Tools $tools): array {
        $key = implode(',', $tools->names());
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $tools->toToolSchema();
        }
        return $this->cache[$key];
    }
}
```

## Migration Path

1. **Phase 1**: Add `PrepareStepContext` and `PrepareStepResult` classes
2. **Phase 2**: Add `prepareStep` callback to Agent
3. **Phase 3**: Implement tool filtering in execution loop
4. **Phase 4**: Add convenience `activeTools` parameter to execute()
5. **Phase 5**: Add examples for common patterns

## Success Metrics

- [ ] Tools can be filtered per step
- [ ] LLM only sees filtered tools in schema
- [ ] prepareStep can modify system prompt
- [ ] Role-based access patterns supported
- [ ] No breaking changes to existing API
- [ ] Performance: Tool schema caching works

## Open Questions

1. Should `activeTools` be tool names or `ToolInterface` instances?
2. How to handle empty `activeTools` (no tools available)?
3. Should driver receive filtered or full tools?
4. How to combine with existing observer pattern?
