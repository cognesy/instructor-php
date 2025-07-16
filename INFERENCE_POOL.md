# Inference Pool Integration Design

## Overview

This document outlines the design for integrating parallel request execution capabilities into the Polyglot Inference API, leveraging the existing HTTP Client pool functionality.

## Current Architecture Analysis

### Existing Inference API Structure

The Inference system follows a **layered architecture** with clear separation of concerns:

- **Inference** (facade) - Main entry point with trait-based functionality
- **InferenceRequestBuilder** - Builds InferenceRequest objects
- **PendingInference** - Deferred execution wrapper
- **InferenceRequest** - Immutable request data structure
- **LLMProvider** - Manages driver configuration and creation
- **InferenceDriverFactory** - Creates concrete driver instances

### Current API Patterns

#### Fluent Builder Pattern
```php
$result = (new Inference())
    ->withMessages("Hello world")
    ->withModel("gpt-4")
    ->withMaxTokens(100)
    ->withStreaming(true)
    ->get();
```

#### Trait-Based Organization
- **`HandlesLLMProvider`** - LLM configuration (DSN, presets, HTTP client)
- **`HandlesRequestBuilder`** - Request building methods (messages, model, tools, etc.)
- **`HandlesInvocation`** - Core execution methods (`create()`, `with()`)
- **`HandlesShortcuts`** - Convenience methods (`get()`, `asJson()`, `response()`)

#### Deferred Execution Model
1. **Building Phase**: Configure request parameters via fluent methods
2. **Creation Phase**: Call `create()` to build `PendingInference` 
3. **Execution Phase**: Call terminal methods (`get()`, `response()`, `stream()`) to execute

## Proposed API Design

### Recommended Approach: Fluent Pool Integration

Extend the existing Inference class rather than creating a separate `InferencePool` to maintain API consistency.

#### Basic Usage Pattern
```php
// Current single request pattern
$result = (new Inference())
    ->withMessages("Hello")
    ->withModel("gpt-4")
    ->get();

// Proposed parallel pattern - maintains same fluent style
$results = (new Inference())
    ->withRequests([
        InferenceRequest::create()->withMessages("Hello")->withModel("gpt-4"),
        InferenceRequest::create()->withMessages("World")->withModel("gpt-3.5-turbo"),
    ])
    ->withMaxConcurrent(2)
    ->getAll();

// Or with pool creation for deferred execution
$pool = (new Inference())
    ->withRequests($requests)
    ->withMaxConcurrent(3)
    ->createPool();

$results = $pool->all();
```

### API Usage Examples

#### Basic Parallel Execution
```php
$requests = [
    InferenceRequest::create()->withMessages("Explain AI")->withModel("gpt-4"),
    InferenceRequest::create()->withMessages("Explain ML")->withModel("gpt-3.5-turbo"),
    InferenceRequest::create()->withMessages("Explain DL")->withModel("claude-3-sonnet"),
];

$results = (new Inference())
    ->using('openai/gpt-4')
    ->withRequests($requests)
    ->withMaxConcurrent(3)
    ->getAll();
```

#### Mixture of Experts Pattern
```php
$prompt = "Explain quantum computing";
$presets = ["openai/o3", "anthropic/claude-4-sonnet", "gemini/2.5-pro"];

$requests = array_map(
    fn($model) => InferenceRequest::create()->withMessages($prompt)->withModel($model),
    $models
);

$results = (new Inference())
    ->withRequests($requests)
    ->withMaxConcurrent(3)
    ->getAll();

// Process results for comparison
foreach ($results as $i => $result) {
    if ($result->isSuccess()) {
        echo "Model {$models[$i]}: " . $result->unwrap()->content();
    }
}
```

#### Deferred Pool Execution
```php
$pool = (new Inference())
    ->using('openai/gpt-4')
    ->withRequests($requests)
    ->withMaxConcurrent(2)
    ->createPool();

// Execute multiple times with different concurrency
$fastResults = $pool->all(maxConcurrent: 5);
$slowResults = $pool->all(maxConcurrent: 1);
```

## Required Refactoring

### Current Architecture Issue

The current architecture **encapsulates** the HTTP request/response translation, but pool operations need **direct access** to these translation methods to bypass individual HTTP execution.

**Current BaseInferenceDriver structure:**
- `makeHttpRequest()` - **protected** (needs to be accessible)
- `fromResponse()` - **protected** (needs to be accessible)
- HTTP execution happens individually through `handleHttpRequest()` -> `PendingHttpResponse` -> `get()`

### 1. Extend CanHandleInference Interface

```php
interface CanHandleInference
{
    // Existing methods
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;
    
    // New pool support methods
    public function toHttpRequest(InferenceRequest $request): HttpRequest;
    public function fromHttpResponse(HttpResponse $response): InferenceResponse;
    public function getHttpClient(): HttpClient;
    public function supportsPool(): bool;
}
```

### 2. Refactor BaseInferenceDriver

