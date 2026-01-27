# Problem Analysis: Message Flow in Agentic Execution

**Date:** 2026-01-26

## The Fractal Model of Agent Conversations

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

## Current Message Flow

```
User Message → state.messages() (via MessageStore "messages" section)
                    ↓
            messagesForInference() compiles [summary, buffer, messages]
                    ↓
            ToolCallingDriver.useTools()
                    ↓
            AgentStep created with:
              - inputMessages (context for this step)
              - outputMessages (tool_call + tool_result + response)
                    ↓
            AppendStepMessages processor appends ALL outputMessages to state.messages()
                    ↓
            NEXT STEP sees: original messages + tool traces + response
```

## The Core Problem

`outputMessages` conflates three distinct message types:

1. **Tool call messages** (assistant with tool_calls metadata)
2. **Tool result messages** (tool role)
3. **Final response** (assistant text response)

All three get appended to the persistent conversation after EACH step.

### Consequences

1. **Polluted conversation history**: Tool traces become part of "conversation"
2. **Serialization bloat**: When state is serialized, includes all tool traces
3. **Broken fractal isolation**: Subagent internal details visible to parent
4. **Context confusion**: Long conversations full of tool traces

## Evidence from Codebase

### ToolCallingDriver creates mixed outputMessages

```php
// ToolCallingDriver.php lines 82-93
public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
    $response = $this->getToolCallResponse($state, $tools);
    $toolCalls = $this->getToolsToCall($response);
    $executions = $executor->useTools($toolCalls, $state);

    // This creates tool_call + tool_result messages
    $messages = $this->formatter->makeExecutionMessages($executions);

    return $this->buildStepFromResponse(
        response: $response,
        executions: $executions,
        followUps: $messages,  // Mixed content goes here
        context: $context,
    );
}
```

### ToolExecutionFormatter mixes message types

```php
// ToolExecutionFormatter.php
public function makeExecutionMessages(ToolExecutions $toolExecutions): Messages {
    $messages = Messages::empty();
    foreach ($toolExecutions->all() as $toolExecution) {
        // 1. Tool invocation (assistant with tool_calls)
        $messages = $messages->appendMessage($this->toolInvocationMessage($toolCall));
        // 2. Tool result (tool role)
        $messages = $messages->appendMessage($this->toolExecutionResultMessage($toolCall, $result));
    }
    return $messages;
}
```

### AppendStepMessages appends everything

```php
// AppendStepMessages.php
public function process(AgentState $state, ?callable $next = null): AgentState {
    $newState = $next ? $next($state) : $state;
    $currentStep = $newState->currentStep();

    // ALL outputMessages get appended - no filtering
    $outputMessages = $currentStep->outputMessages();

    return $newState->withMessages(
        $newState->messages()->appendMessages($outputMessages)
    );
}
```

## What LLMs Actually Need

### During Execution (Required)

LLMs MUST see tool_use → tool_result pairs to reason correctly. This is an API requirement from both OpenAI and Anthropic.

```
User: "What's the weather?"
Assistant: [tool_use: get_weather]     ← LLM needs this
User: [tool_result: "22°C sunny"]      ← LLM needs this
Assistant: "It's sunny and 22°C"
```

### Between Executions (Optional)

Tool call history CAN be omitted between separate user interactions:

> "Once a tool has been called deep in the message history, why would the agent need to see the raw result again?" — Anthropic Engineering

## The Two Message Streams

### 1. Conversation Stream (`messages`)
- Clean user↔agent dialogue
- Persists across executions
- Contains only user queries and agent responses
- No tool calls, no intermediate reasoning

### 2. Execution Stream (`stepExecutions` / `execution_buffer`)
- Temporary, scoped to single execution
- Contains tool calls, results, reasoning, errors
- Required for LLM inference within execution
- Available for debugging/retry/re-planning

## Why This Matters for Subagents

Subagents currently handle this correctly:

```php
// SpawnSubagentTool.php - returns only text to parent
return $this->extractResponse($finalState, $spec->name());
// Returns: "[Subagent: reviewer] Final response text..."
```

But the parent's own execution pollutes its conversation with tool traces.
