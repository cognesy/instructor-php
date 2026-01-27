# OpenAI migration guide: Chat Completions API -> Responses API

This document is a consolidated, faithful rewrite of OpenAI's migration guidance for moving from Chat Completions to the Responses API, plus the related details that the official docs call out for request/response shape, parameter mapping, streaming, and tools. It is intentionally written in new words (no long verbatim excerpts), but it preserves all documented requirements, field names, and behaviors.

---

## 1) What the Responses API is (and why OpenAI recommends it)

OpenAI positions the Responses API as the new core primitive for model output. It unifies text and multimodal inputs, supports agentic tool usage, and provides stateful workflows. The docs list these benefits over Chat Completions:

- Better performance with reasoning-capable models (OpenAI reports a 3% SWE-bench improvement with the same prompt/setup).
- Agentic by default: a single response can chain multiple tool calls (web search, file search, code interpreter, computer use, MCP servers, or custom functions).
- Lower costs through improved caching (OpenAI reports 40% to 80% improved cache utilization in internal tests).
- Built-in statefulness: you can store and reuse response context between turns.
- Flexible input: pass a string or a list of messages/items, and separate top-level instructions from user input.
- Encrypted reasoning: remain stateless while preserving reasoning context.
- Future-proofing for upcoming models and features.

Capabilities comparison in the guide indicates that Responses covers text, vision, structured outputs, function calling, web search, file search, computer use, code interpreter, MCP, image generation, and reasoning summaries (with audio noted as coming soon in the Chat Completions comparison).

---

## 2) Core conceptual differences

### Messages vs. Items

- Chat Completions uses a **messages[]** array where each message blends role, text, and other concerns.
- Responses uses **items**, where each item is a typed unit of context. A message is just one item type; tool calls and tool outputs are separate item types.

### Single generation

- Chat Completions can return multiple choices using `n`.
- Responses **removes `n`**; you always get a single output stream of items.

### Response object shape

- Chat Completions returns a `chat.completion` object with a `choices[]` array; each choice contains a `message`.
- Responses returns a `response` object with an `output[]` array of typed items (for example, a reasoning item followed by a message item).

### Storage defaults

- Responses are stored by default.
- Chat Completions are stored by default for new accounts.
- In both APIs, set `store: false` to disable storage.

---

## 3) Request / response structure differences (high-level)

### Chat Completions request (shape)

```json
{
  "model": "gpt-5",
  "messages": [
    { "role": "system", "content": "..." },
    { "role": "user", "content": "..." }
  ]
}
```

### Responses request (shape)

```json
{
  "model": "gpt-5",
  "instructions": "...",
  "input": "..."  
}
```

Notes:
- `input` can be a string or a list of items/messages.
- If you already have a `messages[]` array, you can pass it directly as `input` for a straightforward migration.

### Chat Completions response (shape)

```json
{
  "id": "chatcmpl_...",
  "object": "chat.completion",
  "choices": [
    {
      "index": 0,
      "message": { "role": "assistant", "content": "..." },
      "finish_reason": "stop"
    }
  ]
}
```

### Responses response (shape)

```json
{
  "id": "resp_...",
  "object": "response",
  "output": [
    { "type": "reasoning", "content": [], "summary": [] },
    {
      "type": "message",
      "role": "assistant",
      "content": [
        { "type": "output_text", "text": "..." }
      ]
    }
  ]
}
```

Additional differences from the guide:
- Structured Outputs: move from `response_format` (Chat Completions) to `text.format` (Responses).
- Function calling: request tool definitions and response tool calls use different shapes and are itemized.
- Responses SDK includes an `output_text` convenience accessor.
- State and context: Responses supports `previous_response_id` or the Conversations API for persistence.

---

## 4) Step-by-step migration (as described in the guide)

### Step 1: Update generation endpoints

- Change endpoint from `POST /v1/chat/completions` to `POST /v1/responses`.
- For simple text-only usage, you can pass the same messages list as `input` and you are done.

### Step 2: Update item definitions

- Chat Completions uses `messages[]` with `role` and `content`.
- Responses allows `instructions` as a top-level system/developer message and `input` as the user content or item list.

### Step 3: Update multi-turn conversations

