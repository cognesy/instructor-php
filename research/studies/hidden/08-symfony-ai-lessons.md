# Symfony AI - Lessons for Polyglot

## Executive Summary

Symfony AI demonstrates **enterprise-grade architecture for scaling to 26+ providers**. Its standout contribution is the consistent Bridge structure - every provider implements the same interfaces with the same file organization, making new providers trivially addable. The Platform dispatcher with pre/post event hooks enables middleware-like extensibility (caching, logging, routing) without modifying core code. DeferredResult's lazy evaluation avoids parsing JSON until actually accessed, critical for performance in pipelines where results may not be used. For Polyglot, Symfony AI proves that framework patterns (normalizers, events, decorators) can create a highly extensible foundation.

**Key Takeaway**: The consistent Bridge structure is the key to scaling provider support. Every provider follows an identical pattern - 2-3 interfaces to implement, same file organization. This makes adding new providers a copy-paste-customize operation.

---

## Architectural Patterns Worth Adopting

### 1. DeferredResult Lazy Evaluation

**Problem it solves**: Parsing JSON responses is expensive. In pipelines, not all results are used. Eager parsing wastes CPU.

**How it works**:
```php
class DeferredResult implements ResultInterface {
    private bool $isConverted = false;
    private ?ConvertedResult $result = null;

    public function __construct(
        private readonly RawHttpResult $rawResult,
        private readonly ResultConverterInterface $converter,
    ) {}

    // Lazy access methods - conversion happens on first call
    public function asText(): string {
        $this->convertOnce();
        return $this->result->getText();
    }

    public function asArray(): array {
        $this->convertOnce();
        return $this->result->getContent();
    }

    public function getUsage(): Usage {
        $this->convertOnce();
        return $this->result->getUsage();
    }

    public function getRawResponse(): ResponseInterface {
        // Can access raw without conversion
        return $this->rawResult->getResponse();
    }

    private function convertOnce(): void {
        if ($this->isConverted) {
            return;  // Already converted
        }

        // Expensive operation: JSON parsing, validation, transformation
        $this->result = $this->converter->convert($this->rawResult);
        $this->isConverted = true;
    }
}

// Usage: conversion only happens when needed
$result = $platform->invoke($model, $input);

// Fast path - no conversion yet
if ($this->cache->has($key)) {
    return $this->cache->get($key);
}

// Only now does conversion happen
$text = $result->asText();  // Triggers convertOnce()
```

**Why it's powerful**:
- No wasted parsing for unused results
- Raw response always available
- Single conversion regardless of access pattern
- Memory efficient for pipelines

**Polyglot adaptation**:
```php
class LazyResponse implements LLMResponseInterface {
    private bool $parsed = false;
    private ?ParsedResponse $parsed = null;

    public function __construct(
        private readonly HttpResponse $rawResponse,
        private readonly ResponseParser $parser,
    ) {}

    public function getContent(): string {
        return $this->parsed()->content;
    }

    public function getToolCalls(): array {
        return $this->parsed()->toolCalls;
    }

    public function getUsage(): TokenUsage {
        return $this->parsed()->usage;
    }

    // Raw access without parsing
    public function getRawBody(): string {
        return $this->rawResponse->getBody()->getContents();
    }

    public function getStatusCode(): int {
        return $this->rawResponse->getStatusCode();
    }

    public function getHeaders(): array {
        return $this->rawResponse->getHeaders();
    }

    private function parsed(): ParsedResponse {
        if (!$this->parsed) {
            $this->parsed = true;
            $this->parsed = $this->parser->parse($this->rawResponse);
        }
        return $this->parsed;
    }
}

// Usage in pipeline
$responses = $pool->execute($requests);

// Filter without parsing
$successful = array_filter(
    $responses,
    fn($r) => $r->getStatusCode() === 200
);

// Only parse the ones we need
foreach ($successful as $response) {
    $content = $response->getContent();  // Now parsing happens
}
```

**Priority**: LOW - Performance optimization for specific use cases

---

### 2. Model-Aware Normalizer Stacking

**Problem it solves**: Different providers (and different models within providers) need different message formats. Generic normalizers miss edge cases.

