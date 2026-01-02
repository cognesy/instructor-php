# InstructorPHP Polyglot - Improvement Recommendations

Based on deep architectural analysis of NeuronAI, Prism, Symfony AI, and the corrected understanding of Polyglot's sophisticated architecture.

---

## Executive Summary

Polyglot already has **excellent foundations**:
- Hierarchical state machine (InferenceExecution → InferenceAttempt)
- Rich domain model (Messages → Content → ContentPart)
- Functional stream operations (map/reduce/filter)
- Production-grade usage tracking with overflow protection
- Capability modeling foundation in BodyFormat classes
- **HTTP Request Pool** - Unique parallel inference with typed collections and Result monad (not found in other libraries)
- **MessageStore** - Sectioned context management with dynamic inclusion/exclusion
- **Templates Package** - Multi-engine prompt templating (Twig/Blade/ArrowPipe) with XML-based message structure

The recommendations below focus on **missing pieces** that other libraries handle better.

---

## Priority 1: Error Handling Enhancements

### Current State
Polyglot uses generic `RuntimeException` for all HTTP errors:
```php
// BaseInferenceDriver::makeHttpResponse()
if ($httpResponse->statusCode() >= 400) {
    throw new RuntimeException('HTTP request failed with status code ' . $httpResponse->statusCode());
}
```

### Recommendation: Typed Exception Hierarchy

**Inspired by**: Prism's specific exception types

```php
// New exception hierarchy
namespace Cognesy\Polyglot\Exceptions;

class InferenceException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly ?InferenceRequest $request = null,
        public readonly ?HttpResponse $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

class RateLimitException extends InferenceException {
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?DateTimeImmutable $resetAt = null,
        ?InferenceRequest $request = null,
        ?HttpResponse $response = null,
    ) {
        parent::__construct($message, $request, $response);
    }

    public static function fromResponse(HttpResponse $response, ?InferenceRequest $request = null): self {
        $retryAfter = (int) ($response->header('Retry-After') ?? $response->header('x-ratelimit-reset-requests'));
        $resetAt = $retryAfter > 0 ? new DateTimeImmutable("+{$retryAfter} seconds") : null;

        return new self(
            'Rate limit exceeded',
            retryAfterSeconds: $retryAfter ?: null,
            resetAt: $resetAt,
            request: $request,
            response: $response,
        );
    }
}

class AuthenticationException extends InferenceException {}
class ServerErrorException extends InferenceException {}
class InvalidResponseException extends InferenceException {}
class ContentFilterException extends InferenceException {}
```

**Usage in BaseInferenceDriver:**
```php
protected function makeHttpResponse(HttpRequest $request): HttpResponse {
    $httpResponse = $this->httpClient->withRequest($request)->get();

    return match(true) {
        $httpResponse->statusCode() === 429 => throw RateLimitException::fromResponse($httpResponse, $this->currentRequest),
        $httpResponse->statusCode() === 401 => throw new AuthenticationException('Invalid API key', $this->currentRequest, $httpResponse),
        $httpResponse->statusCode() === 403 => throw new AuthenticationException('Access denied', $this->currentRequest, $httpResponse),
        $httpResponse->statusCode() >= 500 => throw new ServerErrorException('Server error: ' . $httpResponse->statusCode(), $this->currentRequest, $httpResponse),
        $httpResponse->statusCode() >= 400 => throw new InferenceException('Request failed: ' . $httpResponse->statusCode(), $this->currentRequest, $httpResponse),
        default => $httpResponse,
    };
}
```

**Benefit**: Callers can catch specific exceptions and handle appropriately (e.g., wait on rate limit, refresh API key on auth error).

---

## Priority 2: Built-in Retry with Exponential Backoff

### Current State
No built-in retry - caller must implement externally.

### Recommendation: Optional Retry Middleware

**Inspired by**: NeuronAI's retry middleware

