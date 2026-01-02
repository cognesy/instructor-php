# Prism - Lessons for Polyglot

## Executive Summary

Prism excels at **developer experience and production-grade error handling**. Its Builder → Immutable Request pattern provides a fluent API without sacrificing type safety. The ResponseBuilder tracks multi-turn tool loops as discrete Steps, enabling accurate usage aggregation across complex agent interactions. Most impressive is Prism's exception hierarchy with rich metadata - rate limit exceptions include remaining quotas, reset times, and retry-after values. For Polyglot, Prism demonstrates that Laravel-style ergonomics can coexist with serious production requirements.

**Key Takeaway**: The ResponseBuilder's Step-based tracking is essential for any library supporting tool calls. Without it, usage metrics are incomplete and debugging multi-turn interactions is painful.

---

## Architectural Patterns Worth Adopting

### 1. Builder → Immutable Request Pattern

**Problem it solves**: Mutable configuration objects lead to bugs when shared; immutable-from-start objects have poor ergonomics.

**How it works**:
```php
// Phase 1: Mutable builder with fluent API
$pending = Prism::text()
    ->using(Provider::Anthropic, 'claude-3-opus-20240229')
    ->withSystemPrompt('You are a helpful assistant.')
    ->withMaxTokens(4096)
    ->usingTemperature(0.8)
    ->withTools([new SearchTool(), new CalculatorTool()])
    ->withClientRetry(3, 100);

// Phase 2: Freeze into immutable request (validation happens here)
$request = $pending->toRequest();  // Returns readonly TextRequest

// Phase 3: Execute with immutable request
$response = $this->handler->handle($request);

// TextRequest is readonly - no accidental mutation during execution
readonly class TextRequest {
    public function __construct(
        public Provider $provider,
        public string $model,
        public Messages $messages,
        public int $maxTokens,
        public float $temperature,
        public array $tools,
        public array $clientRetry,
    ) {}
}
```

**Why it's powerful**:
- Builder: Great DX with method chaining
- Readonly request: No mutation bugs during execution
- Validation at boundary: Catch errors early, not mid-execution
- Clear phase separation: Configuration vs Execution

**Polyglot adaptation**:
```php
// PendingRequest for fluent building
class PendingRequest {
    private string $provider;
    private string $model;
    private array $messages = [];
    private ?int $maxTokens = null;
    private float $temperature = 0.7;
    private array $tools = [];
    private array $retryConfig = [];

    public function using(string $provider, string $model): self {
        $this->provider = $provider;
        $this->model = $model;
        return $this;
    }

    public function withMaxTokens(int $tokens): self {
        $this->maxTokens = $tokens;
        return $this;
    }

    public function withRetry(int $times, int $sleepMs, ?callable $when = null): self {
        $this->retryConfig = [$times, $sleepMs, $when];
        return $this;
    }

    public function toRequest(): LLMRequest {
        $this->validate();  // Throws if invalid

        return new LLMRequest(
            provider: $this->provider,
            model: $this->model,
            messages: Messages::fromArray($this->messages),
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            tools: $this->tools,
            retryConfig: $this->retryConfig,
        );
    }

    private function validate(): void {
        if (!isset($this->provider, $this->model)) {
            throw new InvalidRequestException('Provider and model are required');
        }
    }
}

// Readonly request object
readonly class LLMRequest {
    public function __construct(
        public string $provider,
        public string $model,
        public Messages $messages,
        public ?int $maxTokens,
        public float $temperature,
        public array $tools,
        public array $retryConfig,
    ) {}
}
```

**Priority**: MEDIUM - Improves DX and type safety

---

### 2. ResponseBuilder with Multi-Step Tracking

**Problem it solves**: Tool call loops make multiple API calls, but usage is tracked per-call, losing the aggregate picture.

