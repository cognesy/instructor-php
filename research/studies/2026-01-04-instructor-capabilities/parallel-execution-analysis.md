# Parallel vs Sequential Execution Analysis

## Current Execution Model: SEQUENTIAL

### Tool Execution (ToolExecutor.php:64-69)

```php
public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions {
    $executions = new ToolExecutions();
    foreach ($toolCalls->all() as $toolCall) {
        $executions = $executions->withAddedExecution($this->useTool($toolCall, $state));
    }
    return $executions;
}
```

**Behavior:**
- Executes tool calls **one at a time** in a `foreach` loop
- Blocking: waits for each tool to complete before starting the next
- No async/await or concurrent execution

### Subagent Execution (SpawnSubagentTool.php:89-97)

```php
private function runSubagent(Agent $subagent, AgentState $state): AgentState {
    $finalState = $state;

    foreach ($subagent->iterator($state) as $stepState) {
        $finalState = $stepState;
    }

    return $finalState;
}
```

**Behavior:**
- Runs subagent **to completion** before returning
- Blocking: main agent waits for subagent to finish
- Returns only final state, not intermediate steps

### The `parallelToolCalls` Flag

**Found in:** `ToolCallingDriver.php:37`

```php
private bool $parallelToolCalls = false;

// Line 105
$options = array_merge($options, ['parallel_tool_calls' => $this->parallelToolCalls]);
```

**What it does:**
- ❌ **NOT** actual parallel execution
- ✅ **IS** an API parameter sent to the LLM
- Tells the LLM whether it **can request** multiple tool calls in one response
- Execution is still sequential in `ToolExecutor::useTools()`

**Example:**
```
LLM response with parallelToolCalls=true:
{
  "tool_calls": [
    {"name": "read_file", "args": {"path": "a.php"}},
    {"name": "read_file", "args": {"path": "b.php"}},
    {"name": "grep", "args": {"pattern": "class"}}
  ]
}

Execution in ToolExecutor:
1. Execute read_file(a.php)    ← wait for completion
2. Execute read_file(b.php)    ← wait for completion
3. Execute grep("class")       ← wait for completion
```

## No Async Infrastructure

**Searched for:** `async`, `parallel`, `concurrent`, `promise`, `thread`, `process`

**Results:**
- No async/await patterns
- No promises or futures
- No concurrent execution primitives
- No thread/process pool
- PHP's synchronous nature is preserved throughout

## Implications for Subagents

### Current Behavior

When parent agent calls `spawn_subagent`:

```
Parent Agent
    ↓ (blocks here)
    SpawnSubagentTool::__invoke()
        ↓ (blocks here)
        Subagent runs to completion
        ↓
    Returns result
    ↓
Parent continues
```

**If LLM requests multiple subagents:**

```json
{
  "tool_calls": [
    {"name": "spawn_subagent", "args": {"subagent": "researcher", "prompt": "..."}},
    {"name": "spawn_subagent", "args": {"subagent": "analyzer", "prompt": "..."}},
  ]
}
```

**Execution:**
```
1. researcher runs to completion (blocks for N steps)
2. analyzer runs to completion (blocks for M steps)
3. Both results returned to parent
Total time: sum of both subagent durations
```

### Parallel Execution Would Require

To enable true parallel subagent execution:

#### Option 1: PHP Async Libraries

**Using amphp/parallel or ReactPHP:**

```php
use Amp\Parallel\Worker\DefaultPool;

public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions {
    $pool = DefaultPool::create();
    $promises = [];

    foreach ($toolCalls->all() as $toolCall) {
        $promises[] = $pool->submit(new ToolExecutionTask($toolCall, $state));
    }

    $results = Amp\Promise\all($promises);
    // ...
}
```

**Pros:**
- True parallelism (separate processes)
- No GIL issues (PHP has no GIL)

**Cons:**
- Complex: requires serialization of state
- Dependencies: adds amphp/parallel
- State management: shared state becomes tricky
- Error handling: more complex

#### Option 2: Process Pool

**Using Symfony Process:**

