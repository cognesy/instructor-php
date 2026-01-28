# PRD: Model Middleware

**Priority**: P1
**Impact**: Medium
**Effort**: Medium
**Status**: Proposed

## Problem Statement

instructor-php lacks a middleware system for wrapping model calls. Cross-cutting concerns like caching, logging, rate limiting, and parameter transformation must be implemented in observers or drivers, leading to:
1. Scattered implementation across codebase
2. Difficult composition of multiple concerns
3. No standard interception points for model calls

## Current State

```php
// Current: Direct driver usage
$driver = new ToolCallingDriver(
    llm: $llmProvider,
    model: 'gpt-4o',
);

// Cross-cutting concerns require observer
class LoggingObserver extends PassThroughObserver {
    public function beforeStep(AgentState $state): AgentState {
        $this->logger->info('Step starting', [...]);
        return $state;
    }
}

// Or custom driver wrapper
class CachingDriver implements CanUseTools {
    public function __construct(
        private CanUseTools $inner,
        private CacheInterface $cache,
    ) {}

    public function useTools(...): AgentStep {
        $key = $this->cacheKey(...);
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }
        $result = $this->inner->useTools(...);
        $this->cache->set($key, $result);
        return $result;
    }
}
```

**Limitations**:
1. Each concern requires custom wrapper class
2. Composition order is manual and error-prone
3. No access to raw LLM request/response
4. Cannot modify parameters before LLM call

## Proposed Solution

### Middleware Interface

```php
interface InferenceMiddleware {
    /**
     * Transform parameters before the call.
     */
    public function transformParams(
        InferenceParams $params,
        InferenceContext $context
    ): InferenceParams;

    /**
     * Wrap the generate (non-streaming) call.
     */
    public function wrapGenerate(
        InferenceParams $params,
        callable $next,
        InferenceContext $context
    ): InferenceResponse;

    /**
     * Wrap the streaming call.
     */
    public function wrapStream(
        InferenceParams $params,
        callable $next,
        InferenceContext $context
    ): iterable;
}

class InferenceParams {
    public function __construct(
        public readonly Messages $messages,
        public readonly array $tools,
        public readonly string $model,
        public readonly array $options,
        public readonly ?string $toolChoice = null,
    ) {}

    public function with(
        ?Messages $messages = null,
        ?array $tools = null,
        ?string $model = null,
        ?array $options = null,
    ): self { ... }
}

class InferenceContext {
    public function __construct(
        public readonly LLMProvider $provider,
        public readonly string $operation,  // 'generate' | 'stream'
    ) {}
}
```

### Middleware Stack

```php
class MiddlewareStack {
    /** @var InferenceMiddleware[] */
    private array $middleware = [];

    public function __construct(InferenceMiddleware ...$middleware) {
        $this->middleware = $middleware;
    }

    public function push(InferenceMiddleware $middleware): self {
        return new self(...[...$this->middleware, $middleware]);
    }

    public function execute(
        InferenceParams $params,
        callable $handler,
        InferenceContext $context
    ): InferenceResponse {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($params) =>
                $middleware->wrapGenerate($params, $next, $context),
            $handler
        );

        // Transform params through all middleware
        foreach ($this->middleware as $middleware) {
            $params = $middleware->transformParams($params, $context);
        }

        return $pipeline($params);
    }
}
```

### Wrapped LLM Provider

```php
class MiddlewareWrappedProvider implements LLMProviderInterface {
    public function __construct(
        private LLMProvider $inner,
        private MiddlewareStack $middleware,
    ) {}

    public static function wrap(
        LLMProvider $provider,
        InferenceMiddleware ...$middleware
    ): self {
        return new self($provider, new MiddlewareStack(...$middleware));
    }

    public function generate(InferenceParams $params): InferenceResponse {
        return $this->middleware->execute(
            $params,
            fn($p) => $this->inner->generate($p),
            new InferenceContext($this->inner, 'generate')
        );
    }
}
```

## Built-in Middleware

### LoggingMiddleware

