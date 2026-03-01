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
- `StructuredOutput::using(string $preset): Cognesy\Instructor\StructuredOutput|StructuredOutputFake`
- `StructuredOutput::fake(array $responses = []): StructuredOutputFake`
- forwards to `Cognesy\Instructor\StructuredOutput` (for example: `with(...)->get()`)

`Inference` facade:
- `Inference::fake(array $responses = []): InferenceFake`
- forwards to `Cognesy\Polyglot\Inference\Inference` (for example: `with(...)->get()`)

`Embeddings` facade:
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
- assertions: `assertExtracted`, `assertExtractedTimes`, `assertNothingExtracted`, `assertExtractedWith`, `assertUsedPreset`, `assertUsedModel`
- inspection: `recorded(): array`

`InferenceFake`:
- setup: `respondWith(string $pattern, string|array $response)`, `respondWithSequence(array $responses)`
- assertions: `assertCalled`, `assertCalledTimes`, `assertNotCalled`, `assertCalledWith`, `assertUsedPreset`, `assertUsedModel`, `assertCalledWithTools`
- inspection: `recorded(): array`

`EmbeddingsFake`:
- setup: `respondWith(string $pattern, array $embedding)`, `withDimensions(int $dimensions)`
- assertions: `assertCalled`, `assertCalledTimes`, `assertNotCalled`, `assertCalledWith`, `assertUsedPreset`, `assertUsedModel`
- inspection: `recorded(): array`

`AgentCtrlFake`:
- builders: `claudeCode()`, `codex()`, `openCode()`, `make(AgentType $type)`
- execution: `execute(string $prompt)`, `executeStreaming(string $prompt)`
- assertions: `assertExecuted`, `assertNotExecuted`, `assertExecutedTimes`, `assertExecutedWith`, `assertAgentType`, `assertUsedClaudeCode`, `assertUsedCodex`, `assertUsedOpenCode`, `assertStreaming`
- helpers: `getExecutions(): array`, `reset(): void`, `response(...)`, `toolCall(...)`

## Artisan Commands

- `instructor:install {--force}`
- `instructor:test {--preset=} {--inference}`
- `make:response-model {name} {--collection} {--nested} {--description=} {--force}`

## Publish Tags

From `InstructorServiceProvider`:
- `instructor-config` -> publishes `config/instructor.php`
- `instructor-stubs` -> publishes `resources/stubs` to `stubs/instructor`

## Config File

Main config file:
- `packages/laravel/config/instructor.php`

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
