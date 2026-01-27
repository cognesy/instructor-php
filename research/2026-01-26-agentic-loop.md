# Agentic Loop Architecture - Message Separation

**Date:** 2026-01-26
**Status:** Research / Design Phase

## Context

Investigation into how `AgentStep::outputMessages` handles data and whether it properly separates intermediate execution data from final agent responses.

## User's Design Intent

The concept behind the `Agent` implementation relies on:

1. **Iterative execution of steps** until continuation criteria are met before providing an answer

2. **Continuation criteria** might be:
   - No more tool calls requested
   - Budget exceeded (tokens, cost, time)
   - Error encountered
   - Custom criteria

3. **Original design intent** was to use the results of iterative process to:
   - a) Use the answer the LLM provided (generated based on 1 or more tool executions)
   - b) Generate the answer if it was not directly provided

4. **During iterative process** (while continuation criteria allow it):
   - Do NOT inject anything into main messages sequence
   - Those results are intermediate and expected to lead to final answer
   - Main messages sequence represents interaction between user and agent

5. **Future capability** (UseXxx):
   - Pick up failed attempts to generate answers
   - Re-plan / re-attempt based on prior attempts
   - This requires access to intermediate execution data

6. **Current goal**: Build infrastructure to separate:
   - **Intermediate data**: reasoning, tool calls, tool execution traces, error messages
   - **Final response**: the actual agent answer to inject into conversation

## Current Architecture Analysis

### What `AgentStep::outputMessages` Currently Contains

Per step, the `ToolCallingDriver` creates `outputMessages` containing:

```
outputMessages:
├── Tool invocation message (assistant, content='', tool_calls metadata)
├── Tool result message (tool, content=result)
├── [more tool invocations/results if multiple tools called]
└── LLM response message (assistant, content=response text)
```

When `toString()` is called:
- Tool invocation messages are **skipped** (empty content)
- Tool result messages are **included**
- LLM response text is **included**

### How Messages Flow Currently

1. `ToolCallingDriver::useTools()` creates `AgentStep` with mixed `outputMessages`
2. `AppendStepMessages` processor appends ALL `outputMessages` to main conversation after EACH step
3. Result: main conversation gets polluted with intermediate tool data

### Current Problem Illustrated

```
User: "What's the weather in Paris?"

CURRENT FLOW:
├─ Step 1: LLM calls get_weather tool
│   └─ outputMessages: [tool_call_msg, tool_result_msg] → APPENDED to messages
├─ Step 2: LLM responds "The weather is sunny"
│   └─ outputMessages: [response_msg] → APPENDED to messages

Main conversation becomes:
├─ user: "What's the weather in Paris?"
├─ assistant: "" + tool_calls metadata     ← INTERNAL
├─ tool: "Temperature: 22°C, Sunny"        ← INTERNAL
└─ assistant: "The weather is sunny"       ← Actual response
```

### Desired Behavior

```
Main conversation (clean):
├─ user: "What's the weather in Paris?"
└─ assistant: "The weather is sunny"

Execution trace (separate, for debugging/retry):
├─ Step 1: tool_call(get_weather), result: "Temperature: 22°C"
└─ Step 2: final_response: "The weather is sunny"
```

## Identified Issues

1. **Redundancy**: `inputMessages` stores full context, tool results also appear in `outputMessages`

2. **No clean separation**: No easy way to get just the agent's "final answer" vs intermediate tool outputs

3. **Semantic confusion**: `outputMessages` suggests "what the agent produced" but includes internal tool machinery

4. **Breaks conversation model**: User-agent conversation gets polluted with internal execution details

5. **Hinders future capabilities**: Re-planning/retry needs clean access to execution trace separate from conversation

## Proposed Architecture

### 1. Split `AgentStep::outputMessages` Into Two Concerns

```php
final readonly class AgentStep
{
    private Messages $inputMessages;       // Context sent to LLM (unchanged)
    private Messages $traceMessages;       // Tool calls + results (internal execution data)
    private Messages $responseMessages;    // LLM's text response only
    private InferenceResponse $inferenceResponse;
    private ToolExecutions $toolExecutions;

    // For inference: LLM needs to see tool calls/results to continue reasoning
    public function messagesForNextInference(): Messages {
        return $this->traceMessages->appendMessages($this->responseMessages);
    }

    // For final output: just the response text
    public function responseMessages(): Messages {
        return $this->responseMessages;
    }

    // Convenience
    public function responseText(): string {
        return $this->responseMessages->toString();
    }

    // Legacy compatibility (deprecate later)
    public function outputMessages(): Messages {
        return $this->messagesForNextInference();
    }
}
```

### 2. Modify Inference Context Building