**How it works**:
```php
// Normalizer interface
interface MessageNormalizerInterface {
    public function supportsNormalization(mixed $data, string $format, array $context): bool;
    public function normalize(mixed $data, string $format, array $context): array;
}

// Generic normalizer (always registered)
class UserMessageNormalizer implements MessageNormalizerInterface {
    public function supportsNormalization(mixed $data, string $format, array $context): bool {
        return $data instanceof UserMessage;
    }

    public function normalize(mixed $data, string $format, array $context): array {
        return ['role' => 'user', 'content' => $data->content];
    }
}

// Provider-specific normalizer (prepended, checked first)
class AnthropicUserMessageNormalizer implements MessageNormalizerInterface {
    public function supportsNormalization(mixed $data, string $format, array $context): bool {
        // Only for Anthropic models
        $model = $context[Contract::CONTEXT_MODEL] ?? null;
        return $data instanceof UserMessage && $model instanceof Claude;
    }

    public function normalize(mixed $data, string $format, array $context): array {
        // Anthropic-specific format with cache control
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $data->content,
                    'cache_control' => $data->cacheControl,
                ],
            ],
        ];
    }
}

// Stacking: provider-specific prepended, checked first
class ContractFactory {
    public function create(string $provider): NormalizerInterface {
        $normalizers = $this->genericNormalizers();

        // Prepend provider-specific (checked first)
        match ($provider) {
            'anthropic' => array_unshift(
                $normalizers,
                new AnthropicUserMessageNormalizer(),
                new AnthropicAssistantMessageNormalizer(),
            ),
            'openai' => array_unshift(
                $normalizers,
                new OpenAIImageMessageNormalizer(),
            ),
            default => null,
        };

        return new ChainNormalizer($normalizers);
    }
}

// Model passed in context for model-specific logic
$normalized = $contract->normalize($message, 'json', [
    Contract::CONTEXT_MODEL => $model,  // Claude, GPT4, etc.
]);
```

**Why it's powerful**:
- Provider-specific normalizers handle edge cases
- Generic fallback for common patterns
- Model passed in context for fine-grained control
- Easy to add new normalizers without modifying existing

**Polyglot adaptation**:
```php
// Message formatter interface
interface MessageFormatterInterface {
    public function supports(Message $message, string $provider, ?string $model): bool;
    public function format(Message $message, array $context): array;
}

// Generic formatter
class GenericUserFormatter implements MessageFormatterInterface {
    public function supports(Message $message, string $provider, ?string $model): bool {
        return $message instanceof UserMessage;
    }

    public function format(Message $message, array $context): array {
        return ['role' => 'user', 'content' => $message->content];
    }
}

// Anthropic-specific with cache control
class AnthropicUserFormatter implements MessageFormatterInterface {
    public function supports(Message $message, string $provider, ?string $model): bool {
        return $message instanceof UserMessage && $provider === 'anthropic';
    }

    public function format(Message $message, array $context): array {
        $content = [['type' => 'text', 'text' => $message->content]];

        if ($message->cacheControl !== null) {
            $content[0]['cache_control'] = ['type' => $message->cacheControl];
        }

        return ['role' => 'user', 'content' => $content];
    }
}

// Formatter chain with priority
class MessageFormatterChain {
    /** @var MessageFormatterInterface[] */
    private array $formatters = [];

    public function prepend(MessageFormatterInterface $formatter): self {
        array_unshift($this->formatters, $formatter);
        return $this;
    }

    public function append(MessageFormatterInterface $formatter): self {
        $this->formatters[] = $formatter;
        return $this;
    }

    public function format(Message $message, string $provider, ?string $model): array {
        foreach ($this->formatters as $formatter) {
            if ($formatter->supports($message, $provider, $model)) {
                return $formatter->format($message, [
                    'provider' => $provider,
                    'model' => $model,
                ]);
            }
        }

        throw new UnsupportedMessageException(get_class($message));
    }
}
```

**Priority**: MEDIUM - Improves provider-specific customization

---

### 3. Platform Dispatcher with Event Hooks

**Problem it solves**: Adding cross-cutting concerns (caching, logging, metrics, routing) requires modifying core code.

