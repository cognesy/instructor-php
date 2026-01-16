# Peer Review: Evals System Redesign

**Reviewer**: Claude Opus 4.5
**Date**: 2026-01-16

---

## Summary

The proposal addresses real pain points. Five specific gaps need resolution before implementation.

---

## Gap 1: InferenceCases Generates Unsupported Combinations - **IMPLEMENTED**

**Status**: ✅ Implemented (2026-01-16)

**Location**: `packages/evals/src/Executors/Data/InferenceCases.php:86-134`

**Problem**: The `make()` method generates a cartesian product of all presets × modes × streaming options without checking provider capabilities. Runtime failures occur when unsupported combinations execute.

**Solution Implemented**:

1. **Created `DriverCapabilities` value object** at `packages/polyglot/src/Inference/Data/DriverCapabilities.php`:
```php
readonly class DriverCapabilities {
    public function __construct(
        public array $outputModes = [],    // Empty = all supported
        public bool $streaming = true,
        public bool $toolCalling = true,
        public bool $jsonSchema = true,
        public bool $responseFormatWithTools = true,
    ) {}

    public function supportsOutputMode(OutputMode $mode): bool;
    public function supportsStreaming(): bool;
    public function supportsToolCalling(): bool;
    public function supportsJsonSchema(): bool;
    public function supportsResponseFormatWithTools(): bool;
    public function supports(OutputMode $mode, bool $streaming): bool;
}
```

2. **Added `capabilities()` method to driver contract** at `packages/polyglot/src/Inference/Contracts/CanHandleInference.php:42`:
```php
public function capabilities(?string $model = null): DriverCapabilities;
```

3. **Implemented default in BaseInferenceDriver** with full capabilities, and **driver-specific overrides**:

| Driver | JsonSchema | Tools | responseFormatWithTools | Model-Specific |
|--------|-----------|-------|------------------------|----------------|
| OpenAI | ✓ | ✓ | ✓ | No |
| Anthropic | ✗ (MdJson/Tools only) | ✓ | ✗ | No |
| Deepseek | Conditional | Conditional | ✗ | **Yes** (reasoner) |
| Gemini | ✓ | ✓ | ✗ | No |
| Perplexity | ✓ | ✗ | ✗ | No |
| Groq/Mistral/CohereV2/Fireworks/HuggingFace/Cerebras/OpenRouter | ✓ | ✓ | ✗ | No |
| SambaNova/GeminiOAI/A21 | ✗ | ✓ | varies | No |

4. **Modified `InferenceCases::make()` to filter by capabilities**:
```php
private function make() : Generator {
    $generator = Combination::generator(...);

    if (!$this->filterByCapabilities) {
        yield from $generator;
        return;
    }

    foreach ($generator as $case) {
        if ($this->isSupported($case)) {
            yield $case;
        }
    }
}

private function isSupported(InferenceCaseParams $case) : bool {
    $driver = $this->getDriverForPreset($case->preset);
    $capabilities = $driver->capabilities();

    if (!$capabilities->supportsOutputMode($case->mode)) return false;
    if ($case->mode === OutputMode::Tools && !$capabilities->supportsToolCalling()) return false;
    if ($case->isStreamed && !$capabilities->supportsStreaming()) return false;

    return true;
}
```

**Key Features**:
- Model-aware capabilities: `$driver->capabilities('deepseek-reasoner')` disables tools/jsonSchema
- Opt-out filtering: `InferenceCases::all(filterByCapabilities: false)` for unfiltered generation
- Zero runtime overhead for existing code (fail-open on errors)

**Test Coverage**:
- `packages/polyglot/tests/Unit/Data/DriverCapabilitiesTest.php` (17 tests)
- `packages/polyglot/tests/Unit/Drivers/DriverCapabilitiesIntegrationTest.php` (31 tests)
- `packages/evals/tests/Unit/InferenceCasesCapabilityFilteringTest.php` (11 tests)

**Acceptance**: ✅ Running `InferenceCases::all()` returns only executable combinations based on driver capabilities. Tests verify filtering for Anthropic (no JsonSchema/Json), Perplexity (no Tools), Deepseek-R (no Tools for reasoner model).

---

## Gap 2: Observations Not Persisted

