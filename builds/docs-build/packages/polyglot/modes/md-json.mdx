---
title: Markdown JSON Fallback
description: Markdown-wrapped JSON is handled by Instructor, not by Polyglot directly.
---

Polyglot 2.0 does not expose a markdown-JSON response mode. In previous versions, `OutputMode::MdJson` instructed the model to wrap its JSON response in a Markdown code block (` ```json ... ``` `), and Polyglot would extract the JSON content automatically. This was useful as a compatibility fallback for providers that lacked native JSON output support.

## Why It Was Removed

Polyglot 2.0 is designed to model only native provider request fields. Since markdown-wrapped JSON is a prompt-based convention rather than a native API feature, it does not belong in Polyglot's abstraction layer.

The response shape in Polyglot 2.0 is controlled entirely by standard request parameters:

- `responseFormat` for JSON object and JSON schema modes
- `tools` and `toolChoice` for tool calling

There is no `responseFormat` type for "respond with JSON inside a Markdown code block" because no provider API supports that natively.

## What to Use Instead

If you need prompt-based JSON fallback strategies -- for example, when working with providers that do not support native JSON output -- use the **Instructor** layer above Polyglot. Instructor handles response format negotiation, prompt engineering for structured output, and extraction of JSON from various response formats, including Markdown-wrapped JSON.

For providers that do support native JSON output, use [JSON object mode](/modes/json) or [JSON Schema mode](/modes/json-schema) directly through Polyglot's `responseFormat` field.

## Migration from 1.x

If your application previously used `OutputMode::MdJson`, you have two migration paths:

1. **Switch to native JSON.** If your provider supports it, use `responseFormat: ['type' => 'json_object']` for the same structured output with better reliability.
2. **Use Instructor.** If you need the Markdown JSON fallback for providers without native JSON support, move that logic to the Instructor layer, which provides automatic format negotiation and extraction.
