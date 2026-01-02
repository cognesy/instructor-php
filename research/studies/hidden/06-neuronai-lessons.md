# NeuronAI - Lessons for Polyglot

## Executive Summary

NeuronAI demonstrates that **simplicity and pragmatism can outperform over-engineered solutions**. Its standout contribution is a self-correcting structured output loop that feeds validation errors back to the LLM, dramatically improving success rates without complex retry logic. The library's trait-based composition eliminates inheritance hell while maintaining clear separation of concerns. For Polyglot, NeuronAI proves that async-first design and fine-grained observability are achievable without sacrificing code clarity.

**Key Takeaway**: The most impactful pattern is injecting validation errors into the conversation for LLM self-correction. This single technique can transform Polyglot's structured output reliability.

---

## Architectural Patterns Worth Adopting

### 1. Self-Correcting Structured Output Loop

**Problem it solves**: JSON schema validation failures require expensive retries that don't learn from previous attempts.

**How it works**:
```php
// NeuronAI pattern: Error → Format → Inject → Retry
$error = '';
do {
    if (trim($error) !== '') {
        // KEY INSIGHT: LLM sees its own error and self-corrects
        $correctionMessage = new UserMessage(
            "There was a problem: " . $error .
            "\nTry to generate correct JSON based on schema."
        );
        $this->addToChatHistory($correctionMessage);
    }

    try {
        $response = $this->makeApiCall();
        $validated = $this->validate($response);
        return $validated;
    } catch (ValidationException $ex) {
        $error = $ex->getMessage();
        $maxRetries--;
    }
} while ($maxRetries >= 0);
```

**Why it's powerful**:
- LLM has context from previous attempt
- Error message guides correction
- No wasted tokens re-explaining schema
- Dramatically higher success rate on complex schemas

**Polyglot adaptation**:
```php
// In StructuredOutputHandler or ResponseDeserializer
class SelfCorrectingDeserializer {
    public function deserialize(string $json, Schema $schema, int $maxRetries = 3): object {
        $error = null;
        $messages = [];

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($error !== null) {
                $messages[] = Message::user(
                    "JSON validation failed: {$error}\n\n" .
                    "Please fix the JSON to match the schema. Return ONLY valid JSON."
                );
            }

            $result = $this->attemptDeserialize($json, $schema);

            if ($result->isSuccess()) {
                return $result->value();
            }

            $error = $result->error()->getMessage();
        }

        throw new DeserializationException("Failed after {$maxRetries} attempts: {$error}");
    }
}
```

**Priority**: HIGH - Immediate, high-impact improvement

---

### 2. Trait Composition Architecture

**Problem it solves**: Deep inheritance hierarchies make code hard to understand, test, and extend.

**How it works**:
```
// NeuronAI Provider structure - zero inheritance
Provider uses:
├── HasGuzzleClient      # Infrastructure: HTTP client management
├── HandleChat           # Domain: Regular chat inference
├── HandleStream         # Domain: Streaming responses
├── HandleStructured     # Domain: Structured output
└── HandleWithTools      # Domain: Tool calling

// Each trait is independent - no inter-trait dependencies
trait HandleChat {
    // Uses $this->client from HasGuzzleClient
    // Uses $this->messageMapper() - lazy loaded
    public function chat(array $messages): Message {
        $payload = $this->messageMapper()->toRequest($messages);
        return $this->client->post('/chat', $payload);
    }
}

trait HandleStructured {
    // Completely independent implementation
    public function structured(array $messages, Schema $schema): object {
        $payload = $this->buildStructuredPayload($messages, $schema);
        return $this->client->post('/chat', $payload);
    }
}
```

**Why it's powerful**:
- Each capability = one trait
- Traits are composable: pick what you need
- Easy to add new providers: just implement traits
- File sizes stay small (50-200 lines per trait)
- Testing is isolated per trait

**Polyglot adaptation**:
```php
// Instead of abstract base classes, use traits
trait HasHttpClient {
    protected ?HttpClient $httpClient = null;

    public function httpClient(): HttpClient {
        return $this->httpClient ??= $this->createHttpClient();
    }
}

trait CanChat {
    public function chat(Messages $messages): LLMResponse {
        return $this->httpClient()->post(
            $this->chatEndpoint(),
            $this->buildChatPayload($messages)
        );
    }

    abstract protected function chatEndpoint(): string;
    abstract protected function buildChatPayload(Messages $messages): array;
}

trait CanStream {
    public function stream(Messages $messages): Generator {
        yield from $this->httpClient()->stream(
            $this->streamEndpoint(),
            $this->buildStreamPayload($messages)
        );
    }
}

// Provider composition
final class AnthropicDriver {
    use HasHttpClient;
    use CanChat;
    use CanStream;
    use CanStructuredOutput;
    use CanToolCall;
}
```

**Priority**: MEDIUM - Architectural improvement for maintainability

---

### 3. Observable Pattern with Typed Events

**Problem it solves**: printf debugging and ad-hoc logging make production observability inconsistent and unreliable.

