# Refactoring Plan: Improving Extraction Cohesion

**Date:** 2026-01-03
**Status:** Proposed

## Problem Statement

The current implementation of streaming extraction strategies is "sloppily" handled, creating an incoherent design compared to how deserializers, validators, or transformers work. Specifically:

- `StructuredOutput` holds `streamingExtractionStrategies` separately from the extractor.
- `ResponseIteratorFactory` must explicitly handle these strategies and manually create buffers (`makeBufferFactory`).
- There is a lack of cohesion between static extraction (`CanExtractResponse`) and streaming extraction (currently buried in `ResponseIterators`).

## Proposed Solution

Consolidate all extraction-related logic into the `Extraction` namespace and unify the contracts.

### 1. Consolidate Interfaces

- Rename `CanExtractResponse` to `ResponseExtractor`.
- Update `ResponseExtractor` to include a method for creating streaming extractors:
  ```php
  interface ResponseExtractor {
      public function extract(InferenceResponse $response, OutputMode $mode): Result;
      public function makeStream(OutputMode $mode): CanExtractStream;
  }
  ```

### 2. Move & Rename Buffer Logic

- Move `ContentBuffer` interface from `ResponseIterators\ModularPipeline\ContentBuffer` to `Extraction\Contracts\CanExtractStream`.
- Move `ExtractingJsonBuffer` and `ToolsBuffer` to `Extraction\Stream` (renaming them to `JsonStreamExtractor` and `ToolsStreamExtractor` for consistency).

### 3. Refactor `JsonResponseExtractor`

- Implement the new `ResponseExtractor` interface.
- Encapsulate `streamingExtractionStrategies` within this class.
- Implement `makeStream(OutputMode $mode)`:
    - If `Tools` mode: return `ToolsStreamExtractor`.
    - If other: return `JsonStreamExtractor` configured with the instance's strategies.

### 4. Refactor `ResponseIteratorFactory`

- Remove `streamingExtractionStrategies` property and constructor argument.
- Remove `makeBufferFactory()` logic.
- In `makeModularStreamingIterator()`, delegate buffer creation:
  ```php
  $cleanFactory = new ModularStreamFactory(
      // ...
      bufferFactory: fn(OutputMode $mode) => $this->extractor->makeStream($mode),
  );
  ```

### 5. Refactor `StructuredOutput`

- Remove `$streamingExtractionStrategies` property.
- Update `withStreamingExtractionStrategies()` to pass strategies directly to the `extractor` instance.
- Ensure `create()` properly initializes the default `JsonResponseExtractor` if strategies are provided but no custom extractor is set.

## Benefits

- **Cohesion:** All extraction logic (sync and stream) is managed by the extractor.
- **Decoupling:** `ResponseIteratorFactory` no longer needs to know about specific extraction strategies or buffer types.
- **Extensibility:** Adding new formats (e.g., XML) only requires a new `ResponseExtractor` implementation without touching the iterator factory.
- **Consistency:** Follows the pattern established for other pipeline components.