```php
namespace Cognesy\Polyglot\Middleware;

class RetryMiddleware {
    public function __construct(
        private int $maxRetries = 3,
        private int $baseDelayMs = 1000,
        private float $multiplier = 2.0,
        private array $retryableExceptions = [
            RateLimitException::class,
            ServerErrorException::class,
        ],
    ) {}

    public function wrap(callable $operation): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $operation();
            } catch (Throwable $e) {
                if (!$this->isRetryable($e) || $attempt >= $this->maxRetries) {
                    throw $e;
                }

                $lastException = $e;
                $delay = $this->calculateDelay($attempt, $e);
                usleep($delay * 1000);
                $attempt++;
            }
        }

        throw $lastException;
    }

    private function isRetryable(Throwable $e): bool {
        foreach ($this->retryableExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    private function calculateDelay(int $attempt, Throwable $e): int {
        // Use Retry-After header if available
        if ($e instanceof RateLimitException && $e->retryAfterSeconds !== null) {
            return $e->retryAfterSeconds * 1000;
        }

        // Exponential backoff with jitter
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** $attempt));
        $jitter = random_int(0, (int) ($delay * 0.1));
        return $delay + $jitter;
    }
}
```

**Integration with Inference class:**
```php
class Inference {
    private ?RetryMiddleware $retryMiddleware = null;

    public function withRetry(int $maxRetries = 3, int $baseDelayMs = 1000): static {
        $clone = clone $this;
        $clone->retryMiddleware = new RetryMiddleware($maxRetries, $baseDelayMs);
        return $clone;
    }

    public function get(): InferenceResponse {
        $operation = fn() => $this->pendingInference->get();

        if ($this->retryMiddleware) {
            return $this->retryMiddleware->wrap($operation);
        }

        return $operation();
    }
}
```

**Usage:**
```php
$response = $inference
    ->withRetry(maxRetries: 3, baseDelayMs: 1000)
    ->create()
    ->get();
```

**Benefit**: Production-ready resilience without external packages.

---

## Priority 3: Request Validation Layer

### Current State
Invalid requests fail at the provider API level with unclear errors.

### Recommendation: Pre-flight Validation

**Inspired by**: Prism's capability checking

```php
namespace Cognesy\Polyglot\Validation;

class RequestValidator {
    public function __construct(
        private CapabilityChecker $capabilities,
    ) {}

    public function validate(InferenceRequest $request): ValidationResult {
        $errors = [];

        // Check model exists
        if (!$this->capabilities->hasModel($request->model())) {
            $errors[] = new ValidationError('model', "Unknown model: {$request->model()}");
        }

        // Check tool compatibility
        if ($request->hasTools() && !$this->capabilities->supportsTools($request)) {
            $errors[] = new ValidationError('tools', "Model {$request->model()} does not support tools");
        }

        // Check JSON mode + tools compatibility (Gemini issue)
        if ($request->hasTools() && $request->hasJsonMode() && !$this->capabilities->supportsToolsWithJsonMode($request)) {
            $errors[] = new ValidationError('response_format', "Model {$request->model()} cannot use JSON mode with tools simultaneously");
        }

        // Check message content types
        foreach ($request->messages() as $message) {
            if ($message->content()->hasImages() && !$this->capabilities->supportsVision($request)) {
                $errors[] = new ValidationError('content', "Model {$request->model()} does not support vision");
            }
        }

        // Check max tokens
        if ($request->maxTokens() > $this->capabilities->maxOutputTokens($request)) {
            $errors[] = new ValidationError('max_tokens', "Requested {$request->maxTokens()} exceeds model limit");
        }

        return new ValidationResult($errors);
    }
}

final class ValidationResult {
    public function __construct(
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function throw(): void {
        if (!$this->isValid()) {
            throw new RequestValidationException($this->errors);
        }
    }
}
```

**Integration:**
```php
// In PendingInference::get()
public function get(): InferenceResponse {
    $validation = $this->validator->validate($this->request);
    $validation->throw(); // Fail fast with clear error

    return $this->driver->handle($this->request);
}
```

**Benefit**: Clear errors before hitting the API, better DX.