**How it works**:
```php
// NeuronAI: 50+ typed event constants
trait Observable {
    /** @var array<string, Observer[]> */
    protected array $observers = [];

    public function observe(string $event, Observer $observer): self {
        $this->observers[$event][] = $observer;
        return $this;
    }

    public function notify(string $event, mixed $data): void {
        foreach ($this->getEventObservers($event) as $observer) {
            $observer->update($this, $event, $data);
        }
    }
}

// Event types are specific and typed
class Events {
    public const EXTRACTING = 'extracting';
    public const EXTRACTED = 'extracted';
    public const DESERIALIZING = 'deserializing';
    public const DESERIALIZED = 'deserialized';
    public const VALIDATING = 'validating';
    public const VALIDATED = 'validated';
    public const TOOL_CALL_STARTED = 'tool_call_started';
    public const TOOL_CALL_COMPLETED = 'tool_call_completed';
    public const AGENT_ERROR = 'agent_error';
    // ... 40+ more events
}

// Usage
$agent->observe(Events::VALIDATING, new LoggingObserver());
$agent->observe(Events::AGENT_ERROR, new AlertingObserver());
```

**Why it's powerful**:
- Every significant operation emits an event
- Type-safe: IDE autocomplete for event names
- Observers are reusable across agents
- Production monitoring built-in
- Zero performance cost when no observers

**Polyglot adaptation**:
```php
// Define event enum for type safety
enum LLMEvent: string {
    case RequestStarted = 'request.started';
    case RequestCompleted = 'request.completed';
    case StreamChunkReceived = 'stream.chunk';
    case ToolCallStarted = 'tool.started';
    case ToolCallCompleted = 'tool.completed';
    case ValidationFailed = 'validation.failed';
    case RetryAttempted = 'retry.attempted';
    case RateLimitHit = 'rate_limit.hit';
}

// Typed event data objects
readonly class RequestStartedEvent {
    public function __construct(
        public string $provider,
        public string $model,
        public int $messageCount,
        public float $timestamp,
    ) {}
}

trait EmitsEvents {
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function on(LLMEvent $event, callable $listener): self {
        $this->listeners[$event->value][] = $listener;
        return $this;
    }

    protected function emit(LLMEvent $event, object $data): void {
        foreach ($this->listeners[$event->value] ?? [] as $listener) {
            $listener($data);
        }
    }
}
```

**Priority**: MEDIUM - Enables production observability

---

### 4. Async-First with Sync Wrapper

**Problem it solves**: Blocking I/O limits throughput; adding async later is expensive.

**How it works**:
```php
// NeuronAI: All sync methods wrap async
public function chat(array $messages): Message {
    return $this->chatAsync($messages)->wait();  // Sync = async + wait
}

public function chatAsync(array $messages): PromiseInterface {
    return $this->client->postAsync('/chat', $payload)
        ->then(function (ResponseInterface $response) {
            $message = $this->parseResponse($response);

            // Recursive promise for tool calls
            if ($message instanceof ToolCallMessage) {
                $result = $this->executeTool($message);
                return $this->chatAsync([...$messages, $result]);
            }

            return $message;
        });
}

// Enables natural concurrency
$promises = [
    $agent->chatAsync($messages1),
    $agent->chatAsync($messages2),
    $agent->chatAsync($messages3),
];

$responses = Utils::all($promises)->wait();
```

**Why it's powerful**:
- Sync API remains simple for common cases
- Async available for high-throughput scenarios
- Tool call loops are non-blocking
- Batch processing is trivial

**Polyglot adaptation**:
```php
// In PolyglotDriver base
trait AsyncCapable {
    public function chat(Messages $messages): LLMResponse {
        return $this->chatAsync($messages)->wait();
    }

    public function chatAsync(Messages $messages): PromiseInterface {
        return $this->httpClient->postAsync(
            $this->endpoint(),
            $this->buildPayload($messages)
        )->then(fn($response) => $this->parseResponse($response));
    }
}

// Request pool for batch processing
class RequestPool {
    /** @var PromiseInterface[] */
    private array $promises = [];

    public function add(callable $request): self {
        $this->promises[] = $request();
        return $this;
    }

    public function execute(): array {
        return Utils::all($this->promises)->wait();
    }
}
```

**Priority**: MEDIUM - Enables high-throughput use cases

---

### 5. Lazy Mapper Initialization

**Problem it solves**: Eagerly creating all service objects wastes resources on unused code paths.

**How it works**:
```php
// NeuronAI: Create only on first use
class Provider {
    private ?MessageMapperInterface $messageMapper = null;
    private ?ResponseParserInterface $responseParser = null;

    public function messageMapper(): MessageMapperInterface {
        return $this->messageMapper ??= new MessageMapper();
    }

    public function responseParser(): ResponseParserInterface {
        return $this->responseParser ??= new ResponseParser();
    }
}

// Benefits:
// 1. Constructor is fast
// 2. Unused mappers never created
// 3. Still get single instance (not recreated each call)
// 4. Easy to override in tests
```