**How it works**:
```php
class Platform implements PlatformInterface {
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly iterable $clients,
    ) {}

    public function invoke(Model $model, mixed $input, array $options = []): DeferredResult {
        // PRE-HOOK: Allows mutation of model/input
        $preEvent = new PreInvocationEvent($model, $input, $options);
        $this->dispatcher->dispatch($preEvent);

        // Event may have changed model (e.g., routing, fallback)
        $model = $preEvent->getModel();
        $input = $preEvent->getInput();

        // Find appropriate client
        $client = $this->findClient($model);

        // Make request
        $rawResult = $client->request($model, $this->normalize($input));

        // Create deferred result
        $result = new DeferredResult($rawResult, $this->converter);

        // POST-HOOK: Allows wrapping/enriching result
        $postEvent = new PostInvocationEvent($model, $input, $result);
        $this->dispatcher->dispatch($postEvent);

        return $postEvent->getResult();  // May have been wrapped
    }
}

// Pre-hook: Model routing based on content
class ModelRoutingSubscriber implements EventSubscriberInterface {
    public function onPreInvocation(PreInvocationEvent $event): void {
        $input = $event->getInput();

        // Route long prompts to cheaper model
        if (strlen($input) > 100000) {
            $event->setModel(new Claude('claude-3-haiku'));
        }
    }
}

// Post-hook: Caching
class CachingSubscriber implements EventSubscriberInterface {
    public function onPostInvocation(PostInvocationEvent $event): void {
        $key = $this->cacheKey($event->getInput());

        if ($cached = $this->cache->get($key)) {
            $event->setResult($cached);
            return;
        }

        $result = $event->getResult();
        $this->cache->set($key, $result, 3600);
    }
}

// Post-hook: Metrics
class MetricsSubscriber implements EventSubscriberInterface {
    public function onPostInvocation(PostInvocationEvent $event): void {
        $usage = $event->getResult()->getUsage();

        $this->metrics->increment('llm.requests.total');
        $this->metrics->gauge('llm.tokens.prompt', $usage->promptTokens);
        $this->metrics->gauge('llm.tokens.completion', $usage->completionTokens);
    }
}
```

**Why it's powerful**:
- Core code never changes for cross-cutting concerns
- Pre-hooks can reroute, modify, or short-circuit
- Post-hooks can wrap, cache, or observe
- Easy to add/remove functionality via subscribers

**Polyglot adaptation**:
```php
// Event definitions
readonly class BeforeRequestEvent {
    public function __construct(
        public LLMRequest $request,
        public bool $cancelled = false,
        public ?LLMResponse $earlyResponse = null,
    ) {}

    public function cancel(?LLMResponse $response = null): void {
        $this->cancelled = true;
        $this->earlyResponse = $response;
    }
}

readonly class AfterRequestEvent {
    public function __construct(
        public LLMRequest $request,
        public LLMResponse $response,
    ) {}
}

// Event dispatcher in core
class LLMClient {
    public function __construct(
        private readonly EventDispatcherInterface $events,
        private readonly DriverInterface $driver,
    ) {}

    public function request(LLMRequest $request): LLMResponse {
        // Pre-hook
        $before = new BeforeRequestEvent($request);
        $this->events->dispatch($before);

        if ($before->cancelled) {
            return $before->earlyResponse;  // Cache hit, etc.
        }

        $request = $before->request;  // May have been modified

        // Execute
        $response = $this->driver->execute($request);

        // Post-hook
        $after = new AfterRequestEvent($request, $response);
        $this->events->dispatch($after);

        return $after->response;  // May have been wrapped
    }
}

// Subscriber: Caching
class CacheSubscriber {
    public function onBeforeRequest(BeforeRequestEvent $event): void {
        $key = $this->cacheKey($event->request);
        if ($cached = $this->cache->get($key)) {
            $event->cancel($cached);
        }
    }

    public function onAfterRequest(AfterRequestEvent $event): void {
        if ($event->response->isCacheable()) {
            $key = $this->cacheKey($event->request);
            $this->cache->set($key, $event->response);
        }
    }
}

// Subscriber: Request logging
class LoggingSubscriber {
    public function onBeforeRequest(BeforeRequestEvent $event): void {
        $this->logger->info('LLM request starting', [
            'provider' => $event->request->provider,
            'model' => $event->request->model,
        ]);
    }

    public function onAfterRequest(AfterRequestEvent $event): void {
        $this->logger->info('LLM request completed', [
            'tokens' => $event->response->usage->total(),
            'latency_ms' => $event->response->latencyMs,
        ]);
    }
}
```

**Priority**: HIGH - Enables extensibility without core changes

---

### 4. Consistent Bridge Structure

**Problem it solves**: Each provider implemented differently makes codebase hard to navigate and new providers hard to add.