**How it works**:
```php
// Each API call in a tool loop = one Step
class ResponseBuilder {
    /** @var Collection<Step> */
    public Collection $steps;

    public function addStep(Step $step): self {
        $this->steps->push($step);
        return $this;
    }

    public function toResponse(): Response {
        return new Response(
            text: $this->finalText(),           // From last step
            toolCalls: $this->finalToolCalls(), // From last step
            usage: $this->calculateTotalUsage(), // Aggregated!
            steps: $this->steps,                // Full history
            finishReason: $this->finalFinishReason(),
        );
    }

    // Smart aggregation across all steps
    protected function calculateTotalUsage(): Usage {
        return new Usage(
            promptTokens: $this->steps->sum(
                fn(Step $s) => $s->usage->promptTokens
            ),
            completionTokens: $this->steps->sum(
                fn(Step $s) => $s->usage->completionTokens
            ),
            // Handle nullable fields properly
            cacheWriteTokens: $this->steps->contains(
                fn(Step $s) => $s->usage->cacheWriteTokens !== null
            )
                ? $this->steps->sum(fn(Step $s) => $s->usage->cacheWriteTokens ?? 0)
                : null,
            cacheReadTokens: $this->steps->contains(
                fn(Step $s) => $s->usage->cacheReadTokens !== null
            )
                ? $this->steps->sum(fn(Step $s) => $s->usage->cacheReadTokens ?? 0)
                : null,
        );
    }
}

// Step captures one API call
readonly class Step {
    public function __construct(
        public string $text,
        public array $toolCalls,
        public array $toolResults,
        public Usage $usage,
        public string $finishReason,
    ) {}
}

// Usage preserves nullability semantics
readonly class Usage {
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public ?int $cacheWriteTokens = null,  // null = not supported
        public ?int $cacheReadTokens = null,
    ) {}
}
```

**Why it's powerful**:
- Complete usage tracking across tool loops
- Debug multi-turn interactions via steps
- Nullable fields handled correctly (null vs 0)
- Final response has aggregated totals

**Polyglot adaptation**:
```php
// Step value object
readonly class InferenceStep {
    public function __construct(
        public string $text,
        public array $toolCalls,
        public array $toolResults,
        public TokenUsage $usage,
        public string $finishReason,
        public float $latencyMs,
    ) {}
}

// ResponseBuilder accumulates steps
class ResponseBuilder {
    /** @var InferenceStep[] */
    private array $steps = [];

    public function addStep(InferenceStep $step): self {
        $this->steps[] = $step;
        return $this;
    }

    public function build(): LLMResponse {
        $lastStep = end($this->steps);

        return new LLMResponse(
            content: $lastStep->text,
            toolCalls: $lastStep->toolCalls,
            usage: $this->aggregateUsage(),
            steps: $this->steps,
            finishReason: $lastStep->finishReason,
            totalLatencyMs: array_sum(array_column($this->steps, 'latencyMs')),
        );
    }

    private function aggregateUsage(): TokenUsage {
        return new TokenUsage(
            promptTokens: array_sum(array_column(
                array_column($this->steps, 'usage'),
                'promptTokens'
            )),
            completionTokens: array_sum(array_column(
                array_column($this->steps, 'usage'),
                'completionTokens'
            )),
            cacheWriteTokens: $this->aggregateNullableField('cacheWriteTokens'),
            cacheReadTokens: $this->aggregateNullableField('cacheReadTokens'),
        );
    }

    private function aggregateNullableField(string $field): ?int {
        $hasField = false;
        $total = 0;

        foreach ($this->steps as $step) {
            if ($step->usage->$field !== null) {
                $hasField = true;
                $total += $step->usage->$field;
            }
        }

        return $hasField ? $total : null;
    }
}
```

**Priority**: HIGH - Essential for accurate usage tracking

---

### 3. StreamState with Selective Reset

**Problem it solves**: Streaming tool loops need state reset between turns, but some state (like "stream started") must persist.

**How it works**:
```php
class StreamState {
    // Content state - reset between tool turns
    protected string $currentText = '';
    protected array $toolCalls = [];

    // Lifecycle flags - persist across turns
    protected bool $streamStarted = false;
    protected bool $textStarted = false;

    public function appendText(string $text): self {
        $this->currentText .= $text;
        return $this;
    }

    public function startStream(): self {
        if (!$this->streamStarted) {
            $this->streamStarted = true;
            // Emit StreamStartEvent only once
        }
        return $this;
    }

    // Called between tool loop iterations
    public function reset(): self {
        // Reset content
        $this->currentText = '';
        $this->toolCalls = [];

        // Reset per-turn flags
        $this->textStarted = false;

        // BUT keep streamStarted = true
        // (avoid duplicate StreamStartEvent)

        return $this;
    }

    public function finalize(): StreamedResponse {
        return new StreamedResponse(
            text: $this->currentText,
            toolCalls: $this->toolCalls,
        );
    }
}

// Usage in streaming handler
foreach ($this->streamToolLoop($request) as $chunk) {
    $state->appendText($chunk);
    yield $chunk;
}

// After tool execution, before next iteration
$state->reset();  // Clear content, keep lifecycle state
```

