# PRD: Streaming Agent Loop

**Priority**: P0
**Impact**: High
**Effort**: Low-Medium
**Status**: Proposed

## Problem Statement

instructor-php's agent loop provides step-by-step iteration but not real-time streaming **within** steps. While `iterator()` yields after each step completes, users cannot observe LLM token generation or partial responses during a step. This creates suboptimal UX for long-running steps.

## Current State

```php
// execute() blocks until all steps complete
$finalState = $agent->execute($state);

// iterator() yields after each COMPLETE step
foreach ($agent->iterator($state) as $stepState) {
    // See state only after step completes - no partial responses during LLM generation
    echo $stepState->currentStep()->outputMessages()->toString();
}
```

**What Already Exists:**
1. `StepByStep::iterator()` - yields AgentState after each complete step
2. `InferenceStream` - full streaming capability in the Inference layer
3. `PendingInference::stream()` - returns InferenceStream for real-time partial responses
4. `PartialInferenceResponse` - contains `contentDelta`, accumulated content, tool calls

**The Gap:**
The `ToolCallingDriver` uses `->response()` (blocking) instead of `->stream()`:
```php
// ToolCallingDriver.php line 101
return $this->buildPendingInference(...)->response();  // Blocking!
```

**Limitations**:
1. No streaming of LLM text deltas during generation within a step
2. No real-time visibility into tool call arguments as they stream
3. Steps with long LLM responses appear frozen
4. Cannot build token-by-token UI updates

## Proposed Solution

### Approach: Wire Existing Streaming Infrastructure

The Inference layer already has full streaming support via `InferenceStream`. The solution is to:
1. Add a streaming driver variant that uses `->stream()` instead of `->response()`
2. Add a `streamIterator()` method to Agent that yields during LLM generation
3. Define stream part types that wrap `PartialInferenceResponse` for the agent context

### API Design

```php
interface CanStreamAgentLoop {
    /**
     * Stream agent execution with real-time updates.
     * Yields PartialInferenceResponse during LLM generation AND AgentState after steps.
     *
     * @return iterable<AgentStreamPart>
     */
    public function streamIterator(AgentState $state): iterable;
}

abstract class AgentStreamPart {
    public readonly string $agentId;
    public readonly int $stepNumber;
    public readonly DateTimeImmutable $timestamp;
}

// Stream part types - wrapping existing PartialInferenceResponse
class StepStarted extends AgentStreamPart {}

class TextDelta extends AgentStreamPart {
    public readonly string $delta;              // From PartialInferenceResponse->contentDelta
    public readonly string $accumulatedText;    // From PartialInferenceResponse->content
}

class ToolCallStarted extends AgentStreamPart {
    public readonly string $toolName;
    public readonly string $toolCallId;
}

class ToolCallArgsDelta extends AgentStreamPart {
    public readonly string $toolCallId;
    public readonly string $argsDelta;
}

class ToolCallCompleted extends AgentStreamPart {
    public readonly string $toolCallId;
    public readonly ToolExecution $execution;
}

class StepCompleted extends AgentStreamPart {
    public readonly AgentStep $step;
    public readonly ContinuationOutcome $outcome;
}

class ExecutionCompleted extends AgentStreamPart {
    public readonly AgentState $finalState;
}
```

### Usage Example

```php
// Streaming usage
foreach ($agent->stream($state) as $part) {
    match (true) {
        $part instanceof TextDelta => $this->sendToClient($part->delta),
        $part instanceof ToolCallStarted => $this->showToolIndicator($part->toolName),
        $part instanceof ToolCallCompleted => $this->showToolResult($part->execution),
        $part instanceof StepCompleted => $this->updateStepCount($part->step),
        $part instanceof ExecutionCompleted => $this->showFinalResult($part->finalState),
    };
}

// SSE endpoint example
public function streamAgent(Request $request): StreamedResponse {
    return new StreamedResponse(function () use ($request) {
        $state = $this->buildState($request);

        foreach ($this->agent->stream($state) as $part) {
            echo "data: " . json_encode($part->toArray()) . "\n\n";
            ob_flush();
            flush();
        }
    }, headers: ['Content-Type' => 'text/event-stream']);
}
```

