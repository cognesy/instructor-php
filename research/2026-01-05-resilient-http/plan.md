# Plan: Resilient HTTP Client for LLM Inference

## Context
The current `packages/http-client` implementation provides a robust foundation with drivers (Curl) and a middleware stack. However, it currently lacks built-in resilience mechanisms. As observed in production logs, transient network issues or API provider timeouts (e.g., `Cognesy\Http\Exceptions\TimeoutException`) cause uncaught fatal errors, disrupting the application flow.

## Goal
Make `Inference` and `Embeddings` calls resilient to common LLM API provider issues:
1.  **Timeouts**: The API takes too long to respond.
2.  **Rate Limits**: The API returns 429 Too Many Requests.
3.  **Server Errors**: The API returns 5xx errors.
4.  **Network Glitches**: Transient connectivity issues.

## Proposed Solution: Middleware-Based Resilience

We will leverage the existing `MiddlewareStack` in `HttpClient` to introduce resilience without modifying the core drivers.

### 1. New Resilience Middleware
We will create a new namespace `Cognesy\Http\Middleware\Resilience` and implement the following:

#### A. `RetryMiddleware`
This middleware will be responsible for retrying failed requests based on a configurable strategy.

**Features:**
-   **Conditions**: Retry on specific exceptions (`TimeoutException`, `ConnectionException`) and specific HTTP status codes (429, 5xx).
-   **Max Retries**: Configurable limit (e.g., 3 retries).
-   **Backoff Strategy**:
    -   *Fixed*: Wait X ms between retries.
    -   *Exponential*: Wait base * 2^attempt ms.
-   **Jitter**: Add random variation to backoff time to prevent thundering herd.

#### B. `RateLimitMiddleware` (Optional/Future)
To handle client-side rate limiting (leaky bucket / token bucket) if we need to actively throttle our own requests before sending them. For now, reactive handling via `RetryMiddleware` on 429s is priority.

#### C. `CircuitBreakerMiddleware` (Future)
To stop sending requests to a failing provider for a period of time after a threshold of failures is reached.

### 2. Configuration & Usage

We need to make this easy to consume via `HttpClientBuilder` and `LLMConfig`.

**Example Usage (HttpClient):**
```php
$client = HttpClient::using('openai')
    ->withMiddleware(new RetryMiddleware(
        maxRetries: 3,
        delayMs: 1000,
        strategy: 'exponential', // or callable
        jitter: true
    ));
```

**Example Usage (Inference/Polyglot):**
We should likely add a high-level configuration option or a default "Resilient" preset.

## Implementation Plan

1.  **Create Directory Structure**: `packages/http-client/src/Middleware/Resilience`
2.  **Implement `RetryStrategy`**: A simple value object or interface to define logic for "should retry" and "how long to wait".
3.  **Implement `RetryMiddleware`**:
    -   Wrap `$next->handle($request)` in a try-catch loop.
    -   Catch `HttpRequestException`.
    -   Check status codes on success (for 429/5xx).
    -   Sleep logic.
4.  **Testing**:
    -   Unit tests mocking the inner driver to simulate failures and verify retry counts.
5.  **Integration**:
    -   Verify usage within `Inference` class.

## Detailed Design: `RetryMiddleware`

```php
class RetryMiddleware implements HttpMiddleware
{
    public function __construct(
        private int $maxRetries = 3,
        private int $baseDelay = 1000, // ms
        private bool $exponential = true,
        private bool $jitter = true,
        private array $retryableStatusCodes = [429, 500, 502, 503, 504],
        private array $retryableExceptions = [
            TimeoutException::class,
            ConnectionException::class,
            NetworkException::class
        ]
    ) {}
    
    // ... handle() implementation
}
```