Chat Completions:
- You store and mutate `messages[]` yourself.
- Append the assistant message from `choices[0].message` for each turn.

Responses:
- Append the response `output[]` items back into your `input` list.
- You can also bypass manual concatenation by using `previous_response_id` on subsequent requests (as long as you store responses).

### Step 4: Decide when to use statefulness

If you need stateless behavior (for example, ZDR compliance):
- Set `store: false`.
- Add `"reasoning.encrypted_content"` to the `include` list.

The API returns encrypted reasoning items that you can pass back on the next request. For ZDR organizations, `store: false` is enforced automatically, encrypted reasoning is decrypted only in memory, and any new reasoning is returned encrypted.

### Step 5: Update function definitions

Two documented differences:
1) Chat Completions uses **externally-tagged** function definitions.
2) Responses uses **internally-tagged** function definitions, and functions are **strict by default** in Responses.

Chat Completions function definition:

```json
{
  "type": "function",
  "function": {
    "name": "get_weather",
    "description": "...",
    "strict": true,
    "parameters": { "type": "object", "properties": {"location": {"type": "string"}} }
  }
}
```

Responses function definition:

```json
{
  "type": "function",
  "name": "get_weather",
  "description": "...",
  "parameters": { "type": "object", "properties": {"location": {"type": "string"}} }
}
```

Best practice note from the guide:
- In Responses, tool calls and tool outputs are **separate item types**, correlated by a `call_id`.

### Step 6: Update Structured Outputs

- Chat Completions uses `response_format`.
- Responses uses `text.format` (for example, `text.format.type = "json_schema"`).

### Step 7: Upgrade to native tools

Chat Completions:
- No native OpenAI tools. You must implement your own web search or other tools as functions.

Responses:
- Use built-in tools directly by listing them in `tools`, e.g. `[{"type": "web_search"}]`.

---

## 5) Parameter mapping (Chat Completions -> Responses)

Below is the pragmatic mapping implied by the docs. If there is no direct equivalent, it is noted explicitly.

| Chat Completions | Responses | Notes |
|---|---|---|
| `POST /v1/chat/completions` | `POST /v1/responses` | Endpoint change. |
| `messages` | `input` | `input` can be a string or an item list; you can pass the same messages array directly as `input`. |
| `system`/`developer` messages | `instructions` (or item) | Top-level `instructions` is the preferred way to set system or developer behavior. |
| `response_format` | `text.format` | Structured Outputs moved under `text.format`. |
| `functions` (deprecated) | `tools` with `{ type: "function", ... }` | Function definitions are internally tagged in Responses. |
| `function_call` (deprecated) | `tool_choice` | Use `tool_choice` to force a function/tool or allow auto selection. |
| `tools` | `tools` | In Responses, `tools` can include built-in tools, MCP tools, or custom function tools. |
| `tool_choice` | `tool_choice` | Same concept; Responses also supports `required` mode for forcing tool usage. |
| `n` | (removed) | Responses always returns a single generation. |
| `max_tokens` / `max_completion_tokens` | `max_output_tokens` | Responses uses output token cap. |
| `stream` | `stream` | Streaming still supported; event types differ. |
| `temperature` | `temperature` | Same behavior. |
| `top_p` | `top_p` | Same behavior. |
| `presence_penalty` / `frequency_penalty` | (no 1:1 documented mapping) | Not explicitly listed in Responses request body in the docs excerpt; use only if supported by your model. |
| `logprobs` | `include: ["message.output_text.logprobs"]` | Responses uses `include` to request logprobs in outputs. |
| `store` | `store` | Defaults to true in Responses; use false to disable. |
| (none) | `previous_response_id` | Lets you chain or fork conversations without managing a message list. |
| (none) | `conversation` | Connects responses to a stored conversation. |
| (none) | `parallel_tool_calls` | Controls parallel tool calls in Responses. |
| (none) | `max_tool_calls` | Caps total built-in tool calls for the response. |
| (none) | `reasoning` | Reasoning configuration for reasoning models. |
| (none) | `include` | Controls additional output fields (sources, logprobs, encrypted reasoning, etc.). |

---

## 6) Streaming changes and event types

### Enable streaming in Responses

Set `stream: true` on the Responses request. The API returns **semantic event objects** instead of raw token chunks.

