# Cleaner StructuredOutput Pipeline — High-Value Opportunities

**Date:** 2026-01-03
**Scope:** packages/instructor/src

This list focuses on simplification, clarity, and reducing conceptual surface area.

## 1) Consolidate Streaming Pipelines (Modular vs Partials vs Legacy)

**Current**
- Three parallel streaming implementations:
  - `ModularUpdateGenerator` + `ModularStreamFactory`
  - `PartialUpdateGenerator` + `PartialStreamFactory`
  - `StreamingUpdatesGenerator` (legacy)
- `ResponseIteratorFactory` contains a mode switch with string values.
- Each generator duplicates the same control flow: init → process → exhaust.

**Idea**
- Choose a single canonical streaming pipeline (likely Modular) and deprecate the other two.
- Provide a compatibility adapter if needed for old events or output mode differences.
- Replace `StructuredOutputConfig::responseIterator` string with an enum or strategy object.

**Impact**
- Removes large duplicated logic in `packages/instructor/src/ResponseIterators/**` and `ResponseIteratorFactory`.
- Simplifies support and mental model; only one streaming path to learn/debug.

**Files**
- `packages/instructor/src/ResponseIteratorFactory.php`
- `packages/instructor/src/ResponseIterators/*`
- `packages/instructor/src/Config/StructuredOutputConfig.php`

---

## 2) Extract a Shared Stream Attempt Runner

**Current**
- `ModularUpdateGenerator`, `PartialUpdateGenerator`, and `StreamingUpdatesGenerator` duplicate the same state machine:
  - Initialize stream on first call
  - Pull current chunk
  - Check exhaustion
  - Update `StructuredOutputAttemptState`

**Idea**
- Create a reusable `StreamedAttemptRunner` that accepts:
  - `StreamFactory` (returns iterator)
  - `AggregateAdapter` (convert stream element → `InferenceResponse` + `PartialInferenceResponse`)
- Generators become thin wrappers or disappear entirely.

**Impact**
- Collapses 3 classes into 1 with small adapters, reducing code size and maintenance overhead.
- Makes streaming flow easier to reason about and test.

**Files**
- `packages/instructor/src/ResponseIterators/*/*UpdateGenerator.php`
- `packages/instructor/src/Data/StructuredOutputAttemptState.php`

---

## 3) Make Array-First Extraction the Default (Single Response Pipeline)

**Current**
- `ResponseGenerator` has two pipelines:
  - JSON-string-first pipeline
  - Array-first pipeline (only when extractor is provided)
- `StructuredOutput::create()` uses ad-hoc logic to decide when to use `ResponseExtractor`.

**Idea**
- Always run array-first: `ResponseExtractor` should be the default, with standard extractors.
- Remove the JSON-string pipeline, keep one pipeline:
  - Extract → Deserialize → Validate → Transform
- If needed, supply a no-op or fast extractor that only parses direct JSON.

**Impact**
- Removes branching and duplicated pipeline construction logic.
- Centralizes all extraction logic in one place, consistent for sync and streaming.

**Files**
- `packages/instructor/src/Core/ResponseGenerator.php`
- `packages/instructor/src/StructuredOutput.php`
- `packages/instructor/src/Extraction/ResponseExtractor.php`

---

## 4) Resolve OutputFormat Early (Move to ResponseModelFactory/ExecutionBuilder)

**Current**
- `StructuredOutput::create()` mutates `ResponseModel` to apply `OutputFormat`.
- `ResponseDeserializer` and `ResponseModel` both interpret OutputFormat.

**Idea**
- Push OutputFormat resolution into `StructuredOutputExecutionBuilder` or `ResponseModelFactory`.
- Ensure `StructuredOutputExecution` always contains a fully resolved `ResponseModel`.
- Remove output-format logic from `StructuredOutput::create()`.

**Impact**
- Fewer cross-cutting decisions; cleaner and more predictable pipeline.
- Reduces facade complexity and improves testability.

**Files**
- `packages/instructor/src/StructuredOutput.php`
- `packages/instructor/src/Creation/StructuredOutputExecutionBuilder.php`
- `packages/instructor/src/Creation/ResponseModelFactory.php`

