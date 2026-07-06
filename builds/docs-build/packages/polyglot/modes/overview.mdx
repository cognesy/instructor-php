---
title: Response Shapes
description: Control how LLM providers structure their output using native request fields.
---

One of Polyglot's key strengths is its ability to support various output formats from LLM providers. This flexibility allows you to structure responses in the format that best suits your application, whether you need plain text, structured JSON data, or function and tool calls.

## How Response Shaping Works

Polyglot 2.0 does not use output mode enums. Instead, response shape is controlled by the same request fields that providers already expect:

- **Plain text** -- the default behavior when no format is specified
- **JSON object** -- set `responseFormat` with type `json_object`
- **JSON schema** -- set `responseFormat` with type `json_schema` and a schema definition
- **Tool calls** -- provide `tools` and `toolChoice` definitions

This approach keeps Polyglot close to the underlying APIs and makes requests easier to reason about. You work with the same concepts you would find in the provider's own documentation, without an additional abstraction layer in between.

## Response Shapes at a Glance

| Shape | Request Fields | Best For |
|---|---|---|
| Plain text | _(none)_ | Simple text generation, conversations, summaries |
| JSON object | `responseFormat: ResponseFormat::jsonObject()` | Structured data extraction without a strict schema |
| JSON schema | `responseFormat: ResponseFormat::jsonSchema(...)` | Strictly typed, schema-validated data |
| Tool calls | `tools: ToolDefinitions`, `toolChoice: ToolChoice` | Function calling, external actions, agent workflows |

## Choosing the Right Shape

Consider these factors when selecting a response shape:

1. **Data complexity.** More complex or nested data structures benefit from JSON Schema, which enforces the exact shape you need.
2. **Provider support.** Not every provider supports every shape natively. JSON object mode is widely supported; JSON Schema is currently best supported by OpenAI. Check your provider's capabilities before relying on a specific format.
3. **Consistency requirements.** When you need guaranteed structure across many requests, prefer JSON Schema or tool calls over plain JSON object mode.
4. **Application needs.** If the response will be parsed by downstream code, structured formats save you from fragile string parsing.

## Convenience Accessors

Polyglot provides several shortcut methods on the `Inference` facade for reading responses in different formats:

| Method | Returns | Use When |
|---|---|---|
| `get()` | `string` | You want the raw text content |
| `asJson()` | `string` | You want the JSON string from the response |
| `asJsonData()` | `array` | You want decoded JSON as a PHP array |
| `asToolCallJson()` | `string` | You want tool call arguments as a JSON string |
| `asToolCallJsonData()` | `array` | You want tool call arguments as a PHP array |
| `response()` | `InferenceResponse` | You need the full response object with metadata |
| `stream()` | `InferenceStream` | You want to stream partial responses in real time |

## Tips for Reliable Structured Output

For best results when requesting structured responses:

1. **Be explicit in prompts.** Clearly describe the expected format, including field names and types.
2. **Provide examples.** Show what a correct response looks like directly in your prompt.
3. **Use constraints.** Specify limits, required fields, and allowed values.
4. **Test across providers.** If your application supports multiple providers, verify that your chosen format works with each one.
5. **Implement fallbacks.** Have backup strategies for when a provider does not support your preferred format natively.
