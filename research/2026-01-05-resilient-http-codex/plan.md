# Resilient LLM HTTP/Inference Plan (2026-01-05)

## Scope
- Improve resilience for LLM Inference + Embeddings requests in `packages/polyglot`.
- Add transport-level resiliency in `packages/http-client` (retry, jitter, circuit breaker, rate limits).
- Expand LLM-layer handling for finish reasons (length, content_filter, error, etc.) and provider error bodies.

## Current State (from code review)
- HTTP client has a middleware stack but ships with no retry/circuit-breaker middleware. `packages/http-client/src/HttpClient.php`, `packages/http-client/src/Middleware/MiddlewareStack.php`.
- Drivers map transport failures into typed exceptions; `isRetriable()` exists but is not used. `packages/http-client/src/Exceptions/*.php`.
- `PendingInference` is single-attempt; retry events exist but always use `willRetry=false`. `packages/polyglot/src/Inference/PendingInference.php`.
- `PendingEmbeddings` is also single-attempt. `packages/polyglot/src/Embeddings/PendingEmbeddings.php`.
- Finish reasons are normalized in `InferenceFinishReason` and currently treat `length`, `content_filter`, `error` as failures. `packages/polyglot/src/Inference/Enums/InferenceFinishReason.php`, `packages/polyglot/src/Inference/Data/InferenceResponse.php`.

## Design Goals
- Configurable resilience strategies per request, per model preset, and global defaults.
- Clear separation of transport resilience (HTTP) vs LLM semantics (finish reasons, moderation, max length).
- Deterministic retry decisions with provider-specific error classification.
- Safe defaults (no infinite retry loops, honor rate limits, avoid retrying non-idempotent failures).
- First-class observability (events + metadata).

## Architecture Overview
### 1) HTTP-layer Resilience (Middleware)
Implement middleware in `packages/http-client/src/Middleware/`:
- **RetryMiddleware**
  - Exponential backoff + jitter.
  - Retry on network/timeout, 5xx, and 429 (optional 408/409 depending on provider).
  - Honors `Retry-After` and `x-ratelimit-reset-*` headers when present.
- **CircuitBreakerMiddleware**
  - Per-host (or per-host+model) breaker: closed → open → half-open.
  - Config: failureThreshold, openForSec, halfOpenMaxRequests, successThreshold.
- **RateLimitMiddleware** (optional first pass)
  - Tracks request rate per host/model, pauses/sleeps as needed.
- **IdempotencyMiddleware**
  - Attach `Idempotency-Key` when provider supports it (OpenAI, etc.).

**Config surface**
- Add `http.resilience` in config presets or builder:
  - `maxAttempts`, `baseDelayMs`, `maxDelayMs`, `jitter`, `retryOnStatus`, `retryOnExceptions`.
  - `circuitBreaker`: thresholds, open time, reset strategy.
- Provide `HttpClientBuilder::withResilience(...)` to attach middleware stack defaults.

### 2) LLM-layer Resilience (Inference + Embeddings)
Implement a retry loop in `PendingInference::response()` and `PendingEmbeddings::get()`:
- Add `ResiliencePolicy` (new value object) to `LLMConfig` and `EmbeddingsConfig` (or in `options['resilience']`).
- Compute retry decisions based on:
  - HTTP exception type + status code.
  - Provider error payload (see “error classification”).
  - Finish reason (see below).
- Emit new events: `InferenceRetryScheduled`, `EmbeddingsRetryScheduled` with delay + reason.

### 3) Provider Error Classification
Add a normalizer that converts provider-specific error bodies to a unified error category:
- **Transient**: network, timeouts, 5xx, provider `overloaded`, `server_error`.
- **Rate limit**: 429 or error code `rate_limit_exceeded`.
- **Quota**: `insufficient_quota` (not retriable).
- **Invalid request**: 400, validation errors (not retriable).
- **Auth**: 401/403 (not retriable).

Place this in adapter layer (driver response parsing), returning a typed error or metadata used by retry logic.

## Finish Reason Strategy (LLM-layer)
Current finish reasons and recommended recoverability:
- **stop / stop_sequence / complete**
  - **Recoverable?** No (success).
  - Action: return response.
