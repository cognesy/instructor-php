# OpenResponses vs OpenAI Responses API: Key Differences

## Overview

OpenResponses is an open-source specification based on OpenAI's Responses API, designed for multi-provider interoperability. While largely compatible, there are important distinctions.

## What OpenResponses Adds/Standardizes

### 1. Multi-Provider by Design
- Single schema that maps cleanly to many model providers (NVIDIA, Hugging Face, Vercel, Ollama, etc.)
- Provider-specific extensions use prefixed types: `"type": "provider_slug:custom_type"`
- Routers can orchestrate requests across multiple providers

### 2. Explicit Item State Machine
All items MUST include:
- `id`: Unique opaque identifier
- `type`: Discriminator (standard or `provider_slug:custom_type`)
- `status`: Lifecycle state (`in_progress`, `incomplete`, `completed`, `failed`)

### 3. Reasoning Visibility
OpenResponses formalizes three optional reasoning fields:
- `content`: Raw reasoning traces
- `encrypted_content`: Provider-specific protected content
- `summary`: Sanitized summary from raw traces

### 4. Streaming Event Structure
Extended streaming events MUST include:
- `type`: String identifier (non-standard prefixed with implementor slug)
- `sequence_number`: Monotonically increasing for ordering/gap detection

### 5. Extension Points
- Custom item types: `"type": "acme:telemetry_chunk"`
- Custom streaming events: `"type": "acme:trace_event"`
- Providers can add optional fields to standard schemas

### 6. Provider vs Router Architecture
- **Model Providers**: Supply inference capabilities
- **Routers**: Intermediary orchestrators between clients and multiple providers
- Client specifies both model and provider: `"model": "moonshotai/Kimi-K2-Thinking:nebius"`

## Core Endpoint

Both use: `POST /v1/responses`

## Request Parameters (Common)

| Parameter | Type | Description |
|-----------|------|-------------|
| `model` | string | Model identifier |
| `input` | string/array | Context (string = user message, array = items) |
| `instructions` | string | System/developer instructions |
| `tools` | array | Tool definitions |
| `tool_choice` | string/object | Tool selection control |
| `stream` | boolean | Enable SSE streaming |
| `temperature` | number | 0-2, randomness |
| `top_p` | number | 0-1, nucleus sampling |
| `max_output_tokens` | integer | Generation limit |
| `previous_response_id` | string | Chain/fork conversations |
| `truncation` | string | `auto` or `disabled` |
| `metadata` | object | Key-value pairs |

## OpenAI-Specific Parameters

| Parameter | Description |
|-----------|-------------|
| `store` | Enable/disable storage (default: true) |
| `background` | Async execution |
| `service_tier` | auto/default/flex/priority |
| `reasoning` | Reasoning effort configuration |
| `include` | Request additional outputs (logprobs, encrypted reasoning) |
| `text.format` | Structured output format |
| `max_tool_calls` | Cap built-in tool calls |
| `parallel_tool_calls` | Enable parallel tool execution |

## OpenResponses-Specific Parameters

| Parameter | Description |
|-----------|-------------|
| `OpenResponses-Version` header | Spec version |
| `allowed_tools` | Restrict which tools model can invoke |

## Standard Item Types

### Message
```json
{
  "type": "message",
  "id": "msg_...",
  "role": "assistant",
  "status": "completed",
  "content": [{"type": "output_text", "text": "..."}]
}
```

### Function Call
```json
{
  "type": "function_call",
  "id": "fc_...",
  "name": "sendEmail",
  "call_id": "call_...",
  "arguments": "{...}",
  "status": "completed"
}
```

### Function Call Output
```json
{
  "type": "function_call_output",
  "call_id": "call_...",
  "output": "result string",
  "status": "completed"
}
```

### Reasoning
```json
{
  "type": "reasoning",
  "status": "completed",
  "summary": [{"type": "summary_text", "text": "..."}],
  "content": [{"type": "output_text", "text": "..."}],
  "encrypted_content": null
}
```

## Content Types

### User Input Content
- `input_text`: Plain text
- `input_image`: Image URL or base64
- `input_file`: File URL or data

### Model Output Content
- `output_text`: Text with optional annotations
- `refusal`: Refusal message

## Streaming Events (OpenResponses Standard)

### Delta Events
- `response.output_item.added`
- `response.content_part.added`
- `response.output_text.delta`
- `response.output_text.done`
- `response.content_part.done`
- `response.output_item.done`

### State Machine Events
- `response.in_progress`
- `response.completed`
- `response.failed`
- `response.incomplete`

### Event Structure
```json
{
  "type": "response.output_text.delta",
  "sequence_number": 13,
  "item_id": "msg_...",
  "output_index": 3,
  "content_index": 0,
  "delta": "Here"
}
```

## Tool Types

### External Tools (Both)
- Function tools with JSON schema parameters
- Executed by client, results sent back

### Internal/Built-in Tools (OpenAI)
- `web_search`
- `file_search`
- `code_interpreter`
- `computer_use`
- `image_generation`
- `mcp`

### Provider-Specific Tools (OpenResponses)
```json
{
  "type": "implementor_slug:custom_document_search",
  "documents": [{"type": "external_file", "url": "https://..."}]
}
```

## Error Structure

```json
{
  "error": {
    "message": "...",
    "type": "invalid_request_error",
    "param": "model",
    "code": "model_not_found"
  }
}
```

| Type | Status |
|------|--------|
| `server_error` | 500 |
| `invalid_request` | 400 |
| `not_found` | 404 |
| `model_error` | 500 |
| `too_many_requests` | 429 |

## Compliance Testing

OpenResponses provides acceptance tests to validate API endpoints against the specification.

## Sources
- https://www.openresponses.org/
- https://www.openresponses.org/specification
- https://www.openresponses.org/reference
- https://huggingface.co/blog/open-responses
- https://gpt.gekko.de/openai-api-comparison-chat-responses-assistants-2025/