```php
use Symfony\Component\Process\Process;

public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions {
    $processes = [];

    foreach ($toolCalls->all() as $toolCall) {
        $process = new Process([
            'php', 'bin/execute-tool.php',
            json_encode(['tool' => $toolCall, 'state' => $state])
        ]);
        $process->start();
        $processes[] = $process;
    }

    foreach ($processes as $process) {
        $process->wait(); // Wait for all to complete
    }
}
```

**Pros:**
- Simple process-based parallelism
- Isolated execution (safer)

**Cons:**
- Overhead of process spawning
- IPC complexity for state/results
- Resource intensive

#### Option 3: Async HTTP Clients (for remote subagents)

**If subagents run as services:**

```php
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

$client = new Client();
$promises = [];

foreach ($toolCalls as $call) {
    $promises[] = $client->postAsync('/execute-subagent', [
        'json' => ['subagent' => $call->args()['subagent'], ...]
    ]);
}

$results = Promise\Utils::unwrap($promises);
```

**Pros:**
- True concurrency via HTTP
- Scalable (remote workers)
- Natural isolation

**Cons:**
- Requires service architecture
- Network overhead
- Complex deployment

## Recommendations

### For Current Codebase (Keep Sequential)

**Rationale:**
1. PHP is inherently synchronous
2. Agent execution is stateful and complex
3. Most use cases don't need parallelism
4. Simpler to reason about and debug

**When sequential is fine:**
- Most subagent patterns involve delegation (parent → specialist)
- Subagents typically need context from each other
- Execution order often matters

### For Future (Add Parallel as Opt-In)

**If parallelism is needed, add as optional feature:**

```php
class SubagentSpec {
    public bool $canRunInParallel = false;
}

// Usage
$registry->register(new SubagentSpec(
    name: 'researcher',
    canRunInParallel: true, // Safe to run concurrently
    // ...
));
```

**Implementation approach:**

1. **Mark safe tools:** Tools that are stateless and don't conflict
2. **Detect parallelizable calls:** Check if all requested tools support parallel
3. **Use process pool:** Only for marked-safe tools
4. **Fallback to sequential:** For stateful or unsafe tools

**Example:**

```php
class ToolExecutor {
    public function useTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions {
        if ($this->canRunInParallel($toolCalls)) {
            return $this->useToolsParallel($toolCalls, $state);
        }
        return $this->useToolsSequential($toolCalls, $state);
    }

    private function canRunInParallel(ToolCalls $calls): bool {
        foreach ($calls as $call) {
            $tool = $this->tools->get($call->name());
            if (!($tool instanceof CanRunInParallel)) {
                return false;
            }
        }
        return true;
    }
}
```

## Example: Parallel Research Pattern

**Use case:** Gather information from multiple sources concurrently

```php
// Define parallel-safe subagents
$registry->register(new SubagentSpec(
    name: 'web-researcher',
    description: 'Search web for information',
    tools: ['web_search', 'web_fetch'],
    canRunInParallel: true,
));

$registry->register(new SubagentSpec(
    name: 'code-searcher',
    description: 'Search codebase',
    tools: ['grep', 'glob', 'read_file'],
    canRunInParallel: true,
));

// LLM requests both in parallel
{
  "tool_calls": [
    {"name": "spawn_subagent", "args": {"subagent": "web-researcher", ...}},
    {"name": "spawn_subagent", "args": {"subagent": "code-searcher", ...}}
  ]
}

// With parallel support (future):
// Both run concurrently, results combined
// Total time: max(web-researcher time, code-searcher time)

// Without parallel support (current):
// Run sequentially
// Total time: web-researcher time + code-searcher time
```

## Conclusion

**Current State:**
- ✅ Sequential execution throughout
- ✅ Simple, predictable, debuggable
- ❌ No parallelism support

**Future Enhancement:**
- Could add opt-in parallel execution
- Requires careful design around state management
- Not critical for MVP
- Can be added later without breaking changes

**Decision for SubagentRegistry:**
- Keep sequential execution for now
- Add `canRunInParallel` field to SubagentSpec (for future use)
- Document the execution model
- Provide hooks for future parallel implementation