```php
class LoggingMiddleware implements InferenceMiddleware {
    public function __construct(
        private LoggerInterface $logger,
        private bool $logInputs = true,
        private bool $logOutputs = true,
    ) {}

    public function transformParams(InferenceParams $params, InferenceContext $ctx): InferenceParams {
        if ($this->logInputs) {
            $this->logger->info('LLM Request', [
                'model' => $params->model,
                'messages' => $params->messages->count(),
                'tools' => count($params->tools),
            ]);
        }
        return $params;
    }

    public function wrapGenerate(InferenceParams $params, callable $next, InferenceContext $ctx): InferenceResponse {
        $start = microtime(true);

        try {
            $response = $next($params);

            if ($this->logOutputs) {
                $this->logger->info('LLM Response', [
                    'duration_ms' => (microtime(true) - $start) * 1000,
                    'finish_reason' => $response->finishReason()?->value,
                    'tokens' => $response->usage()->total(),
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('LLM Error', [
                'error' => $e->getMessage(),
                'duration_ms' => (microtime(true) - $start) * 1000,
            ]);
            throw $e;
        }
    }

    public function wrapStream(...): iterable { ... }
}
```

### CachingMiddleware

```php
class CachingMiddleware implements InferenceMiddleware {
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 3600,
    ) {}

    public function transformParams(InferenceParams $params, InferenceContext $ctx): InferenceParams {
        return $params;
    }

    public function wrapGenerate(InferenceParams $params, callable $next, InferenceContext $ctx): InferenceResponse {
        $key = $this->cacheKey($params);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return InferenceResponse::fromArray($cached);
        }

        $response = $next($params);

        // Only cache successful, non-tool-call responses
        if ($response->finishReason() === InferenceFinishReason::Stop) {
            $this->cache->set($key, $response->toArray(), $this->ttl);
        }

        return $response;
    }

    private function cacheKey(InferenceParams $params): string {
        return 'inference:' . hash('sha256', serialize([
            $params->model,
            $params->messages->toArray(),
            $params->tools,
            $params->options,
        ]));
    }

    public function wrapStream(...): iterable {
        // Streaming typically not cached
        return $next($params);
    }
}
```

### RateLimitMiddleware

```php
class RateLimitMiddleware implements InferenceMiddleware {
    public function __construct(
        private RateLimiter $limiter,
        private string $key = 'default',
    ) {}

    public function transformParams(InferenceParams $params, InferenceContext $ctx): InferenceParams {
        return $params;
    }

    public function wrapGenerate(InferenceParams $params, callable $next, InferenceContext $ctx): InferenceResponse {
        $this->limiter->acquire($this->key);

        try {
            return $next($params);
        } finally {
            $this->limiter->release($this->key);
        }
    }

    public function wrapStream(...): iterable { ... }
}
```

### DefaultSettingsMiddleware

```php
class DefaultSettingsMiddleware implements InferenceMiddleware {
    public function __construct(
        private ?float $temperature = null,
        private ?int $maxTokens = null,
        private array $defaultOptions = [],
    ) {}

    public function transformParams(InferenceParams $params, InferenceContext $ctx): InferenceParams {
        $options = array_merge($this->defaultOptions, $params->options);

        if ($this->temperature !== null && !isset($options['temperature'])) {
            $options['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null && !isset($options['max_tokens'])) {
            $options['max_tokens'] = $this->maxTokens;
        }

        return $params->with(options: $options);
    }

    public function wrapGenerate(InferenceParams $params, callable $next, InferenceContext $ctx): InferenceResponse {
        return $next($params);
    }

    public function wrapStream(...): iterable {
        return $next($params);
    }
}
```

## How Other Libraries Implement This

### Vercel AI SDK

**Location**: `packages/ai/src/middleware/wrap-language-model.ts`

```typescript
type LanguageModelMiddleware = {
    transformParams?: (options: {
        params: LanguageModelV3CallOptions;
        type: 'generate' | 'stream';
    }) => LanguageModelV3CallOptions | PromiseLike<LanguageModelV3CallOptions>;

    wrapGenerate?: (options: {
        doGenerate: () => PromiseLike<LanguageModelV3GenerateResult>;
        params: LanguageModelV3CallOptions;
    }) => PromiseLike<LanguageModelV3GenerateResult>;

    wrapStream?: (options: {
        doStream: () => PromiseLike<LanguageModelV3StreamResult>;
        params: LanguageModelV3CallOptions;
    }) => PromiseLike<LanguageModelV3StreamResult>;
};

// Usage
const wrappedModel = wrapLanguageModel({
    model: openai('gpt-4o'),
    middleware: [
        loggingMiddleware,
        cachingMiddleware,
        rateLimitMiddleware,
    ],
});

// Example middleware
const extractReasoningMiddleware: LanguageModelMiddleware = {
    wrapGenerate: async ({ doGenerate }) => {
        const result = await doGenerate();

        // Extract <thinking> blocks from text
        const content = result.content.flatMap(part => {
            if (part.type !== 'text') return [part];

            const match = part.text.match(/<thinking>([\s\S]*?)<\/thinking>/);
            if (!match) return [part];

            return [
                { type: 'reasoning', text: match[1] },
                { type: 'text', text: part.text.replace(/<thinking>[\s\S]*?<\/thinking>/, '') },
            ];
        });

        return { ...result, content };
    },
};
```