**Why it's powerful**:
- Prevents duplicate lifecycle events
- Content state is cleanly reset between turns
- Single state object for entire stream
- Clear separation: content vs lifecycle

**Polyglot adaptation**:
```php
class StreamingState {
    // Content accumulation (reset between tool turns)
    private string $text = '';
    private array $toolCalls = [];
    private ?TokenUsage $usage = null;

    // Lifecycle tracking (persist across turns)
    private bool $streamEmitted = false;
    private bool $textEmitted = false;
    private int $turnNumber = 0;

    public function appendText(string $text): self {
        $this->text .= $text;
        return $this;
    }

    public function emitStreamStart(): ?StreamStartEvent {
        if ($this->streamEmitted) {
            return null;  // Already emitted
        }
        $this->streamEmitted = true;
        return new StreamStartEvent();
    }

    public function emitTextStart(): ?TextStartEvent {
        if ($this->textEmitted) {
            return null;  // Already emitted this turn
        }
        $this->textEmitted = true;
        return new TextStartEvent();
    }

    public function resetForNextTurn(): self {
        $this->text = '';
        $this->toolCalls = [];
        $this->textEmitted = false;  // Reset per-turn flag
        $this->turnNumber++;
        // $this->streamEmitted stays true
        return $this;
    }

    public function currentTurn(): int {
        return $this->turnNumber;
    }

    public function accumulatedText(): string {
        return $this->text;
    }
}
```

**Priority**: MEDIUM - Important for streaming tool loops

---

### 4. Event-Driven Streaming Architecture

**Problem it solves**: Streaming consumers need different information - some want raw chunks, others want lifecycle events, others want structured data.

**How it works**:
```
StreamStartEvent
  ↓
TextStartEvent
  ↓
TextDeltaEvent("Hello") → TextDeltaEvent(" world") → TextDeltaEvent("!")
  ↓
TextCompleteEvent(fullText: "Hello world!")
  ↓
ToolCallStartEvent(name: "search")
  ↓
ToolCallDeltaEvent(argumentChunk: '{"qu')
  ↓
ToolCallCompleteEvent(name: "search", arguments: {...})
  ↓
ToolResultEvent(toolCallId: "...", result: {...})
  ↓
StreamEndEvent(usage: {...}, finishReason: "tool_calls")
```

**Each event is a typed object**:
```php
readonly class TextDeltaEvent {
    public function __construct(
        public string $delta,
    ) {}
}

readonly class TextCompleteEvent {
    public function __construct(
        public string $fullText,
    ) {}
}

readonly class ToolCallCompleteEvent {
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}
}

readonly class StreamEndEvent {
    public function __construct(
        public Usage $usage,
        public string $finishReason,
    ) {}
}
```

**Why it's powerful**:
- Each event type has specific data
- Can filter by event type
- Dual consumption: stream to client + aggregate server-side
- Type-safe event handling

**Polyglot adaptation**:
```php
// Base event interface
interface StreamEvent {}

// Lifecycle events
readonly class StreamStartEvent implements StreamEvent {
    public function __construct(
        public string $model,
        public float $timestamp,
    ) {}
}

readonly class StreamEndEvent implements StreamEvent {
    public function __construct(
        public TokenUsage $usage,
        public string $finishReason,
        public float $totalDurationMs,
    ) {}
}

// Content events
readonly class TextDeltaEvent implements StreamEvent {
    public function __construct(
        public string $delta,
    ) {}
}

readonly class ToolCallEvent implements StreamEvent {
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}
}

// Streaming handler yields events
public function stream(LLMRequest $request): Generator {
    yield new StreamStartEvent($request->model, microtime(true));

    foreach ($this->fetchStream($request) as $chunk) {
        yield $this->parseChunk($chunk);  // TextDeltaEvent or ToolCallEvent
    }

    yield new StreamEndEvent($usage, $finishReason, $duration);
}

// Consumer can filter
foreach ($handler->stream($request) as $event) {
    match (true) {
        $event instanceof TextDeltaEvent => $this->sendToClient($event->delta),
        $event instanceof StreamEndEvent => $this->recordUsage($event->usage),
        default => null,
    };
}
```

**Priority**: MEDIUM - Improves streaming flexibility

---

