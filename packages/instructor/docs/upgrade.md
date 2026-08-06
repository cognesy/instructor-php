---
title: Upgrade
description: 'Migrate structured-output applications to the current API.'
---

## Upgrading to 2.7

### `StructuredOutputConfigBuilder` removed

`Cognesy\Instructor\Creation\StructuredOutputConfigBuilder` is gone. It was a mutable second
representation of the same settings, kept in sync with `StructuredOutputConfig` by hand. Build
configs directly instead — every `with*()` method has the same name on `StructuredOutputConfig`,
which returns a new instance rather than mutating, so existing chains carry over by dropping the
trailing `->create()`:

```php
// before
$config = (new StructuredOutputConfigBuilder())
    ->withOutputMode(OutputMode::Json)
    ->withMaxRetries(2)
    ->create();

// after
$config = (new StructuredOutputConfig())
    ->withOutputMode(OutputMode::Json)
    ->withMaxRetries(2);
```

The builder's `withConfig($defaults)` seed has no replacement: start the chain from that config
(`$defaults->withMaxRetries(2)`). `StructuredOutputConfig` gained `withSchemaDescription()` and
`withStreamMaterializationInterval()`, so every former builder method has a namesake with two
exceptions: `withThrowOnTransformationFailure()` and `withDefaultToStdClass()` have no fluent
equivalent on `StructuredOutputConfig`. Both were `@deprecated 2.5` no-ops — transformation
failures always fail the attempt, and `defaultToStdClass` was superseded by per-request
`intoStdClass()` — and both are still accepted as named arguments to the constructor and to
`with()`, so config round-trips keep working:

```php
$config = $config->with(throwOnTransformationFailure: true);
```

One semantic difference to check: the builder's `create()` merged `modePromptClasses` into the
defaults, whatever route set them. On `StructuredOutputConfig`, `withModePromptClass()` merges a
single mode into the existing map — the common case, unchanged — but `withModePromptClasses()` and
the constructor argument **replace** the map wholesale. If you were passing a partial map expecting
the remaining modes to keep their defaults, merge explicitly:

```php
$config = $config->withModePromptClasses([...$config->modePromptClasses(), ...$overrides]);
```

In `cognesy/instructor-agents`, `StructuredOutputPolicy::withConfigBuilder()` is accordingly
renamed to `withConfig()` and now takes and returns a `StructuredOutputConfig`.

### `CanEmitStreamingUpdates` renamed to `CanDriveExecution`

The internal execution-driver contract implemented by `SyncExecutionDriver` and
`StreamingExecutionDriver` has been renamed from `Cognesy\Instructor\Contracts\CanEmitStreamingUpdates`
to `Cognesy\Instructor\Contracts\CanDriveExecution`. The old name described the interface as a
streaming contract, but it is a pull-based execution driver used by both the sync and streaming
paths. Method signatures are unchanged; update any type hints referencing the old interface name.

### Legacy `RequestMaterializer` removed

The legacy `RequestMaterializer` is gone, along with the only code path that read the inline
`modePrompts`, `retryPrompt`, and `chatStructure` settings. Those settings have been removed
from `StructuredOutputConfig`, together with the
accessors `prompt()`, `modePrompts()`, `retryPrompt()`, `chatStructure()` and the mutators
`withRetryPrompt()`, `withModePrompt()`, `withModePrompts()`, `withChatStructure()`.

`StructuredOutputConfig::fromArray()` now ignores unknown keys instead of failing, so config
files, DSNs, and presets that still carry the removed keys keep loading — the values are
dropped. The Laravel `instructor.extraction.retry_prompt` key and the Symfony
`retry_prompt` / `mode_prompts` / `chat_structure` nodes were removed from their schemas.

Port these settings to prompt classes (`modePromptClasses`, `retryPromptClass`,
`deserializationErrorPromptClass`), or implement `CanMaterializeRequest` when prompt classes
are insufficient.

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
`deserializationErrorPromptClass`. See the 2.7 notes above for the removal of the legacy
inline prompt settings.

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