### Driver Changes

Extend existing `ToolCallingDriver` to support streaming using the existing `InferenceStream`:

```php
interface CanUseToolsStreaming extends CanUseTools {
    /**
     * Stream tool usage with real-time updates.
     * Leverages existing InferenceStream infrastructure.
     */
    public function useToolsStreaming(
        AgentState $state,
        Tools $tools,
        CanExecuteToolCalls $executor
    ): iterable;
}

class ToolCallingDriver implements CanUseToolsStreaming {
    public function useToolsStreaming(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): iterable {
        $cache = $this->resolveCachedContext($state, $tools);

        // Use existing ->stream() instead of ->response()
        $pendingInference = $this->buildPendingInference($state->messagesForInference(), $tools, $cache)
            ->withStreaming(true);  // Enable streaming mode

        $inferenceStream = $pendingInference->stream();  // Returns InferenceStream

        // Iterate over existing PartialInferenceResponse objects
        foreach ($inferenceStream->responses() as $partialResponse) {
            // Text content delta
            if ($partialResponse->contentDelta !== '') {
                yield new TextDelta(
                    delta: $partialResponse->contentDelta,
                    accumulatedText: $partialResponse->content,
                    agentId: $state->agentId,
                    stepNumber: $state->stepCount() + 1,
                );
            }

            // Tool call streaming (if supported by provider)
            foreach ($partialResponse->toolCalls->each() as $toolCall) {
                yield new ToolCallStarted(
                    toolName: $toolCall->name(),
                    toolCallId: $toolCall->id(),
                    ...
                );
            }
        }

        // Get final response from stream
        $response = $inferenceStream->final();
        $toolCalls = $response->toolCalls();

        // Execute tools (still synchronous, but after streaming LLM response)
        $executions = $executor->useTools($toolCalls, $state);
        foreach ($executions as $execution) {
            yield new ToolCallCompleted(execution: $execution, ...);
        }

        // Yield final step
        yield new StepCompleted(step: $this->buildStepFromResponse(...), ...);
    }
}
```

## How Other Libraries Implement This

### Vercel AI SDK

**Location**: `packages/ai/src/generate-text/stream-text.ts`

```typescript
// StreamTextResult is an async iterable
const result = streamText({
    model: openai('gpt-4o'),
    tools,
    prompt: 'Research topic',
});

// Multiple consumption patterns
for await (const chunk of result.textStream) {
    process.stdout.write(chunk);
}

// Or consume full stream with parts
for await (const part of result.fullStream) {
    switch (part.type) {
        case 'text-delta':
            console.log(part.textDelta);
            break;
        case 'tool-call':
            console.log('Tool:', part.toolName);
            break;
        case 'tool-result':
            console.log('Result:', part.result);
            break;
    }
}
```

**Key Implementation Details**:
1. Uses `ReadableStream` and async iterators
2. `TextStreamPart` union type for all stream events
3. Automatic step boundaries in multi-step execution
4. `toDataStreamResponse()` for SSE formatting

### Pydantic AI

**Location**: `pydantic_ai/agent.py`

```python
# Generator-based streaming
async for chunk in agent.run_stream(prompt):
    if isinstance(chunk, TextChunk):
        print(chunk.text, end='')
    elif isinstance(chunk, ToolCall):
        print(f"Calling {chunk.name}")
    elif isinstance(chunk, ToolResult):
        print(f"Result: {chunk.output}")
```

**Key Implementation Details**:
1. Python async generators
2. Chunk types as dataclasses
3. Streaming integrated with validation pipeline

### LangChain

**Location**: `langchain/agents/agent.py`