**How it works**:
```
// Every provider follows identical structure
Bridge/
├── Anthropic/
│   ├── Claude.php              # Model class (provider + model info)
│   ├── ModelClient.php         # Request handler (implements ModelClientInterface)
│   ├── ResultConverter.php     # Response parser (implements ResultConverterInterface)
│   ├── TokenUsageExtractor.php # Usage extraction (optional)
│   ├── PlatformFactory.php     # Factory for DI
│   └── Contract/
│       └── AnthropicContract.php  # Normalizer stack
├── OpenAI/
│   ├── GPT.php
│   ├── ModelClient.php
│   ├── ResultConverter.php
│   ├── TokenUsageExtractor.php
│   ├── PlatformFactory.php
│   └── Contract/
│       └── OpenAIContract.php
├── Google/
│   ├── Gemini.php
│   ├── ModelClient.php
│   ├── ResultConverter.php
│   └── ...
└── ... (26+ providers total)

// Interfaces are minimal
interface ModelClientInterface {
    public function supports(Model $model): bool;
    public function request(Model $model, array $payload, array $options): RawHttpResult;
}

interface ResultConverterInterface {
    public function convert(RawHttpResult $result, array $options): ConvertedResult;
    public function convertStream(RawHttpResult $result, array $options): Generator;
}

// Adding new provider = copy existing bridge, customize
// 1. Create Model class (provider metadata)
// 2. Implement ModelClient (HTTP request format)
// 3. Implement ResultConverter (response parsing)
// 4. Optionally add normalizers for special message types
```

**Why it's powerful**:
- Predictable location for any functionality
- New providers follow proven template
- Code review is consistent
- Testing patterns are reusable

**Polyglot adaptation**:
```
// Consistent driver structure
Drivers/
├── Anthropic/
│   ├── AnthropicDriver.php     # Main entry point
│   ├── RequestBuilder.php      # Build API request
│   ├── ResponseParser.php      # Parse API response
│   ├── StreamParser.php        # Parse streaming response
│   ├── MessageFormatter.php    # Format messages
│   └── Config.php              # Provider configuration
├── OpenAI/
│   ├── OpenAIDriver.php
│   ├── RequestBuilder.php
│   ├── ResponseParser.php
│   ├── StreamParser.php
│   ├── MessageFormatter.php
│   └── Config.php
└── Google/
    └── ...

// Each driver implements same interface
interface DriverInterface {
    public function supports(string $provider): bool;
    public function chat(LLMRequest $request): LLMResponse;
    public function stream(LLMRequest $request): Generator;
}

// Request builder interface
interface RequestBuilderInterface {
    public function build(LLMRequest $request): array;
    public function endpoint(LLMRequest $request): string;
}

// Response parser interface
interface ResponseParserInterface {
    public function parse(HttpResponse $response): LLMResponse;
}

// Adding new provider checklist:
// [ ] Create XxxDriver implementing DriverInterface
// [ ] Create RequestBuilder for API format
// [ ] Create ResponseParser for response format
// [ ] Create StreamParser for streaming format
// [ ] Create MessageFormatter for message format
// [ ] Add to DriverRegistry
```

**Priority**: MEDIUM - Improves maintainability and scalability

---

### 5. EventSourceHttpClient Streaming Robustness

**Problem it solves**: Real-world SSE streams are messy - malformed JSON, multi-object chunks, framework overhead.

**How it works**:
```php
class StreamResultConverter implements ResultConverterInterface {
    public function convertStream(RawHttpResult $result, array $options): Generator {
        $response = $result->getResponse();
        $client = $this->httpClient;

        foreach ($client->stream($response) as $chunk) {
            // Skip framework overhead chunks
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $content = $chunk->getContent();

            // Handle empty chunks
            if (trim($content) === '') {
                continue;
            }

            // Handle SSE format: "data: {...}"
            foreach (explode("\n", $content) as $line) {
                $line = trim($line);

                // Skip empty lines and comments
                if ($line === '' || str_starts_with($line, ':')) {
                    continue;
                }

                // Extract data from "data: {...}"
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);

                    // Handle [DONE] marker
                    if ($data === '[DONE]') {
                        return;
                    }

                    // Handle malformed JSON - some providers send multiple objects
                    $data = trim($data, '[]');

                    try {
                        yield json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        // Log but don't fail - sometimes providers send garbage
                        $this->logger?->warning('Malformed JSON in stream', [
                            'data' => $data,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }
}

// Provider-specific delta extraction
class AnthropicResultConverter extends StreamResultConverter {
    protected function extractDelta(array $data): ?string {
        // Anthropic format
        if ($data['type'] !== 'content_block_delta') {
            return null;
        }

        return $data['delta']['text'] ?? null;
    }
}

class OpenAIResultConverter extends StreamResultConverter {
    protected function extractDelta(array $data): ?string {
        // OpenAI format
        return $data['choices'][0]['delta']['content'] ?? null;
    }
}
```

