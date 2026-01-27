# OpenAI Responses API Implementation

## Overview

This document describes the implementation of OpenAI Responses API (and OpenResponses specification) support in the Polyglot package.

## Key Differences from Chat Completions API

| Aspect | Chat Completions | Responses API |
|--------|------------------|---------------|
| **Endpoint** | `/v1/chat/completions` | `/v1/responses` |
| **System prompts** | `messages[].role=system` | Separate `instructions` field |
| **User/Assistant messages** | `messages[]` array | `input` field (string or items array) |
| **Max tokens param** | `max_completion_tokens` | `max_output_tokens` |
| **Response structure** | `choices[0].message.content` | `output[]` items array with typed items |
| **Finish indicator** | `finish_reason` (stop/length/tool_calls) | `status` (completed/incomplete/failed) |
| **Tool calls** | Externally tagged in `tool_calls[]` | Internally tagged as `output[]` items with `type: function_call` |
| **Streaming events** | Raw deltas (`delta.content`) | Semantic events (`response.output_text.delta`) |

## Major Implementation Challenges

### 1. Message Format Transformation

The Responses API separates concerns differently:

```php
// Chat Completions: everything in messages
['role' => 'system', 'content' => 'You are helpful'],
['role' => 'user', 'content' => 'Hello'],

// Responses API: system → instructions, rest → input
'instructions' => 'You are helpful',
'input' => [['role' => 'user', 'content' => 'Hello']],
```

**Solution**: `OpenResponsesBodyFormat` extracts system/developer messages into `instructions` field and passes remaining messages to `input` via the message formatter.

### 2. Response Parsing - Polymorphic Output Items

The `output[]` array contains heterogeneous items:

```json
{
  "output": [
    {"type": "message", "role": "assistant", "content": [{"type": "output_text", "text": "..."}]},
    {"type": "function_call", "call_id": "...", "name": "get_weather", "arguments": "{}"},
    {"type": "reasoning", "content": [{"type": "reasoning_text", "text": "..."}]}
  ]
}
```

**Solution**: `OpenResponsesResponseAdapter::fromResponse()` iterates through items, dispatching by `type` to extract:
- Content from `message` items
- Tool calls from `function_call` items
- Reasoning from `reasoning` items

### 3. Streaming Event Semantics

Chat Completions streams raw deltas; Responses API streams semantic events:

```
// Chat Completions
data: {"choices":[{"delta":{"content":"Hel"}}]}

// Responses API
data: {"type":"response.output_text.delta","delta":"Hel"}
data: {"type":"response.function_call_arguments.delta","call_id":"...","delta":"{\"city\":"}
data: {"type":"response.completed"}
```

**Solution**: `OpenResponsesResponseAdapter::fromStreamEvent()` parses the `type` field and routes to appropriate handlers, accumulating content/reasoning/tool args based on event type.

### 4. Status → Finish Reason Mapping

The Responses API uses `status` instead of `finish_reason`:

```php
// In OpenResponsesResponseAdapter
protected function mapStatus(string $status): string {
    return match($status) {
        'completed' => 'stop',
        'incomplete' => 'length',
        'failed' => 'error',
        'in_progress' => '',
        default => $status,
    };
}
```

This required updating `InferenceFinishReason` enum to recognize these new status strings.

## Incompatibilities with Existing Driver Architecture

### 1. The `messages` vs `input` Problem

Our `InferenceRequest` centers on a `messages` array. The Responses API accepts either:
- A string prompt directly
- An `input` array with items

**Design Decision**: Keep using our `messages` internally, let `OpenResponsesMessageFormat` transform them. This maintains backward compatibility with existing code.

### 2. Response Format Wrapper Differences

Chat Completions uses:
```json
{"response_format": {"type": "json_schema", "json_schema": {...}}}
```

Responses API uses:
```json
{"text": {"format": {"type": "json_schema", "schema": {...}}}}
```

**Solution**: `OpenResponsesBodyFormat::toTextFormat()` wraps the response format in the `text.format` structure.

### 3. Tool Definition Format

Both APIs use the same tool definition structure (externally tagged), but tool **calls** differ:

```php
// Chat Completions tool call (in message)
"tool_calls": [{"id": "...", "type": "function", "function": {"name": "...", "arguments": "..."}}]

// Responses API tool call (as output item)
{"type": "function_call", "call_id": "...", "name": "...", "arguments": "..."}
```

**Solution**: `OpenResponsesResponseAdapter::extractToolCalls()` handles the internally-tagged format.

### 4. Streaming Termination

Chat Completions ends with `data: [DONE]`. Responses API ends with `response.completed` event.

**Solution**: In tests, we use `replySSEFromJson(..., addDone: false)` and the adapter recognizes `response.completed` as the terminal event.

## Architecture

```
packages/polyglot/src/Inference/Drivers/
├── OpenResponses/                    # Base driver (multi-provider)
│   ├── OpenResponsesDriver.php       # Main driver class
│   ├── OpenResponsesRequestAdapter.php
│   ├── OpenResponsesBodyFormat.php   # instructions/input/max_output_tokens
│   ├── OpenResponsesMessageFormat.php
│   ├── OpenResponsesResponseAdapter.php  # Polymorphic output parsing
│   └── OpenResponsesUsageFormat.php
│
└── OpenAIResponses/                  # OpenAI-specific
    ├── OpenAIResponsesDriver.php     # OpenAI auth headers
    └── OpenAIResponsesRequestAdapter.php
```

## Key Design Principle

We followed the existing driver pattern but created **new adapter classes** rather than extending OpenAI adapters. This was the right choice because:

1. **Response parsing is fundamentally different** - `output[]` items vs `choices[].message`
2. **Request body structure differs significantly** - `instructions`/`input` vs `messages`
3. **Streaming semantics are incompatible** - semantic events vs raw deltas
4. **Independent evolution** - Responses API will gain features Chat Completions won't

The abstraction boundary (`InferenceRequest` → Driver → `InferenceResponse`) absorbed these differences, keeping consuming code unchanged.

## Usage

### Basic Inference

```php
$response = (new Inference())
    ->using('openai-responses')
    ->withModel('gpt-4o')
    ->withMessages([
        ['role' => 'system', 'content' => 'You are helpful'],
        ['role' => 'user', 'content' => 'Hello'],
    ])
    ->get();
```

### Streaming

```php
$stream = (new Inference())
    ->using('openai-responses')
    ->withModel('gpt-4o')
    ->withMessages('Hello')
    ->withStreaming(true)
    ->stream();

foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta();
}

$final = $stream->final();
```

### Structured Output

```php
$user = (new StructuredOutput())
    ->using('openai-responses')
    ->with(
        messages: 'Extract user info from: John is 25 years old',
        responseModel: User::class,
        mode: OutputMode::JsonSchema,
    )
    ->get();
```

## Configuration

The `openai-responses` preset in `config/llm.php`:

```php
'openai-responses' => [
    'driver' => 'openai-responses',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => Env::get('OPENAI_API_KEY', ''),
    'endpoint' => '/responses',
    'model' => 'gpt-4.1-nano',
    'maxTokens' => 1024,
],
```

## Tests

- **Unit tests**: `packages/polyglot/tests/Unit/Drivers/OpenResponses/` (48 tests)
- **Mock HTTP tests**: `packages/polyglot/tests/Feature/MockHttp/InferenceOpenAIResponses*.php` (12 tests)
