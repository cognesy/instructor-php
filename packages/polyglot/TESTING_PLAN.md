# Polyglot Testing Plan (Offline, Multi‑Provider)

This plan defines a scalable, provider‑agnostic testing strategy that remains hermetic (no network), validates request/response translation for provider families, and keeps maintenance low as the number of providers grows.

## Principles
- Provider families: test 3 canonical “shapes” thoroughly and let compatible providers inherit coverage.
  - OpenAI family: OpenAI, Azure, OpenRouter, Groq, Perplexity, Together, Moonshot, SambaNova, Ollama, Gemini‑OAI.
  - Anthropic: native API/streaming semantics (no response_format JSON mode).
  - Gemini (native): generateContent/streamGenerateContent, responseSchema/responseMimeType.
- Native in → normalized out:
  - Fixtures for mocked HTTP responses use native provider JSON (and SSE for streaming).
  - Assertions are on Polyglot’s normalized classes (content, tool calls, usage, streaming partials).
- Capability‑based datasets: only run JSON‑mode tests where supported; always test streaming where provider offers SSE.
- Mock, don’t replay: Use fluent `MockHttpDriver` + `MockHttpResponse::sse/json`; no external network.

## Layers
- Unit (provider‑agnostic):
  - Request building: `InferenceRequestBuilder`, `EmbeddingsRequest` (options, streaming flag, cached context).
  - Stream parsing: `EventStreamReader` line splitting and parser hook.
  - Assembly: `InferenceResponse::fromPartialResponses` (accumulator logic for content/tool args/finish reason).
  - Provider builder: `LLMProvider`, `EmbeddingsProvider` preset/DSN/override resolution.
- Adapter Unit (provider‑specific families):
  - Request adapters → `HttpRequest` assertions for OpenAI, Anthropic, Gemini.
  - Response adapters → normalized output assertions (content/toolCalls/usage; SSE deltas).
- Integration (mock HTTP):
  - End‑to‑end via `Inference`/`Embeddings` with real drivers + mock HTTP for each family.
  - Scenarios: basic completion, streaming, tool calls, JSON/schema mode, error paths.

## Directory Structure
```
packages/polyglot/tests/
  Unit/
    Inference/
    Embeddings/
    Provider/
  Integration/
    MockHttp/
  Fixtures/
    openai/
    anthropic/
    gemini/
```

## Provider Datasets (parameterized tests)
Create a helper that describes test cases per family and yields small native JSON payloads (and SSE payload arrays). Each case provides:
- provider key (e.g., `openai`, `anthropic`, `gemini`)
- supports: `{ streaming: bool, tools: bool, jsonMode: bool }`
- endpoint, method, header expectations (matchers)
- `buildRequest($inference)` configuring preset/model/options
- `buildMock($mock)` registering URL/method/body matchers and `replyJson` or `replySSEFromJson`
- expected normalized output (content/tool args/usage)

File: `packages/polyglot/tests/Integration/MockHttp/ProviderDatasets.php`

## Files to Add / Refactor

### Test Support / Datasets
- `Integration/MockHttp/ProviderDatasets.php`
  - Exports datasets for: `openaiFamily()`, `anthropicFamily()`, `geminiFamily()`
  - Returns associative arrays of cases for Pest `with()` datasets.

### Unit (provider‑agnostic)
- `Unit/Inference/InferenceRequestBuilderTest.php`
  - Asserts `with(...)`, `withStreaming`, `withMaxTokens`, `withCachedContext` → `InferenceRequest::toArray()`
- `Unit/Inference/EventStreamReaderTest.php` (already exists as Feature; leave or move)
  - Verifies line splitting and parser mapping.
- `Unit/Inference/InferenceResponseAssemblyTest.php`
  - Verifies `InferenceResponse::fromPartialResponses` accumulation of content/tool args/finish reason.
- `Unit/Provider/LLMProviderConfigTest.php`
  - DSN/preset/override resolution and events for `LLMProvider`.
- `Unit/Embeddings/EmbeddingsRequestTest.php`
  - Input normalization, model/options mapping; exception on empty input.