The LLM needs to see tool calls/results within an execution, but these come from `stepExecutions` not main `messages`.

**Important**: Research confirms this is the correct approach - LLMs MUST see tool_use/tool_result pairs within an execution to reason correctly, but this data can be omitted from the persistent conversation history.

```php
// AgentState
public function messagesForInference(): Messages {
    $baseMessages = $this->messages();  // Clean user-agent conversation

    // Add current execution's trace (tool calls/results from prior steps in THIS execution)
    $executionTrace = $this->stepExecutions->traceMessages();

    return $baseMessages->appendMessages($executionTrace);
}
```

### 3. Only Append Final Response to Main Conversation

```php
// New processor: AppendFinalResponse (replaces AppendStepMessages)
public function process(AgentState $state, ?callable $next = null): AgentState {
    $newState = $next ? $next($state) : $state;

    // Only append when execution is complete
    if ($newState->status() !== AgentStatus::Completed) {
        return $newState;
    }

    $finalResponse = $newState->currentStep()?->responseMessages();
    if ($finalResponse === null || $finalResponse->isEmpty()) {
        return $newState;
    }

    return $newState->withMessages(
        $newState->messages()->appendMessages($finalResponse)
    );
}
```

### 4. Data Flow Diagram

```
                    ┌─────────────────────────────────────────┐
                    │              AgentState                  │
                    │  ┌─────────────────────────────────────┐│
                    │  │  messages (clean conversation)      ││
                    │  │  user → assistant → user → ...      ││
                    │  └─────────────────────────────────────┘│
                    │  ┌─────────────────────────────────────┐│
                    │  │  stepExecutions (execution trace)   ││
                    │  │  [Step1: tool_call, result]         ││
                    │  │  [Step2: tool_call, result]         ││
                    │  │  [Step3: final_response]            ││
                    │  └─────────────────────────────────────┘│
                    └─────────────────────────────────────────┘
                                        │
        ┌───────────────────────────────┼───────────────────────────────┐
        ▼                               ▼                               ▼
   messagesForInference()        responseMessages()           for retry/re-planning
   (messages + trace)            (just final answer)          (full trace available)
```

## Implementation Plan

1. Add `traceMessages` and `responseMessages` to `AgentStep`
2. Update `ToolCallingDriver` to populate them correctly
3. Update `ReActDriver` similarly
4. Create `AppendFinalResponse` processor (or modify `AppendStepMessages`)
5. Update `messagesForInference()` to build context from trace
6. Update examples to use new API
7. Deprecate `outputMessages()` or keep as alias

## Key Files Involved

- `packages/agents/src/Agent/Data/AgentStep.php`
- `packages/agents/src/Agent/Data/AgentState.php`
- `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`
- `packages/agents/src/Drivers/ToolCalling/ToolExecutionFormatter.php`
- `packages/agents/src/Drivers/ReAct/ReActDriver.php`
- `packages/agents/src/Agent/StateProcessing/Processors/AppendStepMessages.php`

## Open Questions

1. Should `traceMessages` include the LLM's reasoning/thinking if present?
2. How to handle cases where LLM provides partial response + tool calls in same step?
3. Should we support "streaming" partial responses to user while execution continues?
4. How does this interact with context caching (`CachedContext`)?
5. What happens when execution is interrupted (budget exceeded) - do we synthesize a response?

## Research Findings (2026-01-26)

### Web Research on LLM Tool Calling Requirements

#### Within a Single Execution (Agent Loop)
Tool calls and results **MUST** be preserved in message history for subsequent inference calls within the same execution. Both Anthropic and OpenAI APIs require this:

- Anthropic: "Tool result blocks must immediately follow their corresponding tool use blocks in the message history"
- OpenAI: When handling function calls, you must append tool responses with `tool_call_id` referencing the original call
- The LLM needs to see what tools it called and what results came back to continue reasoning correctly

#### Between Separate User Interactions
Tool call history **CAN** be omitted, summarized, or compacted between separate user interactions:

- **Observation Masking** (JetBrains Research): Replacing tool outputs with placeholders achieves equal or better results than full history, with 83.9% of context usage coming from tool results
- **Anthropic Engineering Guidance**: "Once a tool has been called deep in the message history, why would the agent need to see the raw result again?"
- **Context Compaction**: Strip redundant information that exists elsewhere (e.g., file contents written by agent)

#### Best Practices from Research

1. **Prefer raw > Compaction > Summarization** - only summarize when compaction yields insufficient space
2. **Keep recent tool calls raw** - maintains model's "rhythm" and formatting style
3. **Insert summaries as SYSTEM messages** - signals to model this is context-setting, not active dialogue
4. **Treat context as finite resource** - diminishing returns as context grows (context rot)

### Validation of Original Design Intent