---

## 5) Decompose `RequestMaterializer` into a Message Plan Builder

**Current**
- `RequestMaterializer` mixes:
  - cached context merge
  - section ordering
  - retry message injection
  - template rendering
  - “ensure non-empty messages” fallback

**Idea**
- Extract a `MessagePlan`/`MessageScript` builder with explicit steps:
  1. Build base sections
  2. Merge cached sections
  3. Inject retries
  4. Apply structural markers
  5. Render
- Encapsulate `chatStructure` into a value object to avoid ad-hoc arrays.
- Isolate the “ensure non-empty” fallback behind an explicit policy or remove it.

**Impact**
- Makes request composition easier to read and modify.
- Avoids accidental behavior changes when adding new sections.

**Files**
- `packages/instructor/src/Core/RequestMaterializer.php`
- `packages/instructor/src/Config/StructuredOutputConfig.php`

---

## 6) Standardize Error Handling as `Result` (Remove Mixed Exceptions)

**Current**
- `ResponseDeserializer`, `ResponseValidator`, `ResponseTransformer` use `Result` but still throw exceptions in some branches.
- `ResponseGenerator` aggregates errors into strings inconsistently.

**Idea**
- Make all response pipeline steps return `Result` only.
- Convert exceptions into failures at the boundary.
- Centralize error messaging in `ResponseGenerator` (one policy).

**Impact**
- Predictable failure flow; easier retry policy integration.
- Fewer exception branches to understand.

**Files**
- `packages/instructor/src/Deserialization/ResponseDeserializer.php`
- `packages/instructor/src/Validation/ResponseValidator.php`
- `packages/instructor/src/Transformation/ResponseTransformer.php`
- `packages/instructor/src/Core/ResponseGenerator.php`

---

## 7) Extract a `StructuredOutputPipeline` Factory

**Current**
- `StructuredOutput::create()` builds many dependencies inline.
- Hard to see where to add/replace components.

**Idea**
- Introduce a pipeline factory that owns:
  - deserializer/validator/transformer
  - extractor
  - iterator selection
  - retry policy
- `StructuredOutput::create()` becomes composition + call to factory.

**Impact**
- Smaller facade; clearer responsibilities; easier testing/reuse.

**Files**
- `packages/instructor/src/StructuredOutput.php`
- `packages/instructor/src/ResponseIteratorFactory.php`

---

## 8) Centralize Streaming Events (One Event Tap)

**Current**
- Events are emitted across multiple classes (partial generators, ResponseGenerator, RequestMaterializer, etc.).
- Event ordering is implicit and not obvious.

**Idea**
- Use a single `EventTap`/observer for streaming and response generation.
- Emit core events from one place (pipeline transducer), map extra events if needed.

**Impact**
- Easier to reason about event flow and ordering.
- Less duplication and fewer event dispatch points.

**Files**
- `packages/instructor/src/ResponseIterators/*`
- `packages/instructor/src/Core/ResponseGenerator.php`
- `packages/instructor/src/StructuredOutputStream.php`

---

## 9) Remove or Implement Response Caching Consistently

**Current**
- `PendingStructuredOutput` caches responses by default.
- `StructuredOutputStream` disables caching entirely.

**Idea**
- Either remove caching flags entirely or formalize a single caching strategy with clear behavior.

**Impact**
- Fewer “maybe cached” branches; clearer runtime behavior.

**Files**
- `packages/instructor/src/PendingStructuredOutput.php`
- `packages/instructor/src/StructuredOutputStream.php`

---

## 10) Replace String `responseIterator` with Enum

**Current**
- `StructuredOutputConfig::responseIterator` uses string values and is mutable public.

**Idea**
- Introduce `ResponseIteratorType` enum.
- Keep configuration parsing backward compatible (string → enum).

**Impact**
- Type safety and self-documenting configuration.

**Files**
- `packages/instructor/src/Config/StructuredOutputConfig.php`
- `packages/instructor/src/ResponseIteratorFactory.php`