**Why it's powerful**:
- Handles real-world stream messiness
- Framework overhead filtered out
- Malformed JSON logged but not fatal
- Provider-specific delta extraction

**Polyglot adaptation**:
```php
class SSEParser {
    public function parse(string $rawChunk): iterable {
        foreach (explode("\n", $rawChunk) as $line) {
            $line = trim($line);

            // Skip empty and comments
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            // Parse SSE format
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    yield new StreamDone();
                    return;
                }

                yield from $this->parseJson($data);
            }
        }
    }

    private function parseJson(string $data): iterable {
        // Handle provider quirks
        $data = trim($data, '[]');  // Some providers wrap in array

        try {
            yield json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // Try splitting multi-object chunks
            foreach (explode('}{', $data) as $i => $part) {
                $json = $i === 0 ? $part . '}' : '{' . $part;
                try {
                    yield json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    // Truly malformed - skip
                }
            }
        }
    }
}

class RobustStreamHandler {
    public function __construct(
        private readonly SSEParser $parser,
        private readonly LoggerInterface $logger,
    ) {}

    public function stream(HttpResponse $response): Generator {
        foreach ($this->readChunks($response) as $chunk) {
            foreach ($this->parser->parse($chunk) as $parsed) {
                if ($parsed instanceof StreamDone) {
                    return;
                }

                yield $parsed;
            }
        }
    }

    private function readChunks(HttpResponse $response): Generator {
        $body = $response->getBody();

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }
}
```

**Priority**: LOW - Already handled, but patterns are useful

---

### 6. Cache Decorator Pattern

**Problem it solves**: Adding caching requires modifying platform code.

**How it works**:
```php
// Decorator wraps platform
class CachedPlatform implements PlatformInterface {
    public function __construct(
        private readonly PlatformInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {}

    public function invoke(Model $model, mixed $input, array $options = []): DeferredResult {
        $key = $this->cacheKey($model, $input, $options);

        // Check cache
        if ($cached = $this->cache->get($key)) {
            // Enrich metadata to indicate cache hit
            $cached->getMetadata()->set('cached', true);
            $cached->getMetadata()->set('cache_key', $key);
            return $cached;
        }

        // Execute actual request
        $result = $this->inner->invoke($model, $input, $options);

        // Cache the result
        $this->cache->set($key, $result, $this->ttl);

        return $result;
    }

    private function cacheKey(Model $model, mixed $input, array $options): string {
        return md5(serialize([
            'model' => (string) $model,
            'input' => $input,
            'options' => $options,
        ]));
    }
}

// Usage with tag-based invalidation
class TaggedCachedPlatform extends CachedPlatform {
    public function invoke(Model $model, mixed $input, array $options = []): DeferredResult {
        $key = $this->cacheKey($model, $input, $options);
        $tags = ['llm', "model:{$model->getName()}", "provider:{$model->getProvider()}"];

        if ($cached = $this->cache->get($key)) {
            return $this->enrichCached($cached, $key);
        }

        $result = $this->inner->invoke($model, $input, $options);

        $this->cache->set($key, $result, $this->ttl, $tags);

        return $result;
    }

    // Invalidate by model
    public function invalidateModel(string $modelName): void {
        $this->cache->invalidateTags(["model:{$modelName}"]);
    }

    // Invalidate by provider
    public function invalidateProvider(string $provider): void {
        $this->cache->invalidateTags(["provider:{$provider}"]);
    }
}
```

**Why it's powerful**:
- No core code changes
- Decorator is composable
- Tag-based invalidation
- Metadata enrichment for debugging

