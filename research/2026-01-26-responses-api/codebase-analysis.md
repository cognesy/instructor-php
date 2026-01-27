# Codebase Analysis: Polyglot Inference Architecture

## Current Architecture Summary

The polyglot package uses a **layered adapter pattern** for handling inference across 24+ providers:

```
┌─────────────────────────────────────────────────────────────────────┐
│                           Inference (Facade)                        │
├─────────────────────────────────────────────────────────────────────┤
│                        PendingInference                             │
│              (Deferred execution, retry, caching)                   │
├─────────────────────────────────────────────────────────────────────┤
│                         InferenceStream                             │
│                    (Streaming orchestration)                        │
├─────────────────────────────────────────────────────────────────────┤
│                       BaseInferenceDriver                           │
│              (Common orchestration, error handling)                 │
├─────────────────────────────────────────────────────────────────────┤
│    ┌──────────────────────┐    ┌──────────────────────┐            │
│    │    RequestAdapter    │    │   ResponseAdapter    │            │
│    │ (CanTranslateRequest)│    │(CanTranslateResponse)│            │
│    └──────────┬───────────┘    └──────────┬───────────┘            │
│               │                           │                        │
│    ┌──────────▼───────────┐    ┌──────────▼───────────┐            │
│    │     BodyFormat       │    │     UsageFormat      │            │
│    │  (CanMapRequestBody) │    │   (CanMapUsage)      │            │
│    └──────────┬───────────┘    └──────────────────────┘            │
│               │                                                     │
│    ┌──────────▼───────────┐                                        │
│    │    MessageFormat     │                                        │
│    │   (CanMapMessages)   │                                        │
│    └──────────────────────┘                                        │
├─────────────────────────────────────────────────────────────────────┤
│                          HttpClient                                 │
│                   (HTTP transport layer)                            │
└─────────────────────────────────────────────────────────────────────┘
```

## Key Data Structures

### InferenceRequest
- `messages`: Messages array (system, user, assistant, tool)
- `model`: Model identifier
- `tools`: Tool/function definitions (OpenAI format)
- `toolChoice`: Tool selection strategy
- `responseFormat`: Structured output schema
- `options`: Provider-specific options
- `mode`: OutputMode enum

### InferenceResponse
- `content`: Main text response
- `reasoningContent`: Extended thinking content
- `toolCalls`: ToolCalls collection
- `finishReason`: InferenceFinishReason enum
- `usage`: Token usage

### PartialInferenceResponse (Streaming)
- `contentDelta`: Text chunk
- `reasoningContentDelta`: Reasoning chunk
- `toolId`, `toolName`, `toolArgs`: Tool accumulation
- Internal tool accumulation via array

## Streaming Implementation

Current streaming uses:
1. SSE parsing via `EventStreamReader`
2. `toEventBody()` extracts event data (handles `data:` prefix)
3. `fromStreamResponse()` converts JSON to `PartialInferenceResponse`
4. Accumulation happens in `PartialInferenceResponse::withAccumulatedContent()`

## Extensibility Points

1. **Driver Registration**: `Inference::registerDriver()`
2. **Custom Adapters**: Implement adapter interfaces
3. **Capabilities Declaration**: `capabilities()` method per driver
4. **Events**: Observable pattern for all state changes

## Gaps for Responses API Support

### 1. Response Structure Mismatch
**Current:** `choices[0].message.content`
**Responses API:** `output[]` array of typed items

### 2. Items vs Messages
**Current:** Messages with roles (user, assistant, system, tool)
**Responses API:** Items with types (message, function_call, function_call_output, reasoning)

### 3. Streaming Events
**Current:** `data:` prefixed chunks with delta content
**Responses API:** Semantic events (`response.output_text.delta`, `response.completed`, etc.)

### 4. Tool Call Format
**Current (Chat Completions):**
```json
{
  "type": "function",
  "function": {
    "name": "...",
    "arguments": "..."
  }
}
```
**Responses API:**
```json
{
  "type": "function_call",
  "call_id": "...",
  "name": "...",
  "arguments": "..."
}
```

### 5. Instructions vs System Messages
**Current:** System role in messages array
**Responses API:** Top-level `instructions` field

### 6. Response Format Location
**Current:** `response_format` at top level
**Responses API:** `text.format` nested object

### 7. No Previous Response Chaining
**Responses API:** Supports `previous_response_id` for conversation continuity

## Assessment

The existing architecture is **well-designed for extensibility**. The adapter pattern allows for a new driver with different request/response mappings without touching core code.

**Recommended Approach:** Create a new `OpenAIResponses` driver (Option A) rather than modifying existing OpenAI driver. This:
- Keeps Chat Completions driver stable
- Allows clean separation of concerns
- Enables independent evolution
- Follows existing multi-driver patterns (e.g., Gemini vs GeminiOAI)

## Files to Create/Modify

### New Files (Driver)
```
packages/polyglot/src/Inference/Drivers/OpenAIResponses/
├── OpenAIResponsesDriver.php
├── OpenAIResponsesRequestAdapter.php
├── OpenAIResponsesBodyFormat.php
├── OpenAIResponsesResponseAdapter.php
├── OpenAIResponsesMessageFormat.php
├── OpenAIResponsesUsageFormat.php
└── OpenAIResponsesStreamEventParser.php
```

### New Data Structures
```
packages/polyglot/src/Inference/Data/
├── ResponseItem.php              # Union type for output items
├── ResponseItemType.php          # Enum for item types
└── ResponsesStreamEvent.php      # Streaming event type
```

### Modify
```
packages/polyglot/src/Inference/
├── Creation/InferenceDriverFactory.php  # Register new driver
└── Config/LLMPresetFactory.php          # Add preset
```

## Implementation Complexity

| Component | Complexity | Notes |
|-----------|------------|-------|
| Driver scaffold | Low | Follows existing pattern |
| Request adapter | Medium | Different body structure |
| Body format | Medium | Items vs messages mapping |
| Response adapter | High | Complex item type handling |
| Streaming parser | High | Semantic events need new logic |
| Message format | Medium | Items bidirectional conversion |
| Usage format | Low | Similar structure |

**Estimated Effort:** Medium - the architecture supports this well, but Responses API has enough differences to require careful implementation.