The original design intent is **correct and aligned with industry best practices**:

1. **During execution**: Keep tool calls/results in context for LLM to reason over
2. **After execution**: Only add the final response to the main conversation
3. **For retry/replay**: Keep full execution trace available for debugging/re-planning

### The Critical Distinction

```
WITHIN an execution (must preserve for LLM):
├── User: "What's the weather?"
├── Assistant: [tool_use: get_weather]     ← LLM needs this
├── User: [tool_result: "22°C sunny"]      ← LLM needs this
└── Assistant: "It's sunny and 22°C"

BETWEEN executions (can compact/omit for clean conversation):
├── User: "What's the weather?"
├── Assistant: "It's sunny and 22°C"       ← Clean conversation only
├── User: "And tomorrow?"
└── ... new execution starts fresh
```

### Sources

- [Anthropic Tool Use Documentation](https://platform.claude.com/docs/en/docs/build-with-claude/tool-use)
- [Anthropic Context Engineering for AI Agents](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- [OpenAI Conversation State](https://platform.openai.com/docs/guides/conversation-state)
- [JetBrains Research: Efficient Context Management](https://blog.jetbrains.com/research/2025/12/efficient-context-management/)
- [LLM Chat History Summarization Guide](https://mem0.ai/blog/llm-chat-history-summarization-guide-2025)

## Key Discoveries and Insights

### Discovery 1: The "Fractal" Nature of Agent Conversations

Agent conversations have a recursive/fractal structure:

```
User ←→ Agent (main thread)
         │
         └── Agent's internal execution (internal thread)
              ├── Step 1: tool call + result
              ├── Step 2: tool call + result
              ├── Step 3: subagent call
              │            └── Subagent's internal execution
              │                 ├── Step 1: tool call + result
              │                 └── Step 2: final response
              └── Step 4: final response
```

Each level:
- Has its own execution context (steps, tool calls, token usage)
- Should only expose the **response** to its caller, not internal details
- May be interrupted (budget, depth limit, errors)
- Needs full tracking for observability/control

### Discovery 2: Two Distinct Message Streams

1. **Conversation Stream** (`messages`): Clean user↔agent dialogue
   - Persists across executions
   - Contains only user queries and agent responses
   - No tool calls, no intermediate reasoning

2. **Execution Stream** (`stepExecutions`): Internal processing trace
   - Temporary, scoped to single execution
   - Contains tool calls, results, reasoning, errors
   - Required for LLM inference within execution
   - Available for debugging/retry/re-planning

### Discovery 3: Current `AppendStepMessages` Violates This Model

The processor appends ALL step output (including tool calls/results) to persistent `messages` after EACH step. This:
- Pollutes conversation with internal details
- Breaks the fractal isolation model
- Makes subagent responses include their internal trace
- Prevents clean conversation continuity

### Discovery 4: `onAfterExecution()` is the Right Hook

The lifecycle hook `onAfterExecution()` fires after all steps complete. This is the correct place to:
- Extract only the final response
- Handle cases where no response was generated (interrupted execution)
- Optionally synthesize a response from execution results
- Add the clean response to `messages`

### Discovery 5: Token/Resource Tracking Across Stack

Need to track across the fractal stack:
- Total tokens used (including subagents)
- Depth of agent calls
- Number of tool executions
- Number of steps taken
- Time spent

Current `AgentState` has some of this, but not propagated correctly through subagent calls.

## Architectural Principles to Apply

### Single Responsibility (SRP)
- `AgentStep` should not mix trace data with response data
- Separate concerns: inference context vs. conversation output

### Open/Closed (OCP)
- Extend via processors/hooks rather than modifying core Agent
- New capabilities (retry, re-plan) should be additive

### Liskov Substitution (LSP)
- Subagent should be substitutable with any tool from caller's perspective
- Response format should be consistent regardless of internal complexity

### Interface Segregation (ISP)
- Callers only need response, not full execution trace
- Internal components need full trace for continuation decisions

### Dependency Inversion (DIP)
- Agent should depend on abstractions (CanExecuteToolCalls, CanUseTools)
- Response extraction strategy should be injectable

### DDD Concepts
- **Aggregate Root**: `AgentState` is the aggregate root for execution state
- **Value Objects**: `AgentStep`, `StepExecution`, `ToolExecution` are value objects
- **Domain Events**: Already have events for step/execution lifecycle
- **Bounded Context**: Execution context is bounded - doesn't leak to parent

## Related Future Work

- **UseRetry capability**: Re-attempt failed executions with access to prior trace
- **UseReplanning capability**: Analyze failed attempts and create new plan
- **Execution persistence**: Store execution traces for debugging/analytics
- **Streaming responses**: Show partial results while agent works