```python
# Streaming with callbacks
for chunk in agent.stream({"input": query}):
    for key, value in chunk.items():
        if key == "output":
            print(value)
        elif key == "intermediate_steps":
            for action, result in value:
                print(f"Tool: {action.tool}, Result: {result}")
```

**Key Implementation Details**:
1. Uses callback system for streaming
2. `astream_events()` for fine-grained control
3. Intermediate steps exposed during streaming

## Implementation Considerations

### Building on Existing Infrastructure

The key insight is that `InferenceStream` and `PartialInferenceResponse` already exist. The work is:
1. Enable streaming mode in `PendingInference` (already supported)
2. Add `CanUseToolsStreaming` interface to drivers
3. Add `streamIterator()` to `StepByStep` that wraps the streaming driver
4. Translate `PartialInferenceResponse` to `AgentStreamPart` types

### PHP Generators
PHP generators compose naturally with the existing `InferenceStream::responses()` generator:

```php
public function streamIterator(AgentState $state): \Generator {
    $state = $this->markExecutionStartedIfSupported($state);
    yield new ExecutionStarted(agentId: $state->agentId);

    while ($this->hasNextStep($state)) {
        yield new StepStarted(stepNumber: $state->stepCount() + 1, ...);

        // Use streaming driver - yields PartialInferenceResponse wrapped as AgentStreamPart
        foreach ($this->driver->useToolsStreaming($state, $this->tools, $this->executor) as $part) {
            yield $part;

            // Update state when step completes
            if ($part instanceof StepCompleted) {
                $state = $this->applyStep($state, $part->step);
            }
        }
    }

    $finalState = $this->onNoNextStep($state);
    yield new ExecutionCompleted(finalState: $finalState);
}
```

### HTTP Streaming
For SSE/streaming HTTP responses:

```php
class AgentStreamTransformer {
    public static function toSSE(iterable $stream): \Generator {
        foreach ($stream as $part) {
            yield sprintf("event: %s\ndata: %s\n\n",
                $part->type(),
                json_encode($part->toArray())
            );
        }
    }

    public static function toNDJSON(iterable $stream): \Generator {
        foreach ($stream as $part) {
            yield json_encode($part->toArray()) . "\n";
        }
    }
}
```

### Buffering Strategy
Handle tool execution during streaming:

```php
// Option 1: Buffer tool calls, execute after
$toolCalls = [];
foreach ($inferenceStream as $chunk) {
    if ($chunk->isToolCall()) {
        $toolCalls[] = $chunk;
    }
    yield $chunk;
}
// Then execute tools

// Option 2: Execute immediately (parallel potential)
// More complex but lower latency
```

## Migration Path

Since the infrastructure already exists in `InferenceStream`, the effort is lower than building from scratch:

1. **Phase 1**: Define `AgentStreamPart` types (simple data classes)
2. **Phase 2**: Add `CanUseToolsStreaming` interface extending `CanUseTools`
3. **Phase 3**: Implement `useToolsStreaming()` in `ToolCallingDriver` using existing `->stream()`
4. **Phase 4**: Add `streamIterator()` to `StepByStep` that composes with streaming driver
5. **Phase 5**: Add HTTP helpers (SSE, NDJSON) for web integration

## Success Metrics

- [ ] Stream parts emitted in real-time during LLM generation
- [ ] Tool execution progress visible before completion
- [ ] SSE endpoint can stream to browser clients
- [ ] No performance regression for non-streaming usage
- [ ] Existing `iterate()` API unchanged

## Open Questions

1. Should `streamIterator()` be a separate method or a mode flag on `iterator()`?
2. How to handle state processor hooks during streaming (before/after each partial)?
3. Should tool call argument streaming be exposed (provider-dependent)?
4. How to integrate with existing events (`AgentStepStarted`, etc.) during streaming?
5. Should we emit `PartialInferenceResponse` directly or wrap in `AgentStreamPart`?