### 5. Exception Hierarchy with Rich Metadata

**Problem it solves**: Generic exceptions lose critical information like retry-after headers, remaining quotas, and error codes.

**How it works**:
```php
// Base exception
class PrismException extends Exception {}

// Rate limit exception with FULL metadata
class PrismRateLimitedException extends PrismException {
    public function __construct(
        /** @var ProviderRateLimit[] */
        public readonly array $rateLimits,
        public readonly ?int $retryAfter = null,
        string $message = 'Rate limit exceeded',
    ) {
        parent::__construct($message);
    }
}

// Value object for each limit type
readonly class ProviderRateLimit {
    public function __construct(
        public string $name,           // "requests", "tokens", etc.
        public ?int $limit = null,     // Total allowed
        public ?int $remaining = null, // Remaining
        public ?Carbon $resetsAt = null, // When it resets
    ) {}
}

// Server error with response details
class PrismServerException extends PrismException {
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        string $message = 'Server error',
    ) {
        parent::__construct($message);
    }
}

// Extraction in handler
protected function handleRateLimit(ResponseInterface $response): never {
    $headers = $response->getHeaders();

    $rateLimits = [
        new ProviderRateLimit(
            name: 'requests',
            limit: (int) ($headers['x-ratelimit-limit-requests'][0] ?? null),
            remaining: (int) ($headers['x-ratelimit-remaining-requests'][0] ?? null),
            resetsAt: isset($headers['x-ratelimit-reset-requests'][0])
                ? Carbon::parse($headers['x-ratelimit-reset-requests'][0])
                : null,
        ),
        new ProviderRateLimit(
            name: 'tokens',
            limit: (int) ($headers['x-ratelimit-limit-tokens'][0] ?? null),
            remaining: (int) ($headers['x-ratelimit-remaining-tokens'][0] ?? null),
            resetsAt: isset($headers['x-ratelimit-reset-tokens'][0])
                ? Carbon::parse($headers['x-ratelimit-reset-tokens'][0])
                : null,
        ),
    ];

    $retryAfter = isset($headers['retry-after'][0])
        ? (int) $headers['retry-after'][0]
        : null;

    throw new PrismRateLimitedException($rateLimits, $retryAfter);
}
```

**Why it's powerful**:
- Caller has ALL information to implement smart retries
- Different limits (requests vs tokens) are separated
- Reset times enable precise backoff
- Error codes enable provider-specific handling

**Polyglot adaptation**:
```php
// Base hierarchy
class LLMException extends Exception {}
class ClientException extends LLMException {}
class ServerException extends LLMException {}

// Rate limit with rich metadata
class RateLimitException extends ClientException {
    public function __construct(
        string $message,
        public readonly array $limits,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function getSmartRetryDelay(): int {
        if ($this->retryAfterSeconds !== null) {
            return $this->retryAfterSeconds * 1000;  // Convert to ms
        }

        // Calculate from reset times
        $earliestReset = min(array_filter(
            array_column($this->limits, 'resetsAt'),
            fn($t) => $t !== null
        ));

        return $earliestReset
            ? max(0, $earliestReset->diffInMilliseconds(now()))
            : 60000;  // Default 60s
    }
}

// Rate limit value object
readonly class RateLimitInfo {
    public function __construct(
        public string $type,
        public ?int $limit = null,
        public ?int $remaining = null,
        public ?DateTimeInterface $resetsAt = null,
    ) {}

    public function isExhausted(): bool {
        return $this->remaining !== null && $this->remaining <= 0;
    }

    public function percentRemaining(): ?float {
        if ($this->limit === null || $this->remaining === null) {
            return null;
        }
        return ($this->remaining / $this->limit) * 100;
    }
}

// Auth errors
class AuthenticationException extends ClientException {
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}

// Server errors with details
class ProviderServerException extends ServerException {
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
    ) {
        parent::__construct($message);
    }

    public function isRetryable(): bool {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }
}
```

**Priority**: HIGH - Critical for production reliability

---

### 6. Request-Scoped Retry Configuration

**Problem it solves**: Global retry settings don't account for request-specific needs (batch vs interactive, critical vs optional).