**Location**: `packages/evals/src/Execution.php:86-95` and `packages/evals/src/Console/Display.php`

**Problem**: `Execution::toArray()` excludes the `$observations` array. Results exist only in console output and are lost after the script ends.

**Evidence**:
```php
// Execution.php:86-95
public function toArray() : array {
    return [
        'id' => $this->id(),
        'startedAt' => $this->startedAt(),
        'status' => $this->status(),
        'data' => $this->data(),
        'timeElapsed' => $this->timeElapsed(),
        'usage' => $this->usage(),
        'exception' => $this->exception(),
        // NOTE: observations NOT included
    ];
}
```
- `Observation::toArray()` at lines 61-69 has full serialization support but is never called for persistence
- `Display::displayObservations()` at lines 151-186 renders to stdout only

**Concrete Fix**:
1. Add to `Execution::toArray()`:
```php
'observations' => array_map(fn($o) => $o->toArray(), $this->observations),
```
2. Add to `Experiment::toArray()` (doesn't exist, create it):
```php
public function toArray(): array {
    return [
        'id' => $this->id,
        'startedAt' => $this->startedAt->format('Y-m-d H:i:s'),
        'executions' => array_map(fn($e) => $e->toArray(), $this->executions),
        'observations' => array_map(fn($o) => $o->toArray(), $this->observations),
        'timeElapsed' => $this->timeElapsed,
    ];
}
```
3. Create `packages/evals/src/Output/JsonFileWriter.php`:
```php
class JsonFileWriter implements CanObserveExperiment {
    public function observe(Experiment $experiment): Observation {
        file_put_contents($this->path, json_encode($experiment->toArray(), JSON_PRETTY_PRINT));
        return Observation::make('summary', 'experiment.output_path', $this->path);
    }
}
```

**Acceptance**: After `$experiment->execute()`, a JSON file exists with full execution data, observations, and metadata queryable offline.

---

## Gap 3: Streaming Has No Timeout

**Location**: `packages/polyglot/src/Inference/Streaming/EventStreamReader.php:62-73`

**Problem**: The `readLines()` method blocks indefinitely waiting for stream chunks. If a provider stalls, the eval hangs forever.

**Evidence**:
```php
// EventStreamReader.php:62-73
protected function readLines(iterable $stream): Generator {
    $buffer = '';
    foreach ($stream as $chunk) {  // <-- Blocks forever if no chunk arrives
        $buffer .= $chunk;
        while (false !== ($pos = strpos($buffer, "\n"))) {
            yield substr($buffer, 0, $pos + 1);
            $buffer = substr($buffer, $pos + 1);
        }
    }
    // ...
}
```
- No timeout configuration exists in `packages/polyglot/src/Inference/Config/`
- HTTP client timeout applies to initial connection, not per-chunk delivery
- `BaseInferenceDriver::httpResponseToInferenceStream()` at line 82-100 has no timeout wrapper

**Concrete Fix**:
1. Add timeout wrapper in `BaseInferenceDriver::httpResponseToInferenceStream()`:
```php
protected function httpResponseToInferenceStream(ResponseInterface $response): Generator {
    $timeout = $this->config->streamChunkTimeout ?? 30; // seconds
    $lastChunkTime = time();

    foreach ($this->reader->eventsFrom($this->toStream($response)) as $event) {
        if (time() - $lastChunkTime > $timeout) {
            throw new StreamTimeoutException("No chunk received in {$timeout}s");
        }
        $lastChunkTime = time();
        yield $this->adapter->fromStreamResponse($event);
    }
}
```
2. Add to `LLMConfig`:
```php
public int $streamChunkTimeout = 30;
```

**Acceptance**: Streaming evals timeout after configurable seconds of no data. No infinite hangs.

---

## Gap 4: Fixture Cache Strategy Undefined

**Location**: Proposal mentions fixture caching but no implementation details.

**Problem**: The proposal says "Save raw API responses keyed by case id + model + mode + schema hash" but doesn't specify:
- Where fixtures live (file path pattern)
- When to invalidate (age? schema change? manual?)
- How to generate cache keys

**Concrete Fix**:
Define the fixture system explicitly:

1. **Cache key formula**:
```php
$cacheKey = hash('sha256', json_encode([
    'preset' => $case->preset,
    'mode' => $case->mode->value,
    'streaming' => $case->isStreamed,
    'messagesHash' => hash('sha256', json_encode($request->messages())),
    'schemaHash' => hash('sha256', json_encode($request->responseFormat()?->schema() ?? [])),
]));
```

2. **File path**: `./fixtures/responses/{preset}/{cacheKey}.json`

3. **Invalidation policy**:
   - Fixtures expire after 30 days (configurable)
   - Fixtures invalidate when `schemaHash` changes (schema evolution)
   - Manual invalidation via `bin/evals fixtures:clear --preset=openai`

4. **Replay mode**:
```php
// In RunStructuredOutputInference::run()
if ($this->replayMode && $fixture = $this->loadFixture($cacheKey)) {
    $execution->set('response', $fixture);
    return;
}
// ... actual API call ...
$this->saveFixture($cacheKey, $response);
```

**Acceptance**: Running `bin/evals --replay` uses cached fixtures. Running `bin/evals --live` calls real APIs and saves fixtures.

---

## Gap 5: Error Taxonomy Missing

**Location**: `packages/evals/src/Execution.php:72-77`

**Problem**: Exceptions are caught and stored raw. No categorization exists for distinguishing retryable errors from permanent failures.

**Evidence**:
```php
// Execution.php:72-77
} catch(Exception $e) {
    $this->timeElapsed = microtime(true) - $time;
    $this->data()->set('output.notes', $e->getMessage());
    $this->exception = $e;  // <-- Raw exception, no classification
    $this->events->dispatch(new ExecutionFailed($this->toArray()));
    throw $e;
}
```
- `FeedbackType` enum at `packages/evals/src/Enums/FeedbackType.php` has only `Error`, `Improvement`, `Other`
- No mapping from provider exceptions to categories

**Concrete Fix**:
1. Create `packages/evals/src/Enums/EvalErrorCode.php`:
```php
enum EvalErrorCode: string {
    case NETWORK_TIMEOUT = 'E001';
    case RATE_LIMITED = 'E002';
    case INVALID_JSON_RESPONSE = 'E003';
    case SCHEMA_VALIDATION_FAILED = 'E004';
    case TOOL_CALL_MALFORMED = 'E005';
    case PROVIDER_INTERNAL_ERROR = 'E006';
    case UNSUPPORTED_MODE = 'E007';

    public function isRetryable(): bool {
        return match($this) {
            self::NETWORK_TIMEOUT, self::RATE_LIMITED, self::PROVIDER_INTERNAL_ERROR => true,
            default => false,
        };
    }
}
```

2. Create classifier `packages/evals/src/Utils/ExceptionClassifier.php`:
```php
class ExceptionClassifier {
    public static function classify(Exception $e): EvalErrorCode {
        return match(true) {
            $e instanceof ConnectException => EvalErrorCode::NETWORK_TIMEOUT,
            str_contains($e->getMessage(), '429') => EvalErrorCode::RATE_LIMITED,
            $e instanceof JsonException => EvalErrorCode::INVALID_JSON_RESPONSE,
            $e instanceof ValidationException => EvalErrorCode::SCHEMA_VALIDATION_FAILED,
            default => EvalErrorCode::PROVIDER_INTERNAL_ERROR,
        };
    }
}
```

3. Use in `Execution::execute()`:
```php
} catch(Exception $e) {
    $this->errorCode = ExceptionClassifier::classify($e);
    $this->data()->set('error.code', $this->errorCode->value);
    $this->data()->set('error.retryable', $this->errorCode->isRetryable());
    // ...
}
```

**Acceptance**: Every failed execution has an `error.code` and `error.retryable` flag. Reports can aggregate failures by error type.

---

## Out of Scope (Future Work)

These items are lower priority and can be deferred:
- Multi-turn conversation evals
- Cost tracking and budgeting
- Parallel execution
- Observability integration
- Human review workflow

---

## Verification

After implementation, run:
1. `php evals/SimpleExtraction/run.php` - should produce JSON artifact
2. Change a schema field - fixture cache should invalidate
3. Simulate network stall - should timeout within configured seconds
4. Check error classification on intentionally failing cases