---

## Priority 4: Explicit Capability Model

### Current State
Capabilities are scattered across BodyFormat methods:
```php
// OpenAIBodyFormat
protected function supportsStructuredOutput(InferenceRequest $request): bool { return true; }

// GeminiBodyFormat
protected function supportsNonTextResponseForTools(InferenceRequest $request): bool { return false; }
```

### Recommendation: First-Class Capability Objects

```php
namespace Cognesy\Polyglot\Capabilities;

interface CapabilitySet {
    public function supportsTools(): bool;
    public function supportsToolChoice(): bool;
    public function supportsJsonMode(): bool;
    public function supportsJsonSchema(): bool;
    public function supportsVision(): bool;
    public function supportsAudio(): bool;
    public function supportsStreaming(): bool;
    public function supportsToolsWithJsonMode(): bool;
    public function maxInputTokens(): int;
    public function maxOutputTokens(): int;
}

final class ModelCapabilities implements CapabilitySet {
    public function __construct(
        private string $provider,
        private string $model,
        private bool $tools = false,
        private bool $toolChoice = false,
        private bool $jsonMode = false,
        private bool $jsonSchema = false,
        private bool $vision = false,
        private bool $audio = false,
        private bool $streaming = true,
        private bool $toolsWithJsonMode = false,
        private int $maxInput = 128_000,
        private int $maxOutput = 4_096,
    ) {}

    // Implement interface methods...

    public static function openAI(string $model): self {
        return match(true) {
            str_starts_with($model, 'gpt-4o') => new self(
                provider: 'openai',
                model: $model,
                tools: true,
                toolChoice: true,
                jsonMode: true,
                jsonSchema: true,
                vision: true,
                audio: str_contains($model, 'audio'),
                streaming: true,
                toolsWithJsonMode: true,
                maxInput: 128_000,
                maxOutput: 16_384,
            ),
            str_starts_with($model, 'o1') => new self(
                provider: 'openai',
                model: $model,
                tools: false,  // o1 doesn't support tools
                streaming: false,  // o1 doesn't support streaming
                // ...
            ),
            // ... other models
        };
    }

    public static function anthropic(string $model): self { /* ... */ }
    public static function gemini(string $model): self { /* ... */ }
}

// Registry
final class CapabilityRegistry {
    private array $capabilities = [];

    public function register(string $provider, string $model, CapabilitySet $capabilities): void {
        $this->capabilities["{$provider}:{$model}"] = $capabilities;
    }

    public function get(string $provider, string $model): CapabilitySet {
        $key = "{$provider}:{$model}";

        // Try exact match
        if (isset($this->capabilities[$key])) {
            return $this->capabilities[$key];
        }

        // Try pattern matching (e.g., "gpt-4o*")
        foreach ($this->capabilities as $pattern => $caps) {
            if (fnmatch($pattern, $key)) {
                return $caps;
            }
        }

        // Return default capabilities
        return $this->getDefault($provider);
    }
}
```

**Benefit**: Centralized, queryable capability information; enables request validation and smart defaults.

---

## Priority 5: Builder Pattern for Request Construction (Optional)

### Current State
Request construction via array or direct InferenceRequest:
```php
$request = new InferenceRequest(
    messages: $messages,
    model: 'gpt-4o',
    // ... many parameters
);
```

### Recommendation: Fluent Builder (Additive, Not Replacement)

**Inspired by**: Prism's PendingRequest

