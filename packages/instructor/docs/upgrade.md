---
title: Upgrade
description: 'Migrate structured-output applications to the current API.'
---

## Upgrading to 2.5

### Output targets are explicit

A plain JSON Schema now returns an associative array. It no longer creates an internal
Dynamic `Structure` on the way to the result.

```php
$result = (new StructuredOutput)
    ->with(messages: $text, responseModel: $jsonSchema)
    ->get();

// array<string, mixed>
```

Use `x-php-class` in the schema or `intoInstanceOf()` to request class hydration. Use
`intoStdClass()` for `stdClass`. `intoArray()` remains useful when overriding a
class-backed schema.

Passing a Dynamic `Structure` explicitly preserves its identity:

```php
$result = (new StructuredOutput)
    ->with(messages: $text, responseModel: $structure)
    ->get();

$data = $result->toArray();
```

Remove workarounds that expected plain schemas to return `Structure`, including
unnecessary `instanceof Structure` checks and `toArray()` calls.

### Validation and failures

Plain arrays now pass through schema validation before being returned. Synchronous and
completed streaming responses share the same materialization path, and transformation
failures fail the attempt instead of silently returning untransformed data.

An unknown root `x-php-class` now fails response-model preparation. Correct or remove
the metadata, or explicitly select another output target.

### Prompt customization

`StructuredPromptRequestMaterializer` is the default. Customize bundled structured
output behavior with `modePromptClasses`, `retryPromptClass`, and
`deserializationErrorPromptClass`.

The inline `modePrompts`, `retryPrompt`, and `chatStructure` settings are read only when
an application explicitly injects the deprecated legacy `RequestMaterializer`. They are
scheduled for removal in 2.6.

### Deprecated compatibility APIs

- Replace `intoObject()` with `intoSelfDeserializing()`.
- Replace global `defaultToStdClass` configuration with per-request `intoStdClass()`.
- Keep `PendingStructuredOutput::toJsonObject()`, `toJson()`, and `toArray()` unchanged;
  these methods still inspect the raw inference response.

### Dynamic is now optional

`cognesy/instructor-struct` no longer requires the Dynamic package. Applications that
import `Cognesy\Dynamic` classes directly must declare their own dependency:

```bash
composer require cognesy/instructor-dynamic:^2.5
```

## 2.0-era API overview

The current docs use the runtime-first 2.x structured-output API.

## What Changed

The public model is now:

- `StructuredOutput` for request construction
- `StructuredOutputRuntime` for runtime behavior
- `PendingStructuredOutput` for lazy execution
- `StructuredOutputResponse` as the primary final response object
- `StructuredOutputStream` for streaming reads and final stream access

## Response Ownership

Older docs and examples often treated the raw Polyglot response as the main response object.

That is no longer the intended API.

- use `response()` when you want the final Instructor response
- use `get()` when you want only the parsed value
- use `inferenceResponse()` or `finalInferenceResponse()` only when you need raw transport-level details

## Streaming Contract

Streaming is now built around Instructor-owned stream state.

- Polyglot streams deltas
- Instructor accumulates those deltas in `StructuredOutputStreamState`
- final stream reads return `StructuredOutputResponse`, not raw partial snapshot objects

If you relied on old partial snapshot behavior, update that code to consume:

- `stream()->responses()` for partial and final `StructuredOutputResponse` items
- `stream()->partials()` for parsed partial values
- `stream()->sequence()` for completed sequence items

## Runtime Setup

Runtime configuration belongs on `StructuredOutputRuntime`, not on a global Instructor object.

- `create()` returns a lazy handle
- `stream()` returns a dedicated stream object
- `StructuredOutput::fromConfig(...)` and `StructuredOutput::using(...)` remain valid entry points
- published config files are optional

## Event Namespaces in 2.5

Structured-output events now live in namespaces that match their lifecycle
stage. Update listener imports as follows:

| Previous namespace | Current namespace |
|---|---|
| `Events\PartialsGenerator\*` | `Events\Streaming\*` |
| `Events\Request\ResponseModel*` | `Events\ResponseModel\ResponseModel*` |
| `Events\Request\SequenceUpdated` | `Events\Streaming\SequenceUpdated` |

The old aliases were removed after the source, documentation, and public-usage
audit found no consumers. Listener registration must use the current event
class name.

The old validation-only recovery events and the result-specific
`ResponseConvertedToObject` and `ResponseGenerationFailed` events were also removed.
Use these result-neutral lifecycle events instead:

| Removed event | Current event |
|---|---|
| `NewValidationRecoveryAttempt` | `Events\Attempt\ResponseRetryScheduled` |
| `StructuredOutputRecoveryLimitReached` | `Events\Attempt\ResponseRecoveryExhausted` |
| `Events\Response\ResponseConvertedToObject` | `Events\Response\ResponseMaterialized` |
| `Events\Response\ResponseGenerationFailed` | `Events\Response\ResponseMaterializationFailed` |

## Migration Rule

If you are updating older code, rewrite it around one of these shapes:

- `StructuredOutput->with(...)->get()`
- `StructuredOutput->with(...)->response()`
- `StructuredOutput->with(...)->stream()`
