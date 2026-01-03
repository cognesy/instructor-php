# Better Extraction Plan (2026-01-03)

## Context
Current extraction configuration is split:
- `StructuredOutput::withExtractionStrategies()` configures `JsonResponseExtractor` for final extraction only.
- `StructuredOutput::withStreamingExtractionStrategies()` configures a separate array used only by the modular streaming buffer (`ExtractingJsonBuffer`).
- Streaming configuration does not affect legacy/partials iterators.
- Custom extractors (`CanExtractResponse`) cannot participate in streaming buffer selection.

This makes extraction inconsistent with the response-stage services (deserializer/validator/transformer), which are single services configured in `StructuredOutput::create()` and passed to both sync and streaming pipelines.

## Goals
- Single, cohesive extraction configuration for both sync and streaming.
- Align extraction with the same service pattern as deserializers/validators/transformers.
- Preserve backward compatibility where possible while reducing confusion.
- Keep the solution small and avoid over-engineering (YAGNI).

## Recommended Direction (Option A: Minimal, cohesive)
Unify extraction around a single extractor instance while allowing optional streaming hints.

### Design
- Keep `CanExtractResponse` as the primary contract.
- Add a small optional interface for streaming integration, e.g.:
  - `CanProvideStreamingStrategies` (returns `ExtractionStrategy[]`), or
  - `CanProvideContentBufferFactory` (returns `Closure(OutputMode): ContentBuffer`), or
  - `CanProvideStreamingBuffer` (returns a buffer instance per mode).
- `JsonResponseExtractor` implements this interface and exposes its streaming strategy chain.
- `StructuredOutput` holds only the extractor. `withExtractionStrategies()` builds a `JsonResponseExtractor` with strategies that apply to both final and streaming extraction.
- `withStreamingExtractionStrategies()` becomes an override that updates the extractor (or creates one if none exists), not a separate array.
- `ResponseIteratorFactory` uses the extractor to configure the buffer factory if it supports the streaming interface. Remove `$streamingExtractionStrategies` from the factory.

### Behavior
- If you configure extraction strategies, streaming uses them by default.
- If you override streaming strategies, they apply only to streaming.
- Custom extractors can opt into streaming behavior by implementing the streaming interface.

### Compatibility
- Keep `withStreamingExtractionStrategies()` but re-route it through the extractor. Deprecate the raw array property.
- Legacy and partials pipelines remain unchanged initially.

## Alternative (Option B: Naming Consistency)
Rename/alias `CanExtractResponse` to a `ResponseExtractor` contract and follow the naming pattern of other response-stage services.
- `ResponseExtractor` becomes a formal service class (existing `JsonResponseExtractor`).
- `StructuredOutput::create()` passes a single extractor to both `ResponseGenerator` and `ResponseIteratorFactory`.

## Alternative (Option C: Larger Refactor)
Introduce a dedicated `ResponseExtraction` service similar to `ResponseDeserializer` and add a collection class for strategies.
- Makes extraction fully symmetric with deserialization/validation/transform.
- Apply extraction strategies uniformly across modular, legacy, and partials pipelines.
- Larger change, likely requires more tests and migration.

## Proposed Steps (for Option A)
1. Add streaming integration interface and implement it on `JsonResponseExtractor`.
2. Update `StructuredOutput` to remove `$streamingExtractionStrategies` and route streaming configuration through the extractor.
3. Update `ResponseIteratorFactory` to use extractor-provided buffer factory/strategies.
4. Keep `withStreamingExtractionStrategies()` for compatibility but make it a wrapper around extractor configuration.
5. Add/adjust tests to cover streaming strategy usage and cross-pipeline consistency.

## Open Questions
- Should `withExtractionStrategies()` automatically drive streaming extraction unless explicitly overridden?
- Should `withStreamingExtractionStrategies()` be deprecated or remain a supported override?
- Should legacy/partials iterators be updated to use the same extraction strategy chain?
- Do we want to rename `CanExtractResponse` to `ResponseExtractor` for naming alignment?