```php
namespace Cognesy\Polyglot\Request;

class InferenceRequestBuilder {
    private array $data = [];

    public static function make(): self {
        return new self();
    }

    public function model(string $model): self {
        $this->data['model'] = $model;
        return $this;
    }

    public function systemPrompt(string $prompt): self {
        $this->data['systemPrompt'] = $prompt;
        return $this;
    }

    public function messages(Messages|array $messages): self {
        $this->data['messages'] = $messages instanceof Messages ? $messages : Messages::fromArray($messages);
        return $this;
    }

    public function addMessage(string $role, string $content): self {
        $this->data['messages'] ??= Messages::empty();
        $this->data['messages'] = $this->data['messages']->add(Message::fromArray([
            'role' => $role,
            'content' => $content,
        ]));
        return $this;
    }

    public function tools(array $tools): self {
        $this->data['tools'] = $tools;
        return $this;
    }

    public function maxTokens(int $tokens): self {
        $this->data['maxTokens'] = $tokens;
        return $this;
    }

    public function temperature(float $temp): self {
        $this->data['temperature'] = $temp;
        return $this;
    }

    public function jsonMode(): self {
        $this->data['responseFormat'] = ResponseFormat::json();
        return $this;
    }

    public function jsonSchema(array $schema, ?string $name = null): self {
        $this->data['responseFormat'] = ResponseFormat::jsonSchema($schema, $name);
        return $this;
    }

    public function build(): InferenceRequest {
        return InferenceRequest::fromArray($this->data);
    }
}
```

**Usage:**
```php
$request = InferenceRequestBuilder::make()
    ->model('gpt-4o')
    ->systemPrompt('You are a helpful assistant')
    ->addMessage('user', 'Hello!')
    ->maxTokens(1000)
    ->jsonMode()
    ->build();
```

**Benefit**: Better DX for users who prefer fluent APIs.

---

## Priority 6: Lazy Response Conversion

### Current State
Responses are always fully parsed:
```php
$response = $this->responseTranslator->fromResponse($httpResponse);
```

### Recommendation: Deferred Parsing (Optional)

**Inspired by**: Symfony AI's DeferredResult

```php
namespace Cognesy\Polyglot\Response;

class LazyInferenceResponse {
    private ?InferenceResponse $resolved = null;

    public function __construct(
        private HttpResponse $rawResponse,
        private ResponseAdapter $adapter,
    ) {}

    private function resolve(): InferenceResponse {
        return $this->resolved ??= $this->adapter->fromResponse($this->rawResponse);
    }

    // Delegate methods that trigger parsing
    public function content(): string {
        return $this->resolve()->content();
    }

    public function toolCalls(): ToolCalls {
        return $this->resolve()->toolCalls();
    }

    public function usage(): Usage {
        return $this->resolve()->usage();
    }

    // Quick access without full parsing
    public function rawJson(): array {
        return json_decode($this->rawResponse->body(), true);
    }

    public function rawBody(): string {
        return $this->rawResponse->body();
    }

    public function statusCode(): int {
        return $this->rawResponse->statusCode();
    }
}
```

**Benefit**: Skip parsing overhead when only raw data is needed; enables gradual response inspection.

---

## Priority 7: Multi-Turn Response Aggregation

### Current State
Each inference call returns independent response. Multi-turn tool use requires manual tracking.

### Recommendation: ResponseBuilder for Multi-Step

**Inspired by**: Prism's ResponseBuilder

```php
namespace Cognesy\Polyglot\Response;

class ResponseAggregator {
    private array $steps = [];
    private Usage $totalUsage;

    public function __construct() {
        $this->totalUsage = Usage::empty();
    }

    public function addStep(InferenceResponse $response): self {
        $this->steps[] = $response;
        $this->totalUsage = $this->totalUsage->withAccumulated($response->usage());
        return $this;
    }

    public function stepCount(): int {
        return count($this->steps);
    }

    public function allContent(): string {
        return implode('', array_map(fn($r) => $r->content(), $this->steps));
    }

    public function allToolCalls(): ToolCalls {
        $allCalls = [];
        foreach ($this->steps as $step) {
            $allCalls = array_merge($allCalls, $step->toolCalls()->all());
        }
        return ToolCalls::fromArray($allCalls);
    }

    public function totalUsage(): Usage {
        return $this->totalUsage;
    }

    public function lastResponse(): ?InferenceResponse {
        return $this->steps[count($this->steps) - 1] ?? null;
    }

    public function toAggregatedResponse(): InferenceResponse {
        $last = $this->lastResponse();

        return new InferenceResponse(
            content: $this->allContent(),
            toolCalls: $this->allToolCalls(),
            finishReason: $last?->finishReason() ?? InferenceFinishReason::Other,
            usage: $this->totalUsage,
            responseData: $last?->responseData(),
        );
    }
}
```