### Common events for text streaming

- `response.created`
- `response.output_text.delta`
- `response.completed`
- `error`

### Full event type list (from the streaming guide)

The guide lists these event types:

- `ResponseCreatedEvent`
- `ResponseInProgressEvent`
- `ResponseFailedEvent`
- `ResponseCompletedEvent`
- `ResponseOutputItemAdded`
- `ResponseOutputItemDone`
- `ResponseContentPartAdded`
- `ResponseContentPartDone`
- `ResponseOutputTextDelta`
- `ResponseOutputTextAnnotationAdded`
- `ResponseTextDone`
- `ResponseRefusalDelta`
- `ResponseRefusalDone`
- `ResponseFunctionCallArgumentsDelta`
- `ResponseFunctionCallArgumentsDone`
- `ResponseFileSearchCallInProgress`
- `ResponseFileSearchCallSearching`
- `ResponseFileSearchCallCompleted`
- `ResponseCodeInterpreterInProgress`
- `ResponseCodeInterpreterCallCodeDelta`
- `ResponseCodeInterpreterCallCodeDone`
- `ResponseCodeInterpreterCallInterpreting`
- `ResponseCodeInterpreterCallCompleted`
- `Error`

### Streaming caveat

The streaming guide cautions that streaming in production can complicate moderation because partial completions are harder to evaluate. Review your moderation strategy if you stream.

---

## 7) Tool definitions in Responses

### Tool categories (Responses API)

The docs describe three categories of tools you can supply in the `tools` array:

1) **Built-in tools** (OpenAI-provided)
2) **MCP tools** (remote tools via MCP servers / connectors)
3) **Function tools** (custom tools defined by you)

### Built-in tool definitions (request-side)

From the tools guide and migration examples:

- Web search:
  ```json
  { "type": "web_search" }
  ```
- File search (requires vector store IDs):
  ```json
  { "type": "file_search", "vector_store_ids": ["<vector_store_id>"] }
  ```
- Code interpreter:
  ```json
  { "type": "code_interpreter" }
  ```
- Computer use:
  ```json
  { "type": "computer_use" }
  ```
- Image generation:
  ```json
  { "type": "image_generation" }
  ```
- MCP tool (high level; details depend on your MCP server):
  ```json
  { "type": "mcp", "server_label": "<server_label>" }
  ```

### Function tool definitions (request-side)

Function tools are defined inside the `tools` array using a JSON schema:

```json
{
  "type": "function",
  "name": "get_weather",
  "description": "Get current weather for a location",
  "parameters": {
    "type": "object",
    "properties": { "location": { "type": "string" } },
    "required": ["location"],
    "additionalProperties": false
  },
  "strict": true
}
```

Key points:
- In Responses, functions are **internally tagged** (no nested `function` object).
- Functions are **strict by default** in Responses (as noted in the migration guide).

### Tool call items and outputs (response-side)

Responses returns tool calls and tool outputs as **items** in the `output` array. These items are correlated by `call_id` (the guide explicitly highlights this). Typical item types include:

- `function_call` (tool call)
- `function_call_output` (tool output)
- `custom_tool_call` / `custom_tool_call_output` (for non-schema custom tools)
- Built-in tool call items for web search, file search, code interpreter, computer use, etc.

You then provide the tool outputs by sending items back to the model in a subsequent request.

---

## 8) Incremental migration strategy

The docs state that Responses is a superset of Chat Completions and that Chat Completions remains supported. You can migrate flows incrementally (e.g., move reasoning-heavy paths first) and complete the full migration later.

---

## 9) Minimal migration checklist

- [ ] Switch endpoint to `/v1/responses`.
- [ ] Replace `messages` with `input` (or pass the same list directly).
- [ ] Move system/developer content into `instructions`.
- [ ] Update structured outputs: `response_format` -> `text.format`.
- [ ] Update function definitions to the Responses shape and strict-by-default behavior.
- [ ] Update multi-turn logic to append `response.output` (or use `previous_response_id`).
- [ ] Rework streaming handlers to consume semantic events.
- [ ] Migrate custom tools to `tools` or enable built-in tools.
- [ ] Decide on statefulness: `store: true` vs `store: false` + `include: ["reasoning.encrypted_content"]`.

