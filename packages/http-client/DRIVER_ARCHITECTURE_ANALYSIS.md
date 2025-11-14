# HTTP Driver Architecture Analysis

## Current Implementation Comparison

### Overview

| Driver | Lines | Modularity | Exception Handling | Configuration | Framework Leverage |
|--------|-------|------------|-------------------|---------------|-------------------|
| CurlNew | 186 | High (3 components) | Specific types | Factory (reusable) | Native curl |
| Curl (old) | 375 | Low (monolithic) | Specific types | Inline (duplicated) | Native curl |
| Symfony | 155 | Low | Exception mapping | Inline | Full (HttpClient) |
| Laravel | 189 | Low | Exception mapping | Inline | Full (HTTP Client) |
| Guzzle | 141 | Low | Exception mapping | Inline | Full (PSR-7) |
| Mock | 268 | Medium | Minimal | N/A | Test double |

---

## Detailed Analysis

### 1. CurlNewDriver - The Modular Reference

**Architecture:**
```
CurlNewDriver (orchestrator - 186 lines)
├── CurlFactory (configuration - shared with CurlNewPool)
├── HeaderParser (header parsing - single responsibility)
├── CurlHandle (resource lifecycle - automatic cleanup)
└── Response Adapters:
    ├── SyncCurlResponseAdapter (blocking)
    └── StreamingCurlResponseAdapter (progressive)
```

**Code Structure:**
```php
handle(HttpRequest $request): HttpResponse
├── dispatchRequestSent()
├── handleSync() OR handleStreaming()
│   ├── factory->createHandle()
│   ├── Configure options
│   └── Create adapter → toHttpResponse()
├── validateStatusCodeOrFail()
└── dispatchResponseReceived()
```

**Strengths:**
- ✅ **Zero duplication** - CurlFactory shared between driver and pool
- ✅ **Clear separation** - Factory, Parser, Handle, Adapter
- ✅ **Minimal code** - 186 lines vs 375 in old Curl
- ✅ **Specific exceptions** - TimeoutException, ConnectionException, NetworkException
- ✅ **Automatic cleanup** - CurlHandle destructor
- ✅ **Type-safe** - Strong typing throughout

---

### 2. CurlDriver (Old) - The Monolith

**Issues:**
- ❌ **375 lines** - 2x the size of CurlNew
- ❌ **Massive duplication** - Configuration duplicated between sync/streaming
  - Lines 87-156: `configureCurl()` for sync (70 lines)
  - Lines 158-238: `handleStreaming()` reconfigures everything (80 lines)
- ❌ **Mixed concerns** - Configuration, execution, parsing all inline
- ❌ **State management** - `$this->responseHeaders` array member
- ❌ **No reusability** - Can't share configuration with pool

**Recommendation:** Deprecate in favor of CurlNewDriver

---

### 3. SymfonyDriver - Clean Framework Integration

**Structure (155 lines):**
```php
handle() → performHttpCall() → handleException() → buildHttpResponse()
```

**Strengths:**
- ✅ **Leverages Symfony** - Uses HttpClient's built-in features
- ✅ **Clean** - Only 155 lines
- ✅ **Exception mapping** - Translates Symfony exceptions

**Issues:**
- ⚠️ **String-based detection** - Exception types determined by message content
- ⚠️ **Inline configuration** - Can't reuse in pool

---

### 4. LaravelDriver - Dual-Mode Configuration

**Structure (189 lines):**

**Strengths:**
- ✅ **Flexible** - Accepts Factory or PendingRequest
- ✅ **Match-based routing** - Clean method dispatch

**Issues:**
- ⚠️ **String matching** - Exception detection via message content
- ⚠️ **Mixed configuration** - Factory vs PendingRequest paths

---

### 5. GuzzleDriver - Simplest Implementation

**Structure (141 lines):**

**Strengths:**
- ✅ **Simplest** - Only 141 lines
- ✅ **PSR-7 compliant** - Standard interfaces

**Issues:**
- ⚠️ **String matching** - Exception detection
- ⚠️ **JSON assumption** - Always uses 'json' option

---

### 6. MockHttpDriver - Test Double

**Purpose:** Different from production drivers - provides test doubles

**Note:** Should remain separate - test doubles have different patterns

---

## Common Patterns Across All Drivers

### 1. Constructor Pattern (Duplicated 5×)
```php
public function __construct(
    HttpClientConfig $config,
    EventDispatcherInterface $events,
    ?object $clientInstance = null,
) {
    $this->config = $config;
    $this->events = $events;
    $this->client = $clientInstance ?? $this->createDefaultClient();
}
```
**Duplication:** ~15 lines × 5 drivers = **75 lines**