**Usage in agentic loops:**
```php
$aggregator = new ResponseAggregator();

while ($shouldContinue) {
    $response = $inference->create()->get();
    $aggregator->addStep($response);

    if ($response->hasToolCalls()) {
        $messages = $this->executeToolsAndBuildMessages($response->toolCalls());
        $inference = $inference->withMessages($messages);
    } else {
        $shouldContinue = false;
    }
}

$finalResponse = $aggregator->toAggregatedResponse();
echo "Total tokens: " . $finalResponse->usage()->total();
```

**Benefit**: Proper token accounting and content aggregation for multi-turn interactions.

---

## Summary: Implementation Priority Order

### Phase 1: Error Handling (High Impact, Low Effort)
1. ✅ Typed exception hierarchy
2. ✅ Rate limit detection with Retry-After parsing
3. ✅ Update BaseInferenceDriver to throw specific exceptions

### Phase 2: Resilience (High Impact, Medium Effort)
4. ✅ RetryMiddleware with exponential backoff
5. ✅ Integration via `withRetry()` method

### Phase 3: Validation (Medium Impact, Medium Effort)
6. ✅ Request validation layer
7. ✅ Capability registry
8. ✅ Pre-flight validation

### Phase 4: Developer Experience (Medium Impact, Low Effort)
9. ✅ Optional fluent builder
10. ✅ ResponseAggregator for multi-turn

### Phase 5: Performance (Low Impact, Low Effort)
11. ✅ Lazy response conversion (optional)

---

## What NOT to Change

Polyglot's existing strengths should be preserved:

1. **Hierarchical State Machine** - InferenceExecution → InferenceAttempt is excellent for retry tracking
2. **Rich Message Domain Model** - Messages → Content → ContentPart with sections
3. **Functional Stream Operations** - map/reduce/filter are valuable
4. **Usage Tracking** - Overflow protection, cumulative vs delta handling
5. **Event-Driven Architecture** - Dispatch before throw pattern
6. **Clean Package Separation** - Polyglot vs Instructor boundary
7. **Capability Foundation** - BodyFormat methods (just needs promotion to first-class)
8. **HTTP Request Pool** - Unique among analyzed libraries; typed collections, Result monad, multi-driver support

---

## Comparison: Before vs After

| Aspect | Current | After Improvements |
|--------|---------|-------------------|
| **Error Handling** | Generic RuntimeException | Typed hierarchy with context |
| **Retry** | External/manual | Built-in with exponential backoff |
| **Validation** | At API (unclear errors) | Pre-flight with clear messages |
| **Capabilities** | Scattered methods | Centralized registry |
| **Request Construction** | Array/constructor | Optional fluent builder |
| **Multi-Turn** | Manual tracking | ResponseAggregator |
| **Parallel Requests** | ✅ Already excellent | N/A - preserve as-is |

---

## Estimated Effort

| Feature | Complexity | Files Changed | Est. Hours |
|---------|------------|---------------|------------|
| Exception hierarchy | Low | 5-8 | 4-6 |
| RetryMiddleware | Low | 3-4 | 3-4 |
| Request validation | Medium | 6-10 | 8-12 |
| Capability registry | Medium | 8-15 | 10-16 |
| Fluent builder | Low | 2-3 | 2-4 |
| ResponseAggregator | Low | 1-2 | 2-3 |
| Lazy response | Low | 2-3 | 2-3 |

**Total**: ~30-50 hours for full implementation

---

## Conclusion

InstructorPHP Polyglot is already architecturally sophisticated. The recommendations above fill specific gaps observed in competing libraries without disrupting the excellent existing foundations. The priority order focuses on high-impact, production-critical features first (error handling, retry) before moving to DX improvements (builder pattern, lazy conversion).