**How it works**:
```php
// Config captured in PendingRequest
class PendingRequest {
    protected ?array $clientRetry = null;

    public function withClientRetry(
        int $times,
        int $sleepMs,
        ?callable $when = null,
        bool $throw = true
    ): self {
        $this->clientRetry = [$times, $sleepMs, $when, $throw];
        return $this;
    }
}

// Flows through immutable Request
readonly class TextRequest {
    public function __construct(
        // ...
        public ?array $clientRetry = null,
    ) {}

    public function clientRetry(): array {
        return $this->clientRetry ?? [0, 0, null, true];  // Defaults
    }
}

// Applied in handler
class TextHandler {
    public function handle(TextRequest $request): Response {
        [$times, $sleep, $when, $throw] = $request->clientRetry();

        return Http::retry($times, $sleep, $when, $throw)
            ->post($this->endpoint, $this->buildPayload($request));
    }
}

// Usage: different retries for different scenarios
$criticalRequest = Prism::text()
    ->withClientRetry(5, 1000)  // 5 retries, 1s between
    ->toRequest();

$batchRequest = Prism::text()
    ->withClientRetry(1, 100)   // Quick fail for batch
    ->toRequest();
```

**Why it's powerful**:
- Per-request customization
- Flows through immutable request
- No global state mutation
- Easy to differentiate critical vs optional calls

**Polyglot adaptation**:
```php
// In request builder
class PendingRequest {
    private ?RetryConfig $retryConfig = null;

    public function withRetry(
        int $maxAttempts,
        int $baseDelayMs = 1000,
        float $multiplier = 2.0,
        int $maxDelayMs = 30000,
    ): self {
        $this->retryConfig = new RetryConfig(
            $maxAttempts,
            $baseDelayMs,
            $multiplier,
            $maxDelayMs,
        );
        return $this;
    }
}

// Retry config value object
readonly class RetryConfig {
    public function __construct(
        public int $maxAttempts,
        public int $baseDelayMs,
        public float $multiplier,
        public int $maxDelayMs,
    ) {}

    public function delayForAttempt(int $attempt): int {
        $delay = $this->baseDelayMs * pow($this->multiplier, $attempt - 1);
        return min((int) $delay, $this->maxDelayMs);
    }
}

// Used in HTTP client wrapper
class RetryableHttpClient {
    public function request(string $method, string $url, array $options, ?RetryConfig $retry): Response {
        $attempt = 0;
        $retry ??= new RetryConfig(0, 0, 0, 0);  // No retry by default

        while (true) {
            try {
                return $this->client->request($method, $url, $options);
            } catch (TransientException $e) {
                $attempt++;
                if ($attempt > $retry->maxAttempts) {
                    throw $e;
                }
                usleep($retry->delayForAttempt($attempt) * 1000);
            }
        }
    }
}
```

**Priority**: MEDIUM - Improves production flexibility

---

### 7. Tool Loop with Explicit Depth Tracking

**Problem it solves**: Recursive tool loops can run forever without explicit termination conditions.

**How it works**:
```php
class TextHandler {
    protected ResponseBuilder $responseBuilder;

    public function handle(TextRequest $request): Response {
        $this->responseBuilder = new ResponseBuilder();

        return $this->executeLoop($request);
    }

    protected function executeLoop(TextRequest $request): Response {
        // Make API call
        $rawResponse = $this->makeApiCall($request);

        // Add as step
        $this->responseBuilder->addStep(
            $this->buildStep($rawResponse)
        );

        // Check for tool calls
        if ($this->hasToolCalls($rawResponse)) {
            return $this->handleToolCalls($request, $rawResponse);
        }

        return $this->responseBuilder->toResponse();
    }

    protected function handleToolCalls(TextRequest $request, $rawResponse): Response {
        // Execute tools
        $results = $this->executeTools($rawResponse->toolCalls);

        // Append to messages
        $request = $request->withAdditionalMessages($results);

        // Check depth limit
        if ($this->responseBuilder->steps->count() >= $request->maxSteps()) {
            // Return partial response - don't recurse forever
            return $this->responseBuilder->toResponse();
        }

        // Recurse
        return $this->executeLoop($request);
    }
}
```

**Why it's powerful**:
- Step count = explicit depth tracking
- maxSteps enforces hard limit
- Partial response on limit (not exception)
- ResponseBuilder aggregates all steps