- **tool_calls / tool_call / tool_use**
  - **Recoverable?** No (success, tool-call path).
  - Action: return response; tool execution will continue.
- **length / max_tokens / model_length**
  - **Recoverable?** Yes, *sometimes*.
  - Strategy: reissue request with higher `maxTokens` (if safe) or ask model to continue.
  - Option: `lengthRecovery = continue | increase_max_tokens | fail`.
- **content_filter / safety / recitation / prohibited_content / blocklist / spii / language**
  - **Recoverable?** Generally **No**.
  - Strategy: fail fast with a typed `ModerationException`, include provider message; optionally allow a policy hook to sanitize or rephrase.
- **error / malformed_function_call**
  - **Recoverable?** Depends on error category.
  - Strategy: if error maps to transient/network/5xx or rate limit, retry with backoff; if invalid request, fail fast.
- **other / finish_reason_unspecified**
  - **Recoverable?** Retry once if HTTP status is 5xx/429 or provider error says transient; otherwise treat as failure.

Update `InferenceResponse::hasFinishedWithFailure()` to respect policy:
- `length` should be treated as “recoverable failure” when policy enables length recovery.
- `content_filter` should always be non-retriable by default.

## Implementation Plan (Phased)
### Phase 1: HTTP Resilience Backbone
1) Implement `RetryPolicy`, `BackoffPolicy`, `JitterStrategy` (simple value objects).
2) Implement `RetryMiddleware` using `HttpRequestException::isRetriable()` + status codes.
3) Implement `CircuitBreakerMiddleware` with in-memory state keyed by host.
4) Add `HttpClientBuilder::withResilience(...)` to install middleware by default.
5) Add docs + examples in `packages/http-client/docs/`.

### Phase 2: Inference Retry Loop
1) Create `InferenceResiliencePolicy` and wire into `LLMConfig` + `InferenceRequest.options`.
2) Add attempt loop to `PendingInference::response()`:
   - increment attempts, dispatch attempt events, compute delay, sleep, retry.
3) Emit `InferenceAttemptFailed` with `willRetry=true` when retry scheduled.
4) Add finish-reason handling for `length` (optional follow-up request).

### Phase 3: Embeddings Retry Loop
1) Mirror the approach in `PendingEmbeddings::get()`.
2) Add `EmbeddingsResiliencePolicy` to config.
3) Emit new events for retry scheduling.

### Phase 4: Error Classification + Moderation
1) Extend response adapters to parse error bodies and categorize.
2) Introduce typed exceptions (`RateLimitException`, `QuotaExceededException`, `ModerationException`).
3) Map moderation-specific failures into “fail-fast with message” behavior.

### Phase 5: Pool + Streaming Considerations (Deferred)
- **Deferred for now:** pool requests currently bypass middleware; revisit optional resilience for pool handlers later.
- Streaming: retries should only happen before streaming starts; mid-stream failure handling should surface a “stream interrupted” error and optionally restart if the provider supports it.

## Configuration Surface (Proposed)
Example `llm.php` / `embed.php` additions:
```php
'options' => [
  'resilience' => [
    'maxAttempts' => 4,
    'baseDelayMs' => 250,
    'maxDelayMs' => 8000,
    'jitter' => 'full',
    'retryOnStatus' => [408, 429, 500, 502, 503, 504],
    'retryOnExceptions' => [TimeoutException::class, NetworkException::class],
    'lengthRecovery' => 'continue',
    'circuitBreaker' => [
      'failureThreshold' => 5,
      'openForSec' => 30,
      'halfOpenMaxRequests' => 2,
      'successThreshold' => 2,
    ],
  ],
],
```

## Testing Plan
- Unit tests for retry decision logic (status codes, exception types, finish reasons).
- Integration tests using MockHttp to simulate 429/5xx/timeout + verify retry count.
- Tests for finish reason `length` recovery (continue/increase max tokens).
- Circuit breaker: open after threshold, half-open recovery, reset on success.

## Open Questions
- Should length recovery reissue with `maxTokens` override or a “continue” prompt?
- Where should per-provider retry defaults live (config vs driver adapters)?
- How to best support pool retries without duplicating middleware logic?