---

### 2. Handle Method Pattern (Duplicated 5×)
```php
public function handle(HttpRequest $request): HttpResponse {
    $this->dispatchRequestSent($request);
    try {
        $rawResponse = $this->performHttpCall($request);
    } catch (FrameworkException $e) {
        $this->handleException($e, $request);
    }
    $httpResponse = $this->buildHttpResponse($rawResponse, $request);
    if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
        $exception = HttpExceptionFactory::fromStatusCode(...);
        throw $exception;
    }
    $this->dispatchResponseReceived($httpResponse);
    return $httpResponse;
}
```
**Duplication:** ~20 lines × 5 drivers = **100 lines**

---

### 3. Event Dispatching Methods (Duplicated 5×)

```php
private function dispatchRequestSent(HttpRequest $request): void {
    $this->events->dispatch(new HttpRequestSent([
        'url' => $request->url(),
        'method' => $request->method(),
        'headers' => $request->headers(),
        'body' => $request->body()->toArray(),
    ]));
}

private function dispatchRequestFailed(...): void { /* ... */ }
private function dispatchResponseReceived(...): void { /* ... */ }
private function dispatchStatusCodeFailed(...): void { /* ... */ }
```
**Duplication:** ~28 lines × 5 drivers = **140 lines**

---

### 4. Exception Handling Pattern (Duplicated 3×)

All framework drivers use string matching:

```php
// Symfony, Laravel, Guzzle all have similar:
$httpException = str_contains($message, 'timeout')
    ? new TimeoutException(...)
    : new ConnectionException(...);
```

**Issue:** Fragile string matching duplicated 3× times

---

## Total Code Duplication

| Pattern | Lines per Driver | Drivers | Total |
|---------|-----------------|---------|-------|
| Constructor | 15 | 5 | 75 |
| Handle method flow | 20 | 5 | 100 |
| Event dispatching | 28 | 5 | 140 |
| Status validation | 10 | 5 | 50 |
| **Subtotal** | **73** | **5** | **365** |
| CurlDriver internal | - | 1 | 150 |
| **TOTAL** | - | - | **515** |

---

## Standardization Strategy

### 1. AbstractDriver Base Class

```php
abstract class AbstractDriver implements CanHandleHttpRequest
{
    public function __construct(
        protected readonly HttpClientConfig $config,
        protected readonly EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->validateClientInstance($clientInstance);
        $this->client = $clientInstance ?? $this->createDefaultClient();
    }

    // Template method - final
    final public function handle(HttpRequest $request): HttpResponse {
        $this->dispatchRequestSent($request);

        try {
            $rawResponse = $this->performHttpCall($request);
        } catch (\Throwable $e) {
            $this->handleDriverException($e, $request);
        }

        $httpResponse = $this->buildHttpResponse($rawResponse, $request);
        $this->validateStatusCodeOrFail($httpResponse, $request);
        $this->dispatchResponseReceived($httpResponse);

        return $httpResponse;
    }

    // Abstract methods - implemented by drivers
    abstract protected function performHttpCall(HttpRequest $request): mixed;
    abstract protected function buildHttpResponse(mixed $rawResponse, HttpRequest $request): HttpResponse;
    abstract protected function handleDriverException(\Throwable $e, HttpRequest $request): never;
    abstract protected function createDefaultClient(): object;
    abstract protected function validateClientInstance(?object $instance): void;

    // Common implementations (event dispatching, status validation)
    protected function validateStatusCodeOrFail(HttpResponse $response, HttpRequest $request): void {
        if (!$this->config->failOnError || $response->statusCode() < 400) {
            return;
        }
        $exception = HttpExceptionFactory::fromStatusCode(
            $response->statusCode(),
            $request,
            $response,
            null
        );
        $this->dispatchStatusCodeFailed($response->statusCode(), $request);
        throw $exception;
    }

    protected function dispatchRequestSent(HttpRequest $request): void { /* ... */ }
    protected function dispatchResponseReceived(HttpResponse $response): void { /* ... */ }
    protected function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void { /* ... */ }
    protected function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void { /* ... */ }
}
```

**Impact:** Eliminates ~300 lines of duplication

---

### 2. Driver Factory Pattern (like CurlNew)

Each driver gets a factory for configuration:

