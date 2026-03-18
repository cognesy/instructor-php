---
title: Laravel
description: Laravel integration — service provider, facades, config publishing, and Artisan commands
package: laravel
---

# Laravel Package Cheatsheet

Code-verified reference for `packages/laravel`.

## Package Wiring

Auto-discovered provider:
- `Cognesy\Instructor\Laravel\InstructorServiceProvider`

Auto-discovered aliases:
- `StructuredOutput` -> `Cognesy\Instructor\Laravel\Facades\StructuredOutput`
- `Inference` -> `Cognesy\Instructor\Laravel\Facades\Inference`
- `Embeddings` -> `Cognesy\Instructor\Laravel\Facades\Embeddings`
- `AgentCtrl` -> `Cognesy\Instructor\Laravel\Facades\AgentCtrl`

## Service Container Bindings

Provided by `InstructorServiceProvider::provides()`:
- `Cognesy\Events\Contracts\CanHandleEvents`
- `Cognesy\Config\Contracts\CanProvideConfig`
- `Cognesy\Http\HttpClient`
- `Cognesy\Http\Contracts\CanSendHttpRequests`
- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\StructuredOutput`
- `Cognesy\Polyglot\Inference\Contracts\CanCreateInference`
- `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings`
- `Cognesy\Instructor\Contracts\CanCreateStructuredOutput`
- `Cognesy\Instructor\Laravel\Testing\StructuredOutputFake`
- `Cognesy\Instructor\Laravel\Testing\AgentCtrlFake`

## Facades

`StructuredOutput` facade:
- `StructuredOutput::connection(string $name): Cognesy\Instructor\StructuredOutput|StructuredOutputFake`
- `StructuredOutput::fromConfig(LLMConfig $config): Cognesy\Instructor\StructuredOutput|StructuredOutputFake`
- `StructuredOutput::fake(array $responses = []): StructuredOutputFake`
- forwards to `Cognesy\Instructor\StructuredOutput` (for example: `with(...)->get()`)

`Inference` facade:
- `Inference::connection(string $name): Cognesy\Polyglot\Inference\Inference|InferenceFake`
- `Inference::fromConfig(LLMConfig $config): Cognesy\Polyglot\Inference\Inference|InferenceFake`
- `Inference::fake(array $responses = []): InferenceFake`
- forwards to `Cognesy\Polyglot\Inference\Inference` (for example: `with(...)->get()`)

`Embeddings` facade:
- `Embeddings::connection(string $name): Cognesy\Polyglot\Embeddings\Embeddings|EmbeddingsFake`
- `Embeddings::fromConfig(EmbeddingsConfig $config): Cognesy\Polyglot\Embeddings\Embeddings|EmbeddingsFake`
- `Embeddings::fake(array $responses = []): EmbeddingsFake`
- forwards to `Cognesy\Polyglot\Embeddings\Embeddings` (for example: `withInputs(...)->first()`)

`AgentCtrl` facade:
- `AgentCtrl::fake(array $responses = []): AgentCtrlFake`
- `AgentCtrl::claudeCode(): ClaudeCodeBridgeBuilder|AgentCtrlFake`
- `AgentCtrl::codex(): CodexBridgeBuilder|AgentCtrlFake`
- `AgentCtrl::openCode(): OpenCodeBridgeBuilder|AgentCtrlFake`
- `AgentCtrl::make(AgentType $type): ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder|AgentCtrlFake`

## Testing Fakes

`StructuredOutputFake`:
- setup: `respondWith(string $class, mixed $response)`, `respondWithSequence(string $class, array $responses)`
- assertions: `assertExtracted`, `assertExtractedTimes`, `assertNothingExtracted`, `assertExtractedWith`, `assertUsedConnection`, `assertUsedModel`
- inspection: `recorded(): array`

`InferenceFake`:
- setup: `respondWith(string $pattern, string|array $response)`, `respondWithSequence(array $responses)`
- assertions: `assertCalled`, `assertCalledTimes`, `assertNotCalled`, `assertCalledWith`, `assertUsedConnection`, `assertUsedModel`, `assertCalledWithTools`
- inspection: `recorded(): array`

`EmbeddingsFake`:
- setup: `respondWith(string $pattern, array $embedding)`, `withDimensions(int $dimensions)`
- assertions: `assertCalled`, `assertCalledTimes`, `assertNotCalled`, `assertCalledWith`, `assertUsedConnection`, `assertUsedModel`
- results: `get(): object`, `first(): array`, `all(): array`
- inspection: `recorded(): array`

`AgentCtrlFake`:
- builders: `claudeCode()`, `codex()`, `openCode()`, `make(AgentType $type)`
- builder config: `withModel(string $model)`, `withTimeout(int $seconds)`, `inDirectory(string $path)`, `withSandboxDriver(mixed $driver)`, `withMaxRetries(int $retries)`
- builder callbacks: `onText(callable $handler)`, `onToolUse(callable $handler)`, `onComplete(callable $handler)`
- execution: `execute(string $prompt): AgentResponse`, `executeStreaming(string $prompt): AgentResponse`
- assertions: `assertExecuted`, `assertNotExecuted`, `assertExecutedTimes`, `assertExecutedWith`, `assertAgentType`, `assertUsedClaudeCode`, `assertUsedCodex`, `assertUsedOpenCode`, `assertStreaming`
- inspection: `getExecutions(): array`, `reset(): void`
- static helpers: `AgentCtrlFake::response(...)`, `AgentCtrlFake::toolCall(...)`

## Artisan Commands

- `instructor:install {--force}`
- `instructor:test {--connection=} {--inference}`
- `make:response-model {name} {--collection} {--nested} {--description=} {--force}`

## Publish Tags

From `InstructorServiceProvider`:
- `instructor-config` -> publishes `config/instructor.php`
- `instructor-stubs` -> publishes `resources/stubs` to `stubs/instructor`

## Logging

Logging is handled by `InstructorLoggingServiceProvider`, which attaches a `LoggingPipeline`
wiretap to the shared `CanHandleEvents` event bus. The pipeline writes to a PSR-3 logger
(Laravel log channel).

Config file: `packages/laravel/resources/config/instructor-logging.php`

Relevant env vars:
- `INSTRUCTOR_LOGGING_ENABLED` (default: `true`)
- `INSTRUCTOR_LOGGING_PRESET` (`default` | `production` | `custom`)
- `INSTRUCTOR_LOG_CHANNEL` — Laravel log channel name (default: `instructor`)
- `INSTRUCTOR_LOG_LEVEL` — minimum PSR log level (default: `debug`)

Presets:
- `default` — warning level, excludes HTTP debug events, adds human-readable message templates
- `production` — warning level, minimal set of events
- `custom` — reads full config array from `instructor-logging.config`

### Structured / JSONL output

`INSTRUCTOR_LOG_PATH` (the standalone JSONL env var from `EventLog`) **has no effect inside
Laravel**. All runtimes receive a user-provided `CanHandleEvents` from the container, so
`EventLog::root()` is never called.

To get machine-parseable JSON log lines in Laravel, configure a log channel with Monolog's
`JsonFormatter`:

```php
// config/logging.php
'instructor-json' => [
    'driver'    => 'single',
    'path'      => storage_path('logs/instructor.jsonl'),
    'level'     => 'debug',
    'formatter' => Monolog\Formatter\JsonFormatter::class,
],
```

Then point `INSTRUCTOR_LOG_CHANNEL=instructor-json` at it.

## Config File

Main config file:
- `packages/laravel/resources/config/instructor.php`

Top-level keys:
- `default`
- `connections`
- `embeddings`
- `extraction`
- `http`
- `logging`
- `events`
- `cache`
- `agents`