```php
abstract class BaseInferenceDriver implements CanHandleInference
{
    // NEW PUBLIC METHODS FOR POOL SUPPORT
    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        return $this->makeHttpRequest($request);
    }

    public function fromHttpResponse(HttpResponse $response): InferenceResponse {
        return $this->fromResponse($response);
    }

    public function getHttpClient(): HttpClient {
        return $this->httpClient;
    }

    public function supportsPool(): bool {
        return true; // Most drivers will support this
    }

    // Update existing methods to use public methods
    protected function makeHttpResponse(InferenceRequest $request): HttpResponse {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));

        try {
            $httpRequest = $this->toHttpRequest($request);  // Use public method
            $pendingHttpResponse = $this->handleHttpRequest($httpRequest);
            $httpResponse = $pendingHttpResponse->get();
        } catch(Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]));
            throw $e;
        }

        return $httpResponse;
    }

    // ... other methods unchanged ...
}
```

### 3. Pool Implementation

```php
class PendingInferencePool
{
    public function __construct(
        private array $requests,
        private CanHandleInference $driver,
        private EventDispatcherInterface $events,
        private ?int $maxConcurrent = null
    ) {}

    public function all(?int $maxConcurrent = null): array
    {
        $maxConcurrent = $maxConcurrent ?? $this->maxConcurrent;
        
        // Convert InferenceRequest[] to HttpRequest[]
        $httpRequests = array_map(
            fn($request) => $this->driver->toHttpRequest($request),
            $this->requests
        );
        
        // Execute HTTP pool directly
        $httpClient = $this->driver->getHttpClient();
        $httpResults = $httpClient->pool($httpRequests, $maxConcurrent);
        
        // Convert HttpResponse[] back to InferenceResponse[]
        return array_map(
            fn($result, $originalRequest) => $this->processResult($result, $originalRequest),
            $httpResults,
            $this->requests
        );
    }
    
    private function processResult($httpResult, InferenceRequest $originalRequest): Result
    {
        if ($httpResult->isSuccess()) {
            try {
                $response = $this->driver->fromHttpResponse($httpResult->unwrap());
                $this->events->dispatch(new InferenceResponseCreated(['response' => $response->toArray()]));
                return Result::success($response);
            } catch (Exception $e) {
                $this->events->dispatch(new InferenceFailed([
                    'exception' => $e->getMessage(),
                    'request' => $originalRequest->toArray(),
                ]));
                return Result::failure($e);
            }
        }
        
        $this->events->dispatch(new InferenceFailed([
            'exception' => $httpResult->error()->getMessage(),
            'request' => $originalRequest->toArray(),
        ]));
        return Result::failure($httpResult->error());
    }
}
```

### 4. Integration with Inference Class

```php
// Add to HandlesInferencePool trait
trait HandlesInferencePool
{
    protected array $requests = [];
    protected ?int $maxConcurrent = null;
    
    public function withRequests(array $requests): static
    {
        $this->requests = $requests;
        return $this;
    }
    
    public function withMaxConcurrent(?int $maxConcurrent): static
    {
        $this->maxConcurrent = $maxConcurrent;
        return $this;
    }
    
    public function createPool(): PendingInferencePool
    {
        $driver = $this->llmProvider->createDriver();
        
        if (!$driver->supportsPool()) {
            throw new UnsupportedOperationException('Driver does not support pool operations');
        }
        
        return new PendingInferencePool(
            $this->requests,
            $driver,
            $this->eventDispatcher,
            $this->maxConcurrent
        );
    }
    
    public function getAll(): array
    {
        return $this->createPool()->all();
    }
}
```

## Key Benefits

1. **DDD Compliance**: Maintains single responsibility - Inference handles inference, whether single or multiple
2. **API Consistency**: Same fluent patterns, same method naming conventions  
3. **Backward Compatibility**: Existing code unchanged, new methods are additive
4. **Trait Organization**: Follows established trait-based architecture
5. **Deferred Execution**: Maintains the create/execute pattern
6. **Event Integration**: Seamlessly integrates with existing event system
7. **Driver Abstraction**: Works with all LLM providers through driver interface
8. **Performance**: Direct HTTP client pool usage without wrapper overhead

## Migration Path

1. Update `CanHandleInference` interface
2. Refactor `BaseInferenceDriver` to expose required methods
3. Update concrete drivers (no changes needed if they extend `BaseInferenceDriver`)
4. Add `HandlesInferencePool` trait to `Inference` class
5. Create `PendingInferencePool` class
6. Add appropriate Result wrapper classes if not already present

## Use Cases

### Multiple LLM APIs
Query multiple LLM providers in parallel for comparison or redundancy.

### Mixture of Experts
Send the same query to multiple models for comparison and selection of best response.

### Batch Processing
Process multiple API calls efficiently with configurable concurrency limits.

### A/B Testing
Test different prompts or models simultaneously to compare performance.

## Next Steps

1. Implement the refactoring changes to `BaseInferenceDriver` and `CanHandleInference`
2. Create the `PendingInferencePool` class
3. Add the `HandlesInferencePool` trait to the `Inference` class
4. Test with existing drivers (OpenAI, Anthropic, etc.)
5. Add comprehensive test coverage for pool operations
6. Update documentation with usage examples

This design maintains clean architecture principles while enabling efficient parallel request processing through the existing HTTP client pool infrastructure.