```php
// Symfony
class SymfonyRequestFactory {
    public function __construct(private HttpClientConfig $config) {}

    public function createOptions(HttpRequest $request): array {
        return [
            'headers' => $request->headers(),
            'body' => $this->prepareBody($request),
            'timeout' => $this->config->idleTimeout,
            'max_duration' => $this->config->requestTimeout,
            'buffer' => !$request->isStreamed(),
        ];
    }

    private function prepareBody(HttpRequest $request): mixed {
        $body = $request->body()->toArray();
        return is_array($body) ? json_encode($body) : $body;
    }
}

// Laravel
class LaravelRequestFactory {
    public function __construct(private HttpClientConfig $config) {}

    public function createPendingRequest(
        HttpFactory|PendingRequest $client,
        HttpRequest $request
    ): PendingRequest {
        $pending = $client instanceof PendingRequest
            ? clone $client
            : $client->timeout($this->config->requestTimeout);

        return $pending
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($request->headers())
            ->when($request->isStreamed(), fn($p) => $p->withOptions(['stream' => true]));
    }
}

// Guzzle
class GuzzleRequestFactory {
    public function __construct(private HttpClientConfig $config) {}

    public function createOptions(HttpRequest $request): array {
        return [
            'headers' => $request->headers(),
            'json' => $request->body()->toArray(),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'stream' => $request->isStreamed(),
            'http_errors' => false,
        ];
    }
}
```

**Benefits:**
- Reusable between driver and pool
- Testable in isolation
- Single responsibility

---

### 3. Exception Mapper Pattern

Standardize exception translation:

```php
interface DriverExceptionMapper {
    public function map(\Throwable $exception, HttpRequest $request): HttpRequestException;
}

class SymfonyExceptionMapper implements DriverExceptionMapper {
    public function map(\Throwable $exception, HttpRequest $request): HttpRequestException {
        if ($exception instanceof TransportExceptionInterface) {
            return $this->mapTransportException($exception, $request);
        }
        if ($exception instanceof HttpExceptionInterface) {
            return $this->mapHttpException($exception, $request);
        }
        return new NetworkException($exception->getMessage(), $request, null, null, $exception);
    }

    private function mapTransportException(TransportExceptionInterface $e, HttpRequest $request): HttpRequestException {
        $message = $e->getMessage();
        return match (true) {
            $this->isTimeout($message) => new TimeoutException($message, $request, null, $e),
            $this->isConnectionError($message) => new ConnectionException($message, $request, null, $e),
            default => new NetworkException($message, $request, null, null, $e),
        };
    }

    private function isTimeout(string $message): bool {
        return str_contains($message, 'timeout') || str_contains($message, 'timed out');
    }

    private function isConnectionError(string $message): bool {
        return str_contains($message, 'Failed to connect')
            || str_contains($message, 'Could not resolve host');
    }
}
```

**Benefits:**
- Centralizes string matching logic
- Testable in isolation
- Reusable across driver and pool

---

### 4. Refactored Driver Example

**SymfonyDriver AFTER refactoring:**

```php
class SymfonyDriver extends AbstractDriver
{
    private readonly SymfonyRequestFactory $factory;
    private readonly SymfonyExceptionMapper $mapper;

    protected function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        parent::__construct($config, $events, $clientInstance);
        $this->factory = new SymfonyRequestFactory($config);
        $this->mapper = new SymfonyExceptionMapper();
    }

    #[\Override]
    protected function createDefaultClient(): HttpClientInterface {
        return SymfonyHttpClient::create(['http_version' => '2.0']);
    }

    #[\Override]
    protected function validateClientInstance(?object $instance): void {
        if ($instance !== null && !($instance instanceof HttpClientInterface)) {
            throw new \InvalidArgumentException(
                'Client must be Symfony\Contracts\HttpClient\HttpClientInterface'
            );
        }
    }

    #[\Override]
    protected function performHttpCall(HttpRequest $request): ResponseInterface {
        $options = $this->factory->createOptions($request);
        return $this->client->request($request->method(), $request->url(), $options);
    }

    #[\Override]
    protected function buildHttpResponse(mixed $rawResponse, HttpRequest $request): HttpResponse {
        return (new SymfonyHttpResponseAdapter(
            client: $this->client,
            response: $rawResponse,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            connectTimeout: $this->config->connectTimeout,
        ))->toHttpResponse();
    }

    #[\Override]
    protected function handleDriverException(\Throwable $e, HttpRequest $request): never {
        $exception = $this->mapper->map($e, $request);
        $this->dispatchRequestFailed($exception, $request);
        throw $exception;
    }
}
```

**Result:** 155 lines → ~60 lines (61% reduction)

---

## Expected Outcomes

### Code Reduction

| Driver | Before | After | Reduction |
|--------|--------|-------|-----------|
| Symfony | 155 | ~60 | 61% |
| Laravel | 189 | ~70 | 63% |
| Guzzle | 141 | ~55 | 61% |
| CurlNew | 186 | ~80 | 57% |
| **Total** | **671** | **265** | **60%** |