**Why it's powerful**:
- Fast instantiation
- Memory efficient
- Code paths create only what they need
- Single instance per service

**Polyglot adaptation**: Already partially implemented, but could be more consistent across all lazy-loadable services.

**Priority**: LOW - Micro-optimization

---

### 6. Provider Adapter Traits per Feature

**Problem it solves**: Monolithic provider classes become unmaintainable.

**How it works**:
```
// NeuronAI file structure per provider
OpenAI/
├── HandleChat.php           # 50-80 lines
├── HandleStream.php         # 59-196 lines
├── HandleStructured.php     # 26-51 lines
├── HandleWithTools.php      # 60-100 lines
├── MessageMapper.php        # Format conversion
└── ResponseParser.php       # Response parsing

// Each file is small and focused
// Adding a new provider = copy structure, implement traits
```

**Why it's powerful**:
- Files under 200 lines
- Easy to find specific functionality
- New providers follow clear template
- Code review is tractable

**Polyglot adaptation**:
```
Drivers/Anthropic/
├── ChatHandler.php
├── StreamHandler.php
├── StructuredHandler.php
├── ToolHandler.php
├── MessageMapper.php
└── ResponseParser.php
```

**Priority**: LOW - Code organization improvement

---

## Specific Code Patterns

### Pattern: Fluent Error Message Formatting

```php
// NeuronAI builds clear correction prompts
$correctionPrompt = sprintf(
    "There was a problem: %s\n\n" .
    "The expected JSON schema is:\n%s\n\n" .
    "Please try again with valid JSON that matches the schema exactly.",
    $validationError,
    json_encode($schema, JSON_PRETTY_PRINT)
);
```

### Pattern: Single Responsibility Traits

```php
// Each trait does ONE thing
trait HandleChat {
    public function chat(array $messages): Message { /* ... */ }
    protected function buildChatPayload(array $messages): array { /* ... */ }
}

// NOT this:
trait HandleEverything {
    public function chat() { /* ... */ }
    public function stream() { /* ... */ }
    public function structured() { /* ... */ }
    public function tools() { /* ... */ }
}
```

### Pattern: Observer with Structured Data

```php
// Pass typed objects, not primitives
$this->notify(Events::TOOL_CALL_STARTED, new ToolCallEvent(
    name: $tool->getName(),
    arguments: $tool->getArguments(),
    timestamp: microtime(true),
));

// NOT this:
$this->notify('tool_call', ['name' => $name, 'args' => $args]);
```

---

## DX Improvements

1. **Clear Error Messages**: Validation errors include schema context
2. **Fluent API**: Method chaining for configuration
3. **Event Discovery**: IDE autocomplete for event constants
4. **Small Files**: Easy to navigate and understand
5. **Consistent Structure**: Same pattern across all providers

---

## What NOT to Copy

### 1. Limited Stream Events
NeuronAI's streaming only yields text content. No tool call streaming, no usage streaming. Prism's event-driven streaming is superior.

### 2. No State Accumulation
NeuronAI doesn't track streaming state for server-side aggregation. For SSE to frontend + backend accumulation, Prism's StreamState is better.

### 3. No Multi-Step Tracking
NeuronAI doesn't track tool call loops as discrete steps. Prism's ResponseBuilder is better for usage aggregation across turns.

### 4. Basic Exception Handling
NeuronAI throws generic exceptions. Prism's rich exception hierarchy with rate limit metadata is superior.

---

## Implementation Roadmap for Polyglot

### Phase 1: Self-Correcting Output (1-2 days)
1. Add error injection to structured output retry loop
2. Format validation errors clearly for LLM
3. Add conversation context to retry attempts
4. Measure success rate improvement

### Phase 2: Event System (2-3 days)
1. Define LLMEvent enum with all events
2. Create typed event data classes
3. Add EmitsEvents trait to core classes
4. Document event catalog

### Phase 3: Trait Reorganization (3-5 days)
1. Extract capabilities into traits
2. Reorganize driver structure
3. Document trait composition pattern
4. Update provider implementations

### Phase 4: Async-First (5-7 days)
1. Add async variants to driver interface
2. Implement promise-based tool loops
3. Add RequestPool for batch processing
4. Document concurrency patterns

---

## Key Metrics to Track

After implementing NeuronAI patterns, measure:

| Metric | Before | Target |
|--------|--------|--------|
| Structured output success rate | ~85% | >95% |
| Average retries per request | 0.8 | 0.2 |
| Provider implementation time | 2 days | 0.5 days |
| Lines of code per provider | 800 | 300 |
| Event coverage | 0% | >80% |

---

## Summary: Top 3 Takeaways

1. **Self-correcting structured output is a must-have** - Inject validation errors into conversation for LLM self-correction. Highest ROI improvement.

2. **Trait composition beats inheritance** - Horizontal code reuse keeps files small, providers consistent, and tests isolated.

3. **Async-first enables future scale** - All sync methods should wrap async. Enables concurrent operations without API changes.