**Polyglot adaptation**:
```php
class ToolLoopHandler {
    private ResponseBuilder $builder;
    private int $currentStep = 0;

    public function handle(LLMRequest $request): LLMResponse {
        $this->builder = new ResponseBuilder();
        $this->currentStep = 0;

        return $this->loop($request);
    }

    private function loop(LLMRequest $request): LLMResponse {
        $this->currentStep++;

        // Check depth BEFORE making call
        if ($this->currentStep > $request->maxSteps) {
            return $this->builder
                ->markLimitReached()
                ->build();
        }

        $response = $this->makeApiCall($request);
        $this->builder->addStep($this->toStep($response));

        if ($response->hasToolCalls() && !$response->isComplete()) {
            $results = $this->executeTools($response->toolCalls);
            $request = $request->appendToolResults($results);
            return $this->loop($request);
        }

        return $this->builder->build();
    }
}
```

**Priority**: MEDIUM - Prevents runaway tool loops

---

## Specific Code Patterns

### Pattern: Fluent Builder with Validation

```php
// Validation at toRequest() boundary
public function toRequest(): TextRequest {
    if ($this->provider === null) {
        throw new InvalidArgumentException('Provider is required. Call using().');
    }

    if (empty($this->messages)) {
        throw new InvalidArgumentException('At least one message is required.');
    }

    return new TextRequest(/* ... */);
}
```

### Pattern: Smart Nullable Aggregation

```php
// Don't return 0 when field doesn't exist - return null
$cacheTokens = $steps->contains(fn($s) => $s->usage->cacheTokens !== null)
    ? $steps->sum(fn($s) => $s->usage->cacheTokens ?? 0)
    : null;  // null = not supported by this provider
```

### Pattern: Exception Factory

```php
// Centralize exception creation
class ExceptionFactory {
    public static function fromResponse(ResponseInterface $response): LLMException {
        return match ($response->getStatusCode()) {
            401, 403 => new AuthenticationException(
                self::extractMessage($response),
                self::extractErrorCode($response),
            ),
            429 => new RateLimitException(
                self::extractMessage($response),
                self::extractRateLimits($response),
                self::extractRetryAfter($response),
            ),
            default => $response->getStatusCode() >= 500
                ? new ProviderServerException(...)
                : new ProviderClientException(...),
        };
    }
}
```

---

## DX Improvements

1. **Fluent Builder**: Chain configuration naturally
2. **IDE Autocomplete**: Typed methods suggest options
3. **Clear Errors**: Validation messages explain what's missing
4. **Step Visibility**: Debug multi-turn loops via steps array
5. **Smart Defaults**: Sensible defaults reduce boilerplate

---

## What NOT to Copy

### 1. Laravel Coupling
Prism uses Laravel Collections, Carbon, Http facade. For Polyglot (framework-agnostic), use plain arrays/DateTimeImmutable.

### 2. No Self-Correcting Output
Prism retries without feeding errors back to LLM. NeuronAI's approach is superior.

### 3. No Async Support
Prism is synchronous only. NeuronAI's async-first is better for high throughput.

### 4. Complex Event Names
Prism's event class names are verbose. Consider shorter enums like NeuronAI.

---

## Implementation Roadmap for Polyglot

### Phase 1: Rich Exceptions (1-2 days)
1. Create exception hierarchy
2. Add rate limit value objects
3. Extract metadata from responses
4. Update all error paths

### Phase 2: ResponseBuilder (2-3 days)
1. Create Step value object
2. Implement ResponseBuilder
3. Add usage aggregation
4. Track steps in tool handler

### Phase 3: Builder Pattern (2-3 days)
1. Create PendingRequest builder
2. Add fluent configuration methods
3. Implement toRequest() with validation
4. Create readonly LLMRequest

### Phase 4: StreamState (1-2 days)
1. Create StreamingState class
2. Implement selective reset
3. Add lifecycle event tracking
4. Integrate with streaming handler

---

## Key Metrics to Track

After implementing Prism patterns, measure:

| Metric | Before | Target |
|--------|--------|--------|
| Usage accuracy (multi-turn) | ~60% | 100% |
| Retry success rate | ~70% | >90% |
| Time to debug tool loops | 30min | 5min |
| Exception information completeness | 30% | 100% |
| API surface discoverability | Low | High |

---

## Summary: Top 3 Takeaways

1. **ResponseBuilder with Steps is essential** - Without step tracking, multi-turn tool loops lose usage data and are hard to debug.

2. **Rich exception metadata enables smart retries** - Rate limit exceptions should include remaining quotas, reset times, and retry-after values.

3. **Builder → Immutable Request improves DX and safety** - Fluent building with readonly execution objects prevents mutation bugs while maintaining ergonomics.