**Key Implementation Details**:
1. Three hooks: `transformParams`, `wrapGenerate`, `wrapStream`
2. Middleware applied in order
3. Access to both params and result
4. Can modify, replace, or extend behavior

### LangChain

**Location**: `langchain/callbacks/manager.py`

```python
# Callback-based middleware pattern
class CustomCallbackHandler(BaseCallbackHandler):
    def on_llm_start(self, serialized, prompts, **kwargs):
        self.start_time = time.time()

    def on_llm_end(self, response, **kwargs):
        duration = time.time() - self.start_time
        self.logger.info(f"LLM call took {duration}s")

# Usage
llm = ChatOpenAI(callbacks=[CustomCallbackHandler()])
```

### OpenAI SDK Pattern

```python
# Interceptor pattern
class LoggingInterceptor:
    def __init__(self, client):
        self.client = client

    def chat_completions_create(self, **kwargs):
        print(f"Request: {kwargs}")
        response = self.client.chat.completions.create(**kwargs)
        print(f"Response: {response}")
        return response
```

## Implementation Considerations

### Driver Integration

```php
class ToolCallingDriver implements CanUseTools {
    private ?MiddlewareStack $middleware;

    public function __construct(
        LLMProvider $llm,
        ?MiddlewareStack $middleware = null,
        // ...existing params
    ) {
        $this->middleware = $middleware;
    }

    public function withMiddleware(InferenceMiddleware ...$middleware): self {
        $clone = clone $this;
        $clone->middleware = new MiddlewareStack(...$middleware);
        return $clone;
    }

    private function executeInference(InferenceParams $params): InferenceResponse {
        if ($this->middleware === null) {
            return $this->llm->generate($params);
        }

        return $this->middleware->execute(
            $params,
            fn($p) => $this->llm->generate($p),
            new InferenceContext($this->llm, 'generate')
        );
    }
}
```

### Composition with Observers

```php
// Middleware operates at LLM level
// Observers operate at Agent level
// They complement each other

$driver = (new ToolCallingDriver($llm))
    ->withMiddleware(
        new LoggingMiddleware($logger),      // LLM request/response
        new CachingMiddleware($cache),        // LLM caching
        new RateLimitMiddleware($limiter),    // LLM rate limiting
    );

$agent = (new Agent($driver, ...))
    ->with(observer: new CompositeObserver(
        new AuditObserver($auditLog),         // Tool execution audit
        new MetricsObserver($metrics),         // Agent-level metrics
    ));
```

### Factory Pattern

```php
class MiddlewareFactory {
    public static function production(): MiddlewareStack {
        return new MiddlewareStack(
            new DefaultSettingsMiddleware(temperature: 0.7),
            new RateLimitMiddleware(RequestsPerMinute::create(60)),
            new LoggingMiddleware(Log::channel('llm')),
            new RetryMiddleware(maxRetries: 3),
        );
    }

    public static function development(): MiddlewareStack {
        return new MiddlewareStack(
            new LoggingMiddleware(Log::channel('llm'), logInputs: true, logOutputs: true),
        );
    }
}
```

## Migration Path

1. **Phase 1**: Define `InferenceMiddleware` interface
2. **Phase 2**: Implement `MiddlewareStack` composition
3. **Phase 3**: Add middleware support to drivers
4. **Phase 4**: Create built-in middleware (logging, caching, rate limit)
5. **Phase 5**: Add factory helpers for common configurations

## Success Metrics

- [ ] Middleware can intercept all LLM calls
- [ ] Multiple middleware compose correctly
- [ ] Both generate and stream supported
- [ ] Parameters modifiable before call
- [ ] Response modifiable after call
- [ ] No performance regression without middleware

## Open Questions

1. Should middleware be on LLMProvider or Driver?
2. How to handle streaming with transforming middleware?
3. Should we support async middleware (promises)?
4. How to share context between middleware?