**Polyglot adaptation**:
```php
interface LLMClientInterface {
    public function request(LLMRequest $request): LLMResponse;
}

class CachingClient implements LLMClientInterface {
    public function __construct(
        private readonly LLMClientInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function request(LLMRequest $request): LLMResponse {
        // Skip cache for non-deterministic requests
        if ($request->temperature > 0) {
            return $this->inner->request($request);
        }

        $key = $this->cacheKey($request);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached->withMetadata([
                'cached' => true,
                'cache_key' => $key,
            ]);
        }

        $response = $this->inner->request($request);

        $this->cache->set($key, $response, $this->ttlSeconds);

        return $response;
    }

    private function cacheKey(LLMRequest $request): string {
        return 'llm:' . md5(json_encode([
            'provider' => $request->provider,
            'model' => $request->model,
            'messages' => $request->messages->toArray(),
            'tools' => array_map(fn($t) => $t->getName(), $request->tools),
        ]));
    }
}

// Compose decorators
$client = new LoggingClient(
    new CachingClient(
        new RetryingClient(
            new BaseClient($driver)
        )
    )
);
```

**Priority**: LOW - Nice pattern but not essential

---

## Specific Code Patterns

### Pattern: Convert-Once Guard

```php
private function convertOnce(): void {
    if ($this->isConverted) {
        return;  // Guard clause
    }

    $this->result = $this->converter->convert($this->rawResult);
    $this->isConverted = true;
}
```

### Pattern: Normalizer Chain with Priority

```php
// Provider-specific prepended (checked first)
array_unshift($normalizers, new AnthropicNormalizer());

// Generic appended (fallback)
$normalizers[] = new GenericNormalizer();

// First match wins
foreach ($normalizers as $normalizer) {
    if ($normalizer->supports($data, $context)) {
        return $normalizer->normalize($data, $context);
    }
}
```

### Pattern: Event Allows Mutation

```php
// Pre-event allows changing request
$event = new PreInvocationEvent($model, $input);
$this->dispatch($event);
$model = $event->getModel();  // May have changed

// Post-event allows replacing result
$event = new PostInvocationEvent($result);
$this->dispatch($event);
return $event->getResult();  // May have been wrapped
```

---

## DX Improvements

1. **Predictable Structure**: Same files in every provider directory
2. **Copy-Paste Providers**: New provider = copy existing, customize
3. **Decorator Composition**: Add features by wrapping
4. **Event Extension Points**: Customize without modifying core
5. **Lazy Evaluation**: Fast code paths stay fast

---

## What NOT to Copy

### 1. Symfony Dependency
Symfony AI is tightly coupled to Symfony framework (EventDispatcher, Serializer, HttpClient). Polyglot should be framework-agnostic.

### 2. Complex Normalizer System
Symfony's normalizer system is powerful but complex. For Polyglot, simpler MessageFormatter chain may be sufficient.

### 3. No Self-Correcting Output
Like Prism, Symfony AI doesn't feed validation errors back to LLM. NeuronAI's approach is superior.

### 4. No Step Tracking
Symfony AI doesn't track multi-turn tool loops as steps. Prism's ResponseBuilder is better.

---

## Implementation Roadmap for Polyglot

### Phase 1: Event System (2-3 days)
1. Define BeforeRequest/AfterRequest events
2. Add dispatcher to core client
3. Implement CacheSubscriber
4. Implement LoggingSubscriber

### Phase 2: Consistent Driver Structure (3-5 days)
1. Define standard interfaces
2. Reorganize existing drivers
3. Document new-provider checklist
4. Create driver template

### Phase 3: Message Formatter Chain (2-3 days)
1. Define formatter interface
2. Implement generic formatters
3. Add provider-specific formatters
4. Wire up chain with priority

### Phase 4: Lazy Response (1-2 days)
1. Create LazyResponse wrapper
2. Implement convert-once pattern
3. Update response accessors
4. Add raw access methods

---

## Key Metrics to Track

After implementing Symfony AI patterns, measure:

| Metric | Before | Target |
|--------|--------|--------|
| Provider implementation time | 2 days | 0.5 days |
| Files to modify for new feature | 5-10 | 0 (use subscriber) |
| Code duplication across providers | High | Low |
| Response parsing (unused results) | Always | Never |

---

## Summary: Top 3 Takeaways

1. **Consistent Bridge structure enables scale** - Every provider follows identical pattern. Makes 26+ providers manageable.

2. **Event hooks enable extension without modification** - Pre/post hooks allow caching, logging, routing without changing core code.

3. **Lazy evaluation prevents wasted work** - DeferredResult only parses when accessed. Critical for pipeline performance.