### Adapter Unit (families)
- `Unit/Drivers/OpenAI/OpenAIRequestAdapterTest.php`
- `Unit/Drivers/OpenAI/OpenAIResponseAdapterTest.php`
- `Unit/Drivers/Anthropic/AnthropicRequestAdapterTest.php`
- `Unit/Drivers/Anthropic/AnthropicResponseAdapterTest.php`
- `Unit/Drivers/Gemini/GeminiRequestAdapterTest.php`
- `Unit/Drivers/Gemini/GeminiResponseAdapterTest.php`

Each test feeds minimal native JSON and asserts normalized output fields (content, tool name/args, usage) and for streaming, partial deltas.

### Integration (mock HTTP) — parameterized by datasets
- `Integration/MockHttp/InferenceBasicCompletionTest.php`
  - Runs with datasets from all families where supported (non‑streaming content).
- `Integration/MockHttp/InferenceStreamingTest.php`
  - Streaming SSE; partials assemble to final content; provider‑specific `[DONE]` or message_stop semantics.
- `Integration/MockHttp/InferenceToolCallsTest.php`
  - Tool call in non‑streaming response.
- `Integration/MockHttp/InferenceToolStreamingTest.php`
  - Tool args streamed across deltas.
- `Integration/MockHttp/InferenceJsonModeTest.php`
  - JSON mode/spec for providers that support it (OpenAI, Gemini native; skip Anthropic).
- `Integration/MockHttp/InferenceErrorPathsTest.php`
  - 400/401/403/429 (pick 400 + 429 as representative) → exception + event dispatch.
- `Integration/MockHttp/EmbeddingsOpenAIFlowTest.php`
  - Non‑streaming embeddings vectors + usage; error path (429) → exception.

Note: We already added some OpenAI tests; the new parameterized files will consolidate them. Existing files to refactor or fold into parameterized suites:
- `Integration/MockHttp/InferenceOpenAIResponseTest.php`
- `Integration/MockHttp/InferenceOpenAIStreamingTest.php`
- `Integration/MockHttp/InferenceOpenAIToolCallsTest.php`
- `Integration/MockHttp/InferenceOpenAIJsonModeTest.php`
- `Integration/MockHttp/InferenceOpenAIToolStreamingTest.php`
- `Integration/MockHttp/EmbeddingsOpenAITest.php`
- `Integration/MockHttp/InferenceErrorPathTest.php`

We will:
- Keep these initially, then refactor into dataset‑based tests once `ProviderDatasets.php` is in place.

### Fixtures (native provider JSON)
- `Fixtures/openai/*.json` (chat completions, tool‑call, streaming payloads as JSON array for SSE)
- `Fixtures/anthropic/*.json` (messages, tool_use, message_stop semantics)
- `Fixtures/gemini/*.json` (generateContent/streamGenerateContent with responseSchema)

Start with ultra‑small, representative JSON; keep a single “happy path” and one “error” per scenario.

## Execution
- Tests must run from monorepo root (Composer autoload mapping).
- Use `./vendor/bin/pest --testsuite=Unit,Feature,Integration` or full.
- All tests hermetic; no network; use `MockHttpDriver` + `MockHttpResponse::json|sse|streaming`.

## Milestones
1) Add `ProviderDatasets.php` with OpenAI family; convert existing OpenAI mock tests to dataset form.
2) Add Anthropic datasets + fixtures; implement streaming tool‑use test.
3) Add Gemini native datasets + fixtures; add JSON schema mode tests.
4) Add unit tests for adapters (request/response) for the three families.
5) Add provider‑agnostic unit tests for builders/assembly and embeddings request.
6) Clean up/merge any duplicated scenario tests into parametric suites.

## Out of Scope (for now)
- Record–replay middleware rewrite — can be a second phase to help (re)generate fixtures; not a dependency for this plan.
- Browser‑level or e2e network tests.

---

This plan lets us validate the full surface area with a small, maintainable set of tests and fixtures. Adding new providers typically requires only a dataset entry pointing at an existing family, plus (rarely) a new family if the shape is unique.
