# StructuredOutput Processing Flow (Walkthrough)

**Date:** 2026-01-03
**Scope:** packages/instructor/src

## Entry Point: `StructuredOutput`

Main API is `StructuredOutput` (`packages/instructor/src/StructuredOutput.php`).

1. `StructuredOutput::with(...)`
   - Populates `StructuredOutputRequestBuilder` and `StructuredOutputConfigBuilder`.
2. `StructuredOutput::create()`
   - Builds `StructuredOutputConfig` + `StructuredOutputRequest`.
   - Builds `StructuredOutputExecution` via `StructuredOutputExecutionBuilder`.
   - Applies `OutputFormat` override to `ResponseModel` (array/object selection).
   - Instantiates response pipeline services:
     - `ResponseDeserializer`
     - `ResponseValidator`
     - `ResponseTransformer`
     - `PartialValidation`
     - `ResponseExtractor` (optional; used for array-first pipeline)
   - Ensures `HttpClient` exists.
   - Builds `ResponseIteratorFactory` (injecting all above).
   - Returns `PendingStructuredOutput`.

## Non-Streaming Execution (get/response)

Path: `StructuredOutput::get()` → `PendingStructuredOutput::get()`.

1. `PendingStructuredOutput::getResponse()`
   - Dispatches `StructuredOutputStarted`.
   - Uses `AttemptIterator` (from `ResponseIteratorFactory::makeExecutor`).
2. `AttemptIterator`
   - Orchestrates attempts + retry logic.
   - Delegates to **sync stream iterator** (`SyncUpdateGenerator`) for the single chunk.
3. `SyncUpdateGenerator`
   - Uses `InferenceProvider` to make one LLM request.
   - Normalizes content (`ResponseNormalizer`) for the output mode.
   - Stores `StructuredOutputAttemptState::fromSingleChunk(...)`.
4. `AttemptIterator::finalizeAttempt()`
   - Uses `ResponseGenerator`:
     - If extractor is set → array-first pipeline (`ResponseExtractor` → deserialize → validate → transform).
     - Else → JSON string pipeline (extract JSON string → deserialize → validate → transform).
   - On success → `StructuredOutputExecution::withSuccessfulAttempt(...)`.
   - On failure → `DefaultRetryPolicy` records failure + retries or throws.

## Streaming Execution (stream)

Path: `StructuredOutput::stream()` → `PendingStructuredOutput::stream()` → `StructuredOutputStream`.

1. `StructuredOutputStream::getStream()`
   - Dispatches `StructuredOutputStarted`.
   - Iterates `AttemptIterator` until exhaustion, yielding `StructuredOutputExecution` updates.
2. `AttemptIterator`
   - Delegates to **stream update generator** chosen by `StructuredOutputConfig::responseIterator`:
     - `ModularUpdateGenerator`
     - `PartialUpdateGenerator`
     - `StreamingUpdatesGenerator` (legacy)
3. Stream update generator (all three share the same pattern)
   - `initializeStream()`
     - Uses `InferenceProvider` to create an LLM streaming response.
     - Wraps it in a pipeline-specific aggregation stream.
     - Stores iterator in `StructuredOutputAttemptState`.
   - `processNextChunk()`
     - Pulls next chunk, updates `StructuredOutputAttemptState`.
     - Builds `InferenceResponse`/`PartialInferenceResponse` from the aggregate.
     - Updates `StructuredOutputExecution::withCurrentAttempt(...)`.
4. When stream exhausts, `AttemptIterator::finalizeAttempt()` runs the same final validation/transformation as sync.
5. `StructuredOutputStream` yields:
   - `partials()` → only values
   - `responses()` → `PartialInferenceResponse|InferenceResponse`
   - `sequence()` → sequence-aware updates

## Downstream Components (Shared)

- `InferenceProvider` (`packages/instructor/src/Core/InferenceProvider.php`)
  - Builds `Inference` with `RequestMaterializer::toMessages()`.
- `RequestMaterializer` (`packages/instructor/src/Core/RequestMaterializer.php`)
  - Merges cached context, retries, examples, prompt, and system messages.
  - Applies `chatStructure` ordering and renders templated messages.
- `ResponseGenerator` (`packages/instructor/src/Core/ResponseGenerator.php`)
  - Centralizes deserialization, validation, transformation.
  - Supports two pipelines: JSON-string-first vs array-first extraction.
- `DefaultRetryPolicy` (`packages/instructor/src/RetryPolicy/DefaultRetryPolicy.php`)
  - Records failures and retries until max retries, then throws.

## Minimal Call Graph (Happy Path)

```
StructuredOutput::create
  -> StructuredOutputExecutionBuilder::createWith
  -> PendingStructuredOutput
      -> AttemptIterator
          -> SyncUpdateGenerator | StreamingUpdateGenerator
              -> InferenceProvider
                  -> RequestMaterializer::toMessages
                  -> Inference::create
          -> ResponseGenerator::makeResponse
              -> ResponseExtractor (optional)
              -> ResponseDeserializer
              -> ResponseValidator
              -> ResponseTransformer
```