### Duplication Elimination

- Event dispatching: **-140 lines**
- Constructor logic: **-75 lines**
- Handle method flow: **-100 lines**
- Status validation: **-50 lines**
- **Total: -365 lines**

### New Shared Components

| Component | Lines | Used By | Effective Lines |
|-----------|-------|---------|-----------------|
| AbstractDriver | ~120 | All (5×) | 120 ÷ 5 = 24 per driver |
| Factories (3×) | ~125 | Driver + Pool (2×) | 125 ÷ 6 = 21 per use |
| Mappers (3×) | ~145 | Driver + Pool (2×) | 145 ÷ 6 = 24 per use |
| **Total** | **~390** | **Reused** | **~69 effective** |

**Net Impact:**
- Remove: 365 lines duplication
- Add: 390 lines shared (but reused 2-3×)
- **Effective reduction: ~300 lines**

---

## Refactoring Roadmap

### Phase 1: Foundation (1-2 days)
1. Create `AbstractDriver` base class
2. Create `DriverExceptionMapper` interface
3. Add tests for AbstractDriver

**Files:**
- `src/Drivers/AbstractDriver.php`
- `src/Contracts/DriverExceptionMapper.php`
- `tests/Unit/AbstractDriverTest.php`

---

### Phase 2: Factories (2-3 days)
1. Create `SymfonyRequestFactory`
2. Create `LaravelRequestFactory`
3. Create `GuzzleRequestFactory`
4. Add tests

**Files:**
- `src/Drivers/Symfony/SymfonyRequestFactory.php`
- `src/Drivers/Laravel/LaravelRequestFactory.php`
- `src/Drivers/Guzzle/GuzzleRequestFactory.php`
- Tests for each

---

### Phase 3: Exception Mappers (2-3 days)
1. Create `SymfonyExceptionMapper`
2. Create `LaravelExceptionMapper`
3. Create `GuzzleExceptionMapper`
4. Add comprehensive tests

**Files:**
- `src/Drivers/Symfony/SymfonyExceptionMapper.php`
- `src/Drivers/Laravel/LaravelExceptionMapper.php`
- `src/Drivers/Guzzle/GuzzleExceptionMapper.php`
- Tests for each

---

### Phase 4: Refactor Drivers (3-4 days)
1. Refactor SymfonyDriver
2. Refactor GuzzleDriver
3. Refactor LaravelDriver
4. Refactor CurlNewDriver
5. Update all tests

---

### Phase 5: Refactor Pools (2-3 days)
1. Update pools to use factories
2. Update pools to use mappers
3. Update pool tests

---

### Phase 6: Deprecate Old CurlDriver (1 day)
1. Add @deprecated annotations
2. Update documentation
3. Migration guide

---

## Benefits Summary

### 1. Code Quality
- ✅ **60% code reduction** in drivers
- ✅ **Zero duplication** of common patterns
- ✅ **Single responsibility** per component
- ✅ **Testability** - isolated testing

### 2. Maintainability
- ✅ **Consistent behavior** across all drivers
- ✅ **Centralized logic** - change once, apply everywhere
- ✅ **Shared factories** - DRY between driver and pool

### 3. Extensibility
- ✅ **Easy new drivers** - extend AbstractDriver, implement 5 methods
- ✅ **Framework leverage** - factories encapsulate framework logic
- ✅ **Clean separation** - exception mapping isolated

---

## Recommendations

### Immediate (High Priority)
1. ✅ Create AbstractDriver
2. ✅ Extract factories
3. ✅ Create exception mappers

### Short-term (Medium Priority)
4. ✅ Refactor drivers
5. ✅ Update pools
6. ✅ Deprecate old CurlDriver

### Long-term (Low Priority)
7. ⚠️ Driver benchmarks
8. ⚠️ Architecture documentation
9. ⚠️ Consider trait alternative

---

## Conclusion

**CurlNewDriver demonstrates the ideal architecture:**
- Modular components (Factory, Parser, Handle, Adapter)
- Zero duplication (factory shared with pool)
- Clear separation of concerns
- Minimal driver code (orchestration only)

**Other drivers should adopt:**
1. AbstractDriver base class (template method pattern)
2. Factory pattern (reusable configuration)
3. Exception mapper pattern (centralized translation)

**Expected outcomes:**
- 60% code reduction
- ~365 lines duplication eliminated
- Consistent behavior
- Easier to add new drivers
- Better testability

The refactoring maintains full framework leverage while standardizing common patterns.
