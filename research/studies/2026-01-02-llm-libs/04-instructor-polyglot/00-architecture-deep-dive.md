# InstructorPHP Polyglot - Deep Architectural Analysis

## Executive Summary

InstructorPHP Polyglot is a sophisticated, production-grade LLM API abstraction layer with **far more depth than initially analyzed**. This document corrects the previous superficial analysis by documenting the full architecture.

## Core Architectural Strength: State Machine

The most sophisticated aspect of Polyglot is its **hierarchical state machine** for tracking inference lifecycle:

```
InferenceRequest (immutable data)
    ↓
Inference (facade for configuration)
    ↓
PendingInference (lazy execution wrapper)
    ↓
InferenceExecution (tracks multiple attempts + finalization)
    ↓
InferenceAttempt (tracks single attempt + errors + partials)
    ↓
InferenceResponse / PartialInferenceResponse (results)
```

### State Machine Components

#### 1. InferenceExecution (`Data/InferenceExecution.php`)
Tracks the **complete lifecycle** of an inference operation:

```php
class InferenceExecution {
    private InferenceRequest $request;
    private InferenceAttemptList $attempts;      // All attempts (for retry)
    private ?InferenceAttempt $currentAttempt;   // Active attempt
    private bool $isFinalized;

    // State transitions:
    public function withSuccessfulAttempt(InferenceResponse $response): self;
    public function withFailedAttempt(?InferenceResponse $response, ...): self;
    public function withNewPartialResponse(PartialInferenceResponse $partial): self;
    public function withFinalizedPartialResponse(): self;

    // Aggregated state:
    public function usage(): Usage;       // Sums across attempts
    public function errors(): array;      // Collects all errors
    public function isSuccessful(): bool;
    public function isFailed(): bool;
}
```

**Key Insight**: This supports **retry with error accumulation** - each attempt is tracked, usage is summed across retries, errors from all attempts are preserved.

#### 2. InferenceAttempt (`Data/InferenceAttempt.php`)
Tracks a **single inference attempt**:

```php
class InferenceAttempt {
    private ?InferenceResponse $response;
    private ?PartialInferenceResponse $accumulatedPartial;
    private array $errors;
    private ?bool $isFinalized;

    // Factory methods:
    public static function fromResponse(InferenceResponse $response): self;
    public static function fromFailedResponse(...$errors): self;
    public static function fromPartialResponses(PartialInferenceResponse $partial): self;

    // State queries:
    public function isFailed(): bool;
    public function usage(): Usage;  // From response or partial
}
```

**Key Insight**: Attempt tracks both full responses AND streaming partials, with proper error attribution.

---

## Messages Package: Complex Message System

### Rich Message Model (`packages/messages/`)

**NOT just simple arrays** - a full domain model for multi-modal messages:

```
Messages (collection)
└── Message (single message)
    ├── role: MessageRole (enum)
    ├── name: string
    ├── content: Content (value object)
    │   └── parts: ContentPart[] (array of parts)
    │       ├── type: string (text, image_url, input_audio, file)
    │       └── fields: array (type-specific data)
    └── metadata: Metadata (arbitrary key-value)
```

#### Key Features:

**1. Composite Content Support**
```php
class Content {
    public function isComposite(): bool;  // Multiple parts?
    public function addContentPart(ContentPart $part): static;
    public function normalized(): string|array;  // Auto-adapts to simple vs composite
}
```

**2. Multi-Modal Content Types**
```php
class ContentPart {
    public static function text(string $text): static;
    public static function imageUrl(string $url): static;
    public static function image(Image $image): static;
    public static function audio(Audio $audio): static;
    public static function file(File $file): static;
}
```

**3. Rich Message Operations**
```php
class Messages {
    // Role-based filtering:
    public function forRoles(array $roles): Messages;
    public function exceptRoles(array $roles): Messages;
    public function headWithRoles(array $roles): Messages;
    public function tailAfterRoles(array $roles): Messages;

    // Provider-specific transformations:
    public function toMergedPerRole(): Messages;  // For providers requiring alternating roles
    public function remapRoles(array $mapping): Messages;  // Developer → System mapping

    // Functional operations:
    public function map(callable $callback): array;
    public function filter(?callable $callback): Messages;
    public function reduce(callable $callback, mixed $initial): mixed;
}
```

**4. MessageStore - Sectioned Message Library**
```php
class MessageStore {
    public Sections $sections;
    public MessageStoreParameters $parameters;

    // Section-based access:
    public function section(string $name): SectionOperator;
    public function select(string|array $sections): MessageStore;
    public function toMessages(): Messages;  // Compile selected sections
}

class Section {
    public string $name;
    public Messages $messages;
    public function toMergedPerRole(): Section;
    public function appendContentField(string $key, mixed $value): Section;
}
```

**Use Case**: System prompts, cached context, chat history, summarized history - all as named sections that can be selectively compiled into final messages.

---

## ToolCalls System

### ToolCall Value Object (`Data/ToolCall.php`)
```php
final readonly class ToolCall {
    private string $id;
    private string $name;
    private array $arguments;  // Already parsed from JSON

    public function argsAsJson(): string;           // Re-serialize when needed
    public function toToolCallArray(): array;       // OpenAI format
    public function hasValue(string $key): bool;
    public function value(string $key, mixed $default): mixed;
}
```

### ToolCalls Collection (`Collections/ToolCalls.php`)
```php
final readonly class ToolCalls {
    // Construction:
    public static function fromArray(array $toolCalls): ToolCalls;
    public static function fromMapper(array $data, callable $mapper): ToolCalls;

    // Streaming support:
    public function withAddedToolCall(string $name, array $args): ToolCalls;
    public function withLastToolCallUpdated(string $name, string $json): ToolCalls;

    // Query:
    public function hasSingle(): bool;
    public function hasMany(): bool;

    // Functional:
    public function map(callable $callback): array;
    public function filter(callable $callback): ToolCalls;
    public function reduce(callable $callback, mixed $initial): mixed;
}
```

---

## Usage Tracking: Production-Grade Token Accounting

### Usage Value Object (`Data/Usage.php`)
```php
class Usage {
    public int $inputTokens;
    public int $outputTokens;
    public int $cacheWriteTokens;   // Anthropic cache writes
    public int $cacheReadTokens;    // Anthropic cache reads
    public int $reasoningTokens;    // o1/o3 reasoning tokens

    public function total(): int;
    public function input(): int;
    public function output(): int;  // Includes reasoning tokens
    public function cache(): int;   // Sum of cache read + write

    // Accumulation with overflow protection:
    public function accumulate(Usage $usage): self;
    public function withAccumulated(Usage $usage): self;

    private function safeAdd(int $a, int $b, string $fieldName): int {
        $maxReasonableTokens = 1_000_000;
        if ($a > $maxReasonableTokens || $b > $maxReasonableTokens) {
            throw new InvalidArgumentException("Unrealistic token count...");
        }
        return $a + $b;
    }
}
```

**Key Insight**: Overflow protection prevents token count bugs from corrupting accounting. Supports all modern token types including cache and reasoning tokens.

### Streaming Usage Accumulation
```php
class PartialInferenceResponse {
    public ?Usage $usage;
    private bool $usageIsCumulative;

    private function accumulateUsage(Usage $previous, Usage $current, bool $isCumulative): Usage {
        // Cumulative mode (Anthropic): Take max of each field
        if ($isCumulative) {
            return new Usage(
                inputTokens: max($previous->inputTokens, $current->inputTokens),
                outputTokens: max($previous->outputTokens, $current->outputTokens),
                // ...
            );
        }
        // Delta mode (OpenAI): Sum values
        return $current->withAccumulated($previous);
    }
}
```

**Key Insight**: Handles provider differences in how usage is reported during streaming (cumulative vs delta).

---

## Capability Modeling Foundation

### BodyFormat Capability Methods

Each provider's BodyFormat declares its capabilities:

```php
// OpenAIBodyFormat
protected function supportsToolSelection(InferenceRequest $request): bool {
    return true;
}
protected function supportsStructuredOutput(InferenceRequest $request): bool {
    return true;
}
protected function supportsAlternatingRoles(InferenceRequest $request): bool {
    return true;
}
protected function supportsNonTextResponseForTools(InferenceRequest $request): bool {
    return true;
}

// GeminiBodyFormat
protected function supportsNonTextResponseForTools(InferenceRequest $request): bool {
    return false;  // Gemini can't do JSON mode + tools simultaneously
}
```

### Request-Aware Capabilities
Capabilities can depend on the request itself:
```php
protected function toResponseFormat(InferenceRequest $request): array {
    if (!$this->supportsStructuredOutput($request)) {
        return [];
    }
    // ... build response format
}
```

**Future Direction**: This could evolve into a full capability model where tools, response formats, etc. are validated against provider capabilities before request submission.

---

## Streaming Architecture

### InferenceStream (`Streaming/InferenceStream.php`)

**NOT just a simple Generator wrapper** - provides functional operations AND state tracking:

```php
class InferenceStream {
    protected InferenceExecution $execution;  // State tracker
    protected iterable $stream;                // Raw partial responses
    protected ?Closure $onPartialResponse;     // Callback

    // Functional operations:
    public function responses(): Generator;
    public function map(callable $mapper): iterable;
    public function reduce(callable $reducer, mixed $initial): mixed;
    public function filter(callable $filter): iterable;
    public function all(): array;

    // State-aware finalization:
    public function final(): ?InferenceResponse {
        if (!$this->execution->isFinalized()) {
            // Drain stream to ensure all deltas processed
            foreach ($this->makePartialResponses($this->stream) as $_) {}
        }
        return $this->execution->response();
    }

    // Internal: enrich each partial with accumulated state
    private function makePartialResponses(iterable $stream): Generator {
        $priorResponse = PartialInferenceResponse::empty();
        foreach ($stream as $partialResponse) {
            $partialResponse = $partialResponse->withAccumulatedContent($priorResponse);
            $this->notifyOnPartialResponse($partialResponse);
            yield $partialResponse;
            $priorResponse = $partialResponse;
        }
        $this->finalizeStream();
    }
}
```

### PartialInferenceResponse Accumulation

Sophisticated tool call reconstruction across streaming deltas:

```php
class PartialInferenceResponse {
    // Internal state for tool accumulation:
    private array $tools = [];      // Keys: "id:<toolId>" or "name:<name>#<n>"
    private int $toolsCount = 0;
    private string $lastToolKey = '';

    public function withAccumulatedContent(PartialInferenceResponse $previous): self {
        // Content accumulation
        $this->content = $previous->content() . ($this->contentDelta ?? '');

        // Usage accumulation (cumulative vs delta aware)
        $this->usage = $this->accumulateUsage($previous->usage(), $this->usage(), $isCumulative);

        // Tool call reconstruction
        $this->tools = $previous->tools;
        if ($hasToolDelta) {
            $key = $this->resolveToolKey($this->toolId, $this->toolName);
            // Create or update tool entry
            // Handles: missing IDs (Gemini), interleaved deltas, late name arrival
        }
        return $this;
    }

    // Lazy materialization - only when needed
    public function toolCalls(): ToolCalls {
        return ToolCalls::fromArray(array_values($this->tools));
    }
}
```

**Key Insight**: Tool calls are tracked as raw arrays during streaming (memory efficient), then converted to `ToolCalls` collection only on access. Handles edge cases like missing tool IDs (Gemini) and interleaved tool deltas.

---

## Finish Reason Normalization

### InferenceFinishReason Enum
```php
enum InferenceFinishReason: string {
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool_calls';
    case ContentFilter = 'content_filter';
    case Error = 'error';
    case Other = 'other';

    public static function fromText(string $text): InferenceFinishReason {
        $text = strtolower($text);
        return match ($text) {
            'blocklist' => self::ContentFilter,
            'complete' => self::Stop,
            'error' => self::Error,
            'finish_reason_unspecified' => self::Other,
            'language' => self::ContentFilter,
            'length' => self::Length,
            'malformed_function_call' => self::Error,
            'max_tokens' => self::Length,
            'model_length' => self::Length,
            'other' => self::Other,
            'prohibited_content' => self::ContentFilter,
            'recitation' => self::ContentFilter,
            'safety' => self::ContentFilter,
            'spii' => self::ContentFilter,
            'stop' => self::Stop,
            'stop_sequence' => self::Stop,
            'tool_call' => self::ToolCalls,
            'tool_calls' => self::ToolCalls,
            'tool_use' => self::ToolCalls,
            default => self::Other,
        };
    }
}
```

**Key Insight**: Maps 20+ provider-specific finish reason strings to 6 normalized cases. Handles OpenAI, Anthropic, Gemini, Cohere, and more.

---

## HTTP Request Pool: Parallel Inference Execution

### Overview

The `http-client` package provides a **production-grade concurrent request pooling system** that enables parallel LLM API calls - a feature not found in NeuronAI, Prism, or Symfony AI.

### Core Components

**1. PendingHttpPool (`PendingHttpPool.php`)**
```php
final readonly class PendingHttpPool {
    public function __construct(
        private HttpRequestList $requests,
        private CanHandleRequestPool $poolHandler,
    ) {}

    public function all(?int $maxConcurrent = null): HttpResponseList {
        return $this->poolHandler->pool($this->requests, $maxConcurrent);
    }
}
```

**2. Typed Collections**

`HttpRequestList` - Immutable request collection:
```php
$requests = HttpRequestList::of(
    new HttpRequest('https://api.openai.com/...', 'POST', $headers, $body, []),
    new HttpRequest('https://api.anthropic.com/...', 'POST', $headers, $body, []),
);

// Functional operations
$filtered = $requests->filter(fn($r) => $r->method() === 'POST');
$newList = $requests->withAppended($additionalRequest);
```

`HttpResponseList` - Result monad collection:
```php
$results = $client->pool($requests, maxConcurrent: 3);

// Query methods
$results->hasFailures();      // bool
$results->hasSuccesses();     // bool
$results->successCount();     // int
$results->failureCount();     // int

// Extract results
$successful = $results->successful();  // HttpResponse[]
$errors = $results->failed();          // Exception[]

// Functional operations
$mapped = $results->map(fn($r) => json_decode($r->unwrap()->body()));
$filtered = $results->filter(fn($r) => $r->isSuccess());
```

**3. Driver-Agnostic Pool Implementations**

Each HTTP driver provides optimized concurrent execution:

| Driver | Implementation | Concurrency Strategy |
|--------|---------------|---------------------|
| **Guzzle** | `GuzzlePool` | Native Promise-based pooling |
| **Symfony** | `SymfonyPool` | Streaming responses |
| **Laravel** | `LaravelPool` | Batched execution |
| **CurlNew** | `CurlPool` | curl_multi native |

```php
// GuzzlePool implementation
public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
    $pool = new Pool($this->client,
        $this->createRequestGenerator($requests->all())(),
        [
            'concurrency' => $maxConcurrent ?? $this->config->maxConcurrent,
            'fulfilled' => fn($response, $index) => $responses[$index] = Result::success(...),
            'rejected' => fn($reason, $index) => $responses[$index] = Result::failure(...),
        ]
    );

    $pool->promise()->wait(unwrap: true);
    return HttpResponseList::fromArray($responses);
}
```

### Use Cases

**1. Mixture of Experts**
```php
$requests = HttpRequestList::of(
    buildOpenAIRequest($prompt, 'gpt-4o'),
    buildAnthropicRequest($prompt, 'claude-3-opus'),
    buildGeminiRequest($prompt, 'gemini-pro'),
);

$results = $client->pool($requests, maxConcurrent: 3);

// Aggregate responses from all models
foreach ($results->successful() as $response) {
    $aggregated[] = json_decode($response->body(), true);
}
```

**2. Batch Processing**
```php
$requests = HttpRequestList::fromArray(
    array_map(
        fn($doc) => buildEmbeddingRequest($doc),
        $documents
    )
);

// Process 100 documents with 10 concurrent requests
$results = $client->pool($requests, maxConcurrent: 10);
```

**3. Fan-out / Fan-in Pattern**
```php
// Fan-out: query multiple services
$results = $client->pool($serviceRequests, maxConcurrent: 5);

// Fan-in: aggregate successful responses
$aggregated = array_map(
    fn($r) => json_decode($r->body(), true),
    $results->successful()
);
```

### Key Features

1. **Result Monad**: Each response wrapped in `Result<HttpResponse>` - failures don't stop the pool
2. **Graceful Degradation**: Pool continues even if some requests fail
3. **Configurable Concurrency**: Per-pool `maxConcurrent` limit
4. **Memory Efficient**: Process large batches in chunks
5. **Retry Support**: Collect failed requests for retry

```php
// Retry failed requests
$retriable = HttpRequestList::empty();
foreach ($results->all() as $result) {
    if ($result->isFailure() && $result->error()->isRetriable()) {
        $retriable = $retriable->withAppended($result->error()->getRequest());
    }
}
$retryResults = $client->pool($retriable, maxConcurrent: 2);
```

### Unique to InstructorPHP

**No other analyzed library has this capability:**
- **NeuronAI**: No parallel request support
- **Prism**: Sequential only
- **Symfony AI**: No pooling

This makes InstructorPHP uniquely suited for:
- Multi-model consensus
- Batch embedding generation
- Parallel structured extraction
- High-throughput inference pipelines

---

## MessageStore: Sectioned Context Management

### Overview

**NOT just a message list** - MessageStore is a **library of named message sections** for managing complex LLM context with dynamic inclusion/exclusion.

### Core Architecture

```
MessageStore
├── Sections (collection)
│   ├── Section "system" → Messages
│   ├── Section "prompt" → Messages
│   ├── Section "examples" → Messages
│   ├── Section "chat" → Messages
│   └── Section "summary" → Messages
└── MessageStoreParameters
```

### Key Components

**1. MessageStore (`MessageStore.php`)**
```php
final readonly class MessageStore {
    public Sections $sections;
    public MessageStoreParameters $parameters;

    // Select sections to compile
    public function select(string|array $sections = []): MessageStore;

    // Compile selected sections to Messages
    public function toMessages(): Messages;

    // Fluent section access
    public function section(string $name): SectionOperator;
}
```

**2. Section (`Section.php`)**
```php
final readonly class Section {
    public string $name;
    public Messages $messages;

    public function appendMessages(array|Messages $messages): Section;
    public function toMergedPerRole(): Section;  // For providers requiring alternating roles
    public function appendContentField(string $key, mixed $value): Section;  // Add cache_control, etc.
}
```

**3. SectionOperator - Fluent API**
```php
// Fluent section manipulation
$store->section('system')->get();
$store->section('prompt')->exists();
$store->section('examples')->isEmpty();
$store->section('system')->appendMessages($messages);
$store->section('prompt')->setMessages($messages);
$store->section('old')->remove();
$store->section('chat')->clear();
```

### Use Cases

**1. Dynamic Context Assembly**
```php
$store = MessageStore::empty()
    ->section('system')->setMessages(Messages::fromString('You are helpful.'))
    ->section('examples')->setMessages($fewShotExamples)
    ->section('chat')->setMessages($conversationHistory);

// Include/exclude based on context window
$compiledMessages = $store
    ->select(['system', 'examples', 'chat'])  // Include all
    ->toMessages();

// Or exclude examples to save tokens
$shortContext = $store
    ->select(['system', 'chat'])
    ->toMessages();
```

**2. Context Window Management**
```php
// Long conversation with summarization
$store = $store
    ->section('summary')->setMessages($summarizedHistory)
    ->section('chat')->setMessages($recentMessages);

// Use summary when context is full
$messages = match($tokenCount > $limit) {
    true => $store->select(['system', 'summary', 'chat'])->toMessages(),
    false => $store->select(['system', 'full_history', 'chat'])->toMessages(),
};
```

**3. Provider-Specific Transformations**
```php
// Merge consecutive messages for providers requiring alternating roles
$merged = $store->section('chat')->get()->toMergedPerRole();

// Add cache control to system section for Anthropic
$store = $store->section('system')->get()
    ->appendContentField('cache_control', ['type' => 'ephemeral']);
```

---

## Templates Package: Prompt Templating System

### Overview

Multi-engine prompt templating with **XML-based message structure** that renders directly to `Messages` or `MessageStore`.

### Supported Engines

| Engine | Syntax | Use Case |
|--------|--------|----------|
| **Twig** | `{{ variable }}`, `{% if %}` | Complex logic, Laravel-like |
| **Blade** | `{{ $variable }}`, `@if` | Laravel ecosystem |
| **ArrowPipe** | `<\|variable\|>` | Simple substitution, lightweight |

### XML-Based Message Structure

Templates can define structured messages using XML tags:

```twig
{# FrontMatter with metadata #}
{#
---
description: Find country capital
variables:
    country:
        description: country name
        type: string
        default: France
schema:
    name: capital
    properties:
        name:
            description: Capital of the country
            type: string
    required: [name]
---
#}
<chat>
    <message role="system">
        You are a helpful assistant.
        Respond with JSON: {{ json_schema }}
    </message>

    <section name="examples">
        <message role="user">What is the capital of France?</message>
        <message role="assistant">{"name": "Paris"}</message>
    </section>

    <message role="user">
        What is the capital of {{ country }}?
    </message>
</chat>
```

### Multi-Modal Content Support

```twig
<message role="user">
    <content type="text">Describe this image:</content>
    <content type="image">{{ image_url }}</content>
</message>

<message role="user">
    <content type="audio" format="mp3">{{ audio_data }}</content>
</message>
```

### Cache Control for Anthropic

```twig
<message role="user">
    <content cache="true">
        {{ long_context_to_cache }}
    </content>
</message>
```

### Template API

```php
// Load and render to Messages
$messages = Template::make('prompts/capital')
    ->withValues(['country' => 'Germany'])
    ->toMessages();

// Render to MessageStore (preserves sections)
$store = Template::make('prompts/complex')
    ->withValues($variables)
    ->toMessageStore();

// DSN syntax: preset:path
$messages = Template::text('twig:prompts/hello', ['name' => 'World']);

// Validate template variables
$errors = $template->validationErrors();
```

### FrontMatter Metadata

Templates support YAML frontmatter for:
- Variable definitions with types, descriptions, defaults
- JSON Schema for structured output
- Template description

```php
$info = $template->info();
$info->variables();      // ['country' => ['type' => 'string', ...]]
$info->schema();         // JSON schema definition
$info->variableNames();  // ['country']
```

### Integration with MessageStore

Templates render to MessageStore when sections are defined:
```php
$store = Template::make('prompts/with-sections')
    ->withValues($params)
    ->toMessageStore();

// Access individual sections
$examples = $store->section('examples')->messages();
```

---

## CachedContext: Prompt Caching Support

```php
class CachedContext {
    private Messages $messages;
    private array $tools;
    private string|array $toolChoice;
    private ResponseFormat $responseFormat;
}
```

Used by `InferenceRequest::withCacheApplied()` to merge cached context with request:
- Cached messages prepended
- Cached tools used if no request tools
- Cached tool choice used as fallback
- Cached response format as fallback

Provider-specific handling (Anthropic):
```php
// AnthropicBodyFormat::toSystemMessages()
$systemCached = Messages::fromAny($cachedMessages)
    ->headWithRoles([MessageRole::System, MessageRole::Developer]);
if (!$systemCached->isEmpty()) {
    $systemCached = $systemCached->appendContentField('cache_control', ['type' => 'ephemeral']);
}
```

---

## Comparison to Other Libraries

| Feature | NeuronAI | Prism | SymfonyAI | Polyglot |
|---------|----------|-------|-----------|----------|
| **State Machine** | Simple | ResponseBuilder | None | Full hierarchy |
| **Attempt Tracking** | Retry loop | None | None | InferenceAttemptList |
| **Usage Accumulation** | Basic | Sum | None | Overflow-safe, cumulative-aware |
| **Tool Reconstruction** | Simple | Event-based | None | Keyed accumulation |
| **Message Model** | Class + attachments | Value objects | Arrays | Full domain model with sections |
| **Capability Model** | None | None | None | BodyFormat methods |
| **Streaming Ops** | Generator | Events | Generator | map/reduce/filter |
| **Finish Reason** | Per-provider | Enum | String | Normalized enum |
| **Parallel Requests** | None | None | None | Full pool with Result monad |
| **Multi-Driver Pool** | N/A | N/A | N/A | Guzzle, Symfony, Laravel, Curl |
| **Sectioned Context** | None | None | None | MessageStore with dynamic inclusion |
| **Prompt Templates** | None | None | None | Twig/Blade/ArrowPipe + XML messages |

---

## Architectural Strengths

1. **Rich State Machine**: `InferenceExecution` → `InferenceAttempt` enables retry, error tracking, usage aggregation
2. **Domain-Driven Messages**: `Messages` → `Message` → `Content` → `ContentPart` with sections and rich operations
3. **Production-Grade Usage**: Overflow protection, cumulative vs delta handling, reasoning tokens
4. **Sophisticated Streaming**: Functional ops, lazy tool materialization, proper accumulation
5. **Capability Foundation**: BodyFormat methods ready for expansion
6. **Clean Separation**: Polyglot (API) vs Instructor (deserialization)
7. **Parallel Request Pool**: Unique among analyzed libraries - typed collections, Result monad, multi-driver support
8. **Driver-Agnostic Concurrency**: Guzzle/Symfony/Laravel/Curl pool implementations with consistent API
9. **MessageStore**: Sectioned context management with dynamic inclusion/exclusion, context window optimization
10. **Templates Package**: Multi-engine templating (Twig/Blade/ArrowPipe) with XML message structure, FrontMatter metadata

## Observability via Events System

### Architecture Overview

Polyglot uses a **PSR-14 compatible event dispatching** system for observability. The base `Event` class provides:

```php
class Event implements JsonSerializable {
    public readonly string $id;              // UUID per event
    public readonly DateTimeImmutable $createdAt;
    public mixed $data;                       // Flexible payload
    public string $logLevel = LogLevel::DEBUG;
}
```

### Inference Events (`packages/polyglot/src/Inference/Events/`)

#### Core Lifecycle Events

| Event | Payload | Dispatch Point |
|-------|---------|----------------|
| `InferenceRequested` | `['request' => $request->toArray()]` | `BaseInferenceDriver::toHttpRequest()` |
| `InferenceResponseCreated` | `['response' => $inferenceResponse->toArray()]` | `BaseInferenceDriver::httpResponseToInference()`, `InferenceStream::finalizeStream()` |
| `InferenceFailed` | Context-rich: `statusCode`, `headers`, `body`, `exception` | Multiple failure points in `BaseInferenceDriver` |
| `InferenceDriverBuilt` | `['driver' => $driverClass, 'config' => $config]` | `InferenceDriverFactory` |

#### Timing Events (NEW)

| Event | Key Properties | Dispatch Point |
|-------|----------------|----------------|
| `InferenceStarted` | `executionId`, `request`, `isStreamed`, `startedAt` | `PendingInference::response()` |
| `InferenceCompleted` | `executionId`, `isSuccess`, `finishReason`, `usage`, `durationMs`, `attemptCount` | `PendingInference::response()`, `InferenceStream::finalizeStream()` |

#### Token Usage Events (NEW)

| Event | Key Properties | Dispatch Point |
|-------|----------------|----------------|
| `InferenceUsageReported` | `executionId`, `usage` (full breakdown), `model`, `isFinal` | `PendingInference::response()`, `InferenceStream::finalizeStream()` |

#### Retry/Attempt Events (NEW)

| Event | Key Properties | Dispatch Point |
|-------|----------------|----------------|
| `InferenceAttemptStarted` | `executionId`, `attemptId`, `attemptNumber`, `model`, `isRetry` | `PendingInference::response()` |
| `InferenceAttemptSucceeded` | `executionId`, `attemptId`, `attemptNumber`, `finishReason`, `usage`, `durationMs` | `PendingInference::response()`, `InferenceStream::finalizeStream()` |
| `InferenceAttemptFailed` | `executionId`, `attemptId`, `attemptNumber`, `errorMessage`, `errorType`, `httpStatusCode`, `willRetry`, `durationMs` | `PendingInference::response()` |

#### Streaming Events

| Event | Key Properties | Dispatch Point |
|-------|----------------|----------------|
| `StreamFirstChunkReceived` | `executionId`, `timeToFirstChunkMs`, `model`, `initialContent` | `InferenceStream::makePartialResponses()` (first iteration) |
| `StreamEventReceived` | Raw SSE line string | `EventStreamReader` |
| `StreamEventParsed` | Processed event data | `EventStreamReader` |
| `PartialInferenceResponseCreated` | `PartialInferenceResponse` object | Streaming pipeline |

### Embeddings Events (`packages/polyglot/src/Embeddings/Events/`)

| Event | Payload | Dispatch Point |
|-------|---------|----------------|
| `EmbeddingsRequested` | `['request' => $request->toArray()]` | `BaseEmbedDriver::handle()` |
| `EmbeddingsResponseReceived` | `EmbeddingsResponse` | `PendingEmbeddings::response()` |
| `EmbeddingsFailed` | `['context', 'exception', 'request/response']` | `BaseEmbedDriver` error handlers |
| `EmbeddingsDriverBuilt` | `['driver', 'config']` | `EmbeddingsDriverFactory` |

### Subscribing to Events

```php
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;

$events = new EventDispatcher();

$events->addListener(InferenceRequested::class, function(InferenceRequested $event) {
    $logger->info('Inference requested', $event->data);
});

$events->addListener(InferenceFailed::class, function(InferenceFailed $event) {
    $logger->error('Inference failed', [
        'context' => $event->data['context'],
        'status' => $event->data['statusCode'] ?? null,
    ]);
});

$inference = new Inference(events: $events);
```

### Event Features

1. **UUID Tracking**: Each event has unique `$id` for distributed tracing
2. **Timestamps**: `$createdAt` for latency analysis
3. **Log Level**: Built-in severity for filtering (`$event->logLevel`)
4. **Serialization**: `JsonSerializable` + `toArray()` for export
5. **Console/Log Formatting**: `asLog()`, `asConsole()`, `print()` methods

### Latency Measurement Example

```php
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;

$events->addListener(InferenceStarted::class, function(InferenceStarted $event) {
    $metrics->recordStart($event->executionId, $event->startedAt);
});

$events->addListener(InferenceCompleted::class, function(InferenceCompleted $event) {
    $metrics->recordLatency($event->executionId, $event->durationMs);
    $metrics->recordSuccess($event->executionId, $event->isSuccess);
});
```

### Time-To-First-Chunk (TTFC) Measurement Example

```php
use Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived;

$events->addListener(StreamFirstChunkReceived::class, function(StreamFirstChunkReceived $event) {
    $metrics->recordTTFC(
        executionId: $event->executionId,
        model: $event->model,
        ttfcMs: $event->timeToFirstChunkMs,
    );

    // Log if TTFC exceeds threshold
    if ($event->timeToFirstChunkMs > 2000) {
        $logger->warning("Slow TTFC: {$event->timeToFirstChunkMs}ms", [
            'executionId' => $event->executionId,
            'model' => $event->model,
        ]);
    }
});
```

### Token Usage Tracking Example

```php
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;

$events->addListener(InferenceUsageReported::class, function(InferenceUsageReported $event) {
    $usage = $event->usage;
    $billing->recordTokens(
        model: $event->model,
        input: $usage->inputTokens,
        output: $usage->outputTokens,
        cacheRead: $usage->cacheReadTokens,
        cacheWrite: $usage->cacheWriteTokens,
        reasoning: $usage->reasoningTokens,
    );
});
```

### Retry Observability Example

```php
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;

$events->addListener(InferenceAttemptStarted::class, function(InferenceAttemptStarted $event) {
    if ($event->isRetry()) {
        $logger->warning("Retry attempt #{$event->attemptNumber}", [
            'executionId' => $event->executionId,
            'model' => $event->model,
        ]);
    }
});

$events->addListener(InferenceAttemptFailed::class, function(InferenceAttemptFailed $event) {
    $logger->error("Attempt #{$event->attemptNumber} failed", [
        'error' => $event->errorMessage,
        'errorType' => $event->errorType,
        'httpStatus' => $event->httpStatusCode,
        'willRetry' => $event->willRetry,
        'durationMs' => $event->durationMs,
    ]);
});
```

### Remaining Limitations

- **Retry coordination at higher level**: The `willRetry` flag is informational; actual retry logic is in Instructor layer
- **Streaming timing**: `InferenceStarted` is dispatched from `PendingInference`, not when stream is created via `stream()` method directly

---

## LLMProvider Design Analysis

### Current Architecture

`LLMProvider` is a **configuration resolver** that:
1. Resolves LLM configuration from presets/DSN/explicit config
2. Optionally carries an explicit inference driver
3. Implements `CanResolveLLMConfig` and `HasExplicitInferenceDriver`

```php
final class LLMProvider implements CanResolveLLMConfig, HasExplicitInferenceDriver
{
    // Dependencies
    private readonly CanHandleEvents $events;
    private readonly CanProvideConfig $configProvider;
    private ConfigPresets $presets;

    // Configuration sources (immutable-ish after construction)
    private ?string $dsn;
    private ?string $llmPreset;
    private ?array $configOverrides = null;
    private ?LLMConfig $explicitConfig;
    private ?CanHandleInference $explicitDriver;
}
```

### What Was Removed

The comment "HTTP client is no longer owned here (moved to facades)" indicates previous refactoring:
- HTTP client creation moved to `Inference` facade
- LLMProvider now focuses purely on config resolution

### Current Design Issues

**1. Mutable After Construction**
```php
public function withLLMPreset(string $preset): self {
    $this->llmPreset = $preset;  // Mutates internal state
    return $this;
}
```
The `with*` methods return `$this`, making the object mutable. For a config/value object, this should return a new instance.

**2. Mixed Responsibilities**
- Config resolution (good)
- Driver carrying (questionable) - the `explicitDriver` field makes it more than a config object

**3. Not Truly Readonly**
Only `$events` and `$configProvider` are `readonly`. Other fields can be modified via `with*` methods.

### Recommendation: Split Into Pure Value Object

**Option A: Immutable LLMProviderConfig (Value Object)**
```php
final readonly class LLMProviderConfig {
    public function __construct(
        public ?string $dsn = null,
        public ?string $preset = null,
        public ?array $overrides = null,
        public ?LLMConfig $explicitConfig = null,
    ) {}

    public function withPreset(string $preset): self {
        return new self($this->dsn, $preset, $this->overrides, $this->explicitConfig);
    }

    public function resolve(ConfigPresets $presets): LLMConfig {
        // Resolution logic here
    }
}
```

**Option B: Keep LLMProvider as Builder, Make Immutable**
```php
public function withLLMPreset(string $preset): self {
    $clone = clone $this;
    $clone->llmPreset = $preset;
    return $clone;
}
```

### Decision Recommendation

**Keep current design** with minor improvements:
1. The mutable pattern is intentional for fluent builder usage
2. Separating config from driver would require more refactoring
3. Current design works and is already improved from before

**If refactoring**: Make `with*` methods return clones for immutability.

---

## HTTP Pool for Parallel Inference

### Current State: Gap Between Layers

**HTTP Pool Exists** at `packages/http-client/`:
- `PendingHttpPool` - Deferred execution wrapper
- Driver implementations: `GuzzlePool`, `SymfonyPool`, `LaravelPool`, `CurlPool`, `ExtHttpPool`
- `CanHandleRequestPool` interface
- `HttpRequestList` / `HttpResponseList` typed collections

**BUT: No Polyglot Integration**

Searching for `pool|HttpPool|PendingHttpPool` in `packages/polyglot/` returns **no results**.

The `Inference` facade has no method for parallel requests:
```php
// What EXISTS:
$inference->with($messages)->response();  // Single request

// What DOESN'T EXIST:
$inference->pool([
    ['messages' => $msg1, 'model' => 'gpt-4'],
    ['messages' => $msg2, 'model' => 'claude-3'],
])->all();  // Parallel requests
```

### Architecture for Parallel Inference

**Required Components:**

1. **InferenceRequestList** - Collection of `InferenceRequest` objects
2. **InferenceResponseList** - Collection of `Result<InferenceResponse>`
3. **PendingInferencePool** - Deferred parallel execution
4. **ParallelInference** or **Inference::pool()** - Entry point

**Proposed API:**

```php
// Option 1: Fluent pool builder
$results = Inference::pool()
    ->add(Inference::new()->with($messages1)->using('openai:gpt-4'))
    ->add(Inference::new()->with($messages2)->using('anthropic:claude-3'))
    ->all(maxConcurrent: 3);

// Option 2: Array-based
$results = Inference::parallel([
    ['preset' => 'openai', 'messages' => $msg1],
    ['preset' => 'anthropic', 'messages' => $msg2],
], maxConcurrent: 3);

// Option 3: Request list
$requests = InferenceRequestList::of(
    InferenceRequest::for('openai:gpt-4')->withMessages($msg1),
    InferenceRequest::for('anthropic:claude-3')->withMessages($msg2),
);
$results = Inference::new()->pool($requests)->all();
```

### Implementation Strategy

**Phase 1: Request/Response Collections**
```php
// InferenceRequestList.php
final readonly class InferenceRequestList {
    public static function of(InferenceRequest ...$requests): self;
    public function all(): array;
    public function map(callable $fn): array;
}

// InferenceResponseList.php
final readonly class InferenceResponseList {
    /** @var array<Result<InferenceResponse>> */
    public function successful(): array;
    public function failed(): array;
    public function hasFailures(): bool;
}
```

**Phase 2: Pool Execution**
```php
// PendingInferencePool.php
final class PendingInferencePool {
    public function __construct(
        private InferenceRequestList $requests,
        private InferenceDriverFactory $factory,
        private HttpClient $httpClient,
    ) {}

    public function all(?int $maxConcurrent = null): InferenceResponseList {
        // Convert InferenceRequests → HttpRequests
        $httpRequests = $this->requests->map(fn($r) => $this->toHttp($r));

        // Execute via HTTP pool
        $httpResponses = $this->httpClient->pool(
            HttpRequestList::fromArray($httpRequests),
            $maxConcurrent
        );

        // Convert HttpResponses → InferenceResponses
        return $this->toInferenceResponses($httpResponses);
    }
}
```

**Phase 3: Facade Integration**
```php
class Inference {
    public function pool(InferenceRequestList $requests): PendingInferencePool {
        return new PendingInferencePool(
            $requests,
            $this->getInferenceFactory(),
            $this->makeHttpClient(),
        );
    }
}
```

### Challenges

1. **Driver Heterogeneity**: Different providers need different drivers - can't use single driver for pool
2. **Config Resolution**: Each request may need different config resolution
3. **Streaming**: Pool doesn't support streaming responses (HTTP constraint)
4. **Error Handling**: Need to handle partial failures gracefully

### Current Workaround

Users can manually use HTTP pool at lower level:
```php
$httpClient = (new HttpClientBuilder())->create();
$requests = HttpRequestList::of(
    $driver1->toHttpRequest($inferenceRequest1),
    $driver2->toHttpRequest($inferenceRequest2),
);
$responses = $httpClient->pool($requests)->all();
// Then manually convert responses
```

This is verbose and loses the Polyglot abstraction.

---

## Areas for Potential Enhancement

1. **Explicit Capability Model**: Promote `supportsX()` methods to first-class capability objects
2. **Retry in Polyglot**: Add optional retry middleware (currently must be external or in Instructor)
3. **Richer Exception Types**: Replace `RuntimeException` with typed exceptions
4. **Rate Limit Detection**: Explicit 429 handling with retry-after
5. **Validation Layer**: Request validation before sending to provider
6. **Parallel Inference API**: Expose HTTP pool at Polyglot level (see section above)
7. ~~**Timing Events**: Add `InferenceStarted`/`InferenceCompleted` events for latency observability~~ ✅ DONE
8. **LLMProvider Immutability**: Make `with*` methods return clones instead of mutating

### Recently Completed Enhancements

- **Timing Events** (`InferenceStarted`, `InferenceCompleted`): Now dispatched from `PendingInference` and `InferenceStream`
- **Token Usage Events** (`InferenceUsageReported`): Separate event with full token breakdown
- **Retry Observability Events** (`InferenceAttemptStarted`, `InferenceAttemptFailed`, `InferenceAttemptSucceeded`): Track individual attempts
- **TTFC Event** (`StreamFirstChunkReceived`): Measures time-to-first-chunk for streaming responses
- **Stats Mechanism** (`InferenceAttemptStats`, `InferenceExecutionStats`, `InferenceStatsCalculator`): Comprehensive stats calculation and reporting

---

## Inference Stats System

### Overview

The stats system provides **comprehensive metrics calculation and reporting** for inference operations. It consists of:

1. **Value Objects** - Immutable data structures for stats
2. **Calculator Service** - Computes stats from execution/attempt data
3. **Stats Events** - PSR-14 events for observability

### Stats Data Classes

#### InferenceAttemptStats (`Data/InferenceAttemptStats.php`)

Captures metrics for a **single inference attempt**:

```php
final readonly class InferenceAttemptStats {
    public function __construct(
        public string $executionId,
        public string $attemptId,
        public int $attemptNumber,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public float $durationMs,
        public ?float $timeToFirstChunkMs,     // TTFC for streaming
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        public int $cacheWriteTokens,
        public int $reasoningTokens,
        public bool $isSuccess,
        public ?string $finishReason,
        public ?string $errorMessage,
        public ?string $errorType,
        public ?string $model,
        public bool $isStreamed,
    ) {}

    // Computed metrics
    public function totalTokens(): int;
    public function durationSeconds(): float;
    public function outputTokensPerSecond(): float;  // Throughput
    public function totalTokensPerSecond(): float;   // Total throughput
}
```

#### InferenceExecutionStats (`Data/InferenceExecutionStats.php`)

Aggregates metrics across **all attempts** in an execution:

```php
final readonly class InferenceExecutionStats {
    /** @param InferenceAttemptStats[] $attemptStats */
    public function __construct(
        public string $executionId,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public float $totalDurationMs,
        public ?float $timeToFirstChunkMs,
        public int $attemptCount,
        public int $successfulAttempts,
        public int $failedAttempts,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public int $totalCacheReadTokens,
        public int $totalCacheWriteTokens,
        public int $totalReasoningTokens,
        public bool $isSuccess,
        public ?string $finishReason,
        public ?string $model,
        public bool $isStreamed,
        public array $attemptStats = [],  // Per-attempt breakdown
    ) {}

    // Computed metrics
    public function totalTokens(): int;
    public function durationSeconds(): float;
    public function outputTokensPerSecond(): float;
    public function totalTokensPerSecond(): float;
}
```

### Stats Calculator Service

`InferenceStatsCalculator` (`Stats/InferenceStatsCalculator.php`) computes stats from raw execution data:

```php
class InferenceStatsCalculator {
    // Calculate execution-level stats
    public function calculateExecutionStats(
        InferenceExecution $execution,
        DateTimeImmutable $startedAt,
        ?float $timeToFirstChunkMs = null,
        ?string $model = null,
        bool $isStreamed = false,
        array $attemptStats = [],
    ): InferenceExecutionStats;

    // Calculate attempt stats from response
    public function calculateAttemptStatsFromResponse(
        InferenceResponse $response,
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        ?float $timeToFirstChunkMs = null,
        ?string $model = null,
        bool $isStreamed = false,
    ): InferenceAttemptStats;

    // Calculate stats for failed attempts
    public function calculateFailedAttemptStats(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        Throwable $error,
        ?Usage $partialUsage = null,
        ?string $model = null,
        bool $isStreamed = false,
        ?float $timeToFirstChunkMs = null,
    ): InferenceAttemptStats;
}
```

### Stats Events

#### InferenceAttemptStatsReported

Dispatched when an attempt completes with calculated stats:

```php
final class InferenceAttemptStatsReported extends InferenceEvent {
    public function __construct(
        public readonly InferenceAttemptStats $stats,
    ) {}
}
```

#### InferenceExecutionStatsReported

Dispatched when an execution completes with aggregate stats:

```php
final class InferenceExecutionStatsReported extends InferenceEvent {
    public function __construct(
        public readonly InferenceExecutionStats $stats,
    ) {}
}
```

### Integration Points

Stats are calculated and events dispatched in:

1. **PendingInference** (non-streaming):
   - `handleAttemptSuccess()` → `InferenceAttemptStatsReported`
   - `handleAttemptFailure()` → `InferenceAttemptStatsReported`
   - `dispatchExecutionStats()` → `InferenceExecutionStatsReported`

2. **InferenceStream** (streaming):
   - `dispatchStreamCompletionEvents()` → Both stats events with TTFC

### Usage Examples

#### Metrics Collection

```php
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStatsReported;
use Cognesy\Polyglot\Inference\Events\InferenceExecutionStatsReported;

$events->addListener(InferenceAttemptStatsReported::class, function($event) {
    $stats = $event->stats;

    $metrics->record([
        'attempt_duration_ms' => $stats->durationMs,
        'ttfc_ms' => $stats->timeToFirstChunkMs,
        'input_tokens' => $stats->inputTokens,
        'output_tokens' => $stats->outputTokens,
        'tokens_per_second' => $stats->outputTokensPerSecond(),
        'model' => $stats->model,
        'success' => $stats->isSuccess,
    ]);
});

$events->addListener(InferenceExecutionStatsReported::class, function($event) {
    $stats = $event->stats;

    $metrics->recordExecution([
        'total_duration_ms' => $stats->totalDurationMs,
        'attempt_count' => $stats->attemptCount,
        'failed_attempts' => $stats->failedAttempts,
        'total_tokens' => $stats->totalTokens(),
        'throughput' => $stats->totalTokensPerSecond(),
    ]);
});
```

#### Throughput Monitoring

```php
$events->addListener(InferenceAttemptStatsReported::class, function($event) {
    $stats = $event->stats;

    // Alert on slow throughput
    if ($stats->outputTokensPerSecond() < 10.0) {
        $logger->warning("Slow inference throughput", [
            'model' => $stats->model,
            'tokens_per_second' => $stats->outputTokensPerSecond(),
            'duration_ms' => $stats->durationMs,
        ]);
    }
});
```

#### Retry Analysis

```php
$events->addListener(InferenceExecutionStatsReported::class, function($event) {
    $stats = $event->stats;

    if ($stats->failedAttempts > 0) {
        $logger->info("Execution completed with retries", [
            'total_attempts' => $stats->attemptCount,
            'failed' => $stats->failedAttempts,
            'successful' => $stats->successfulAttempts,
            'total_duration_ms' => $stats->totalDurationMs,
        ]);

        // Analyze per-attempt stats
        foreach ($stats->attemptStats as $attempt) {
            if (!$attempt->isSuccess) {
                $logger->debug("Failed attempt", [
                    'attempt' => $attempt->attemptNumber,
                    'error' => $attempt->errorMessage,
                    'duration_ms' => $attempt->durationMs,
                ]);
            }
        }
    }
});
```

### Key Features

1. **Complete Token Breakdown**: Input, output, cache read/write, reasoning tokens
2. **Throughput Metrics**: Tokens per second (output and total)
3. **TTFC for Streaming**: Time-to-first-chunk measured and included in stats
4. **Per-Attempt Granularity**: Individual attempt stats with error details
5. **Execution Aggregation**: Cumulative stats across all attempts
6. **Immutable Value Objects**: Thread-safe, serializable stats objects
7. **JSON Serialization**: `toArray()` and `__toString()` for logging/export

---

## Key Files for Reference

**State Machine:**
- `Data/InferenceExecution.php` - Execution lifecycle
- `Data/InferenceAttempt.php` - Single attempt tracking
- `PendingInference.php` - Lazy execution with event dispatching
- `Inference.php` - Facade

**Observability Events (packages/polyglot/src/Inference/Events/):**
- `InferenceStarted.php` - Timing: inference begin
- `InferenceCompleted.php` - Timing: inference end with duration
- `InferenceUsageReported.php` - Token usage breakdown
- `InferenceAttemptStarted.php` - Retry: attempt begin
- `InferenceAttemptSucceeded.php` - Retry: attempt success
- `InferenceAttemptFailed.php` - Retry: attempt failure
- `StreamFirstChunkReceived.php` - Streaming: time-to-first-chunk (TTFC)
- `InferenceAttemptStatsReported.php` - Stats: per-attempt metrics
- `InferenceExecutionStatsReported.php` - Stats: execution aggregate metrics
- `InferenceRequested.php` - Request dispatched
- `InferenceResponseCreated.php` - Response received
- `InferenceFailed.php` - Error with context

**Stats System (packages/polyglot/src/Inference/):**
- `Data/InferenceAttemptStats.php` - Per-attempt stats value object
- `Data/InferenceExecutionStats.php` - Execution aggregate stats value object
- `Stats/InferenceStatsCalculator.php` - Stats calculation service

**Messages:**
- `packages/messages/src/Messages.php` - Collection with rich ops
- `packages/messages/src/Message.php` - Single message
- `packages/messages/src/Content.php` - Multi-modal content
- `packages/messages/src/ContentPart.php` - Individual parts
- `packages/messages/src/MessageStore/` - Sectioned messages

**Streaming:**
- `Streaming/InferenceStream.php` - Functional stream wrapper
- `Data/PartialInferenceResponse.php` - Accumulation logic
- `Streaming/EventStreamReader.php` - SSE parsing

**Tools:**
- `Data/ToolCall.php` - Single tool call
- `Collections/ToolCalls.php` - Tool call collection

**Provider Format:**
- `Drivers/OpenAI/OpenAIBodyFormat.php` - Capability methods
- `Drivers/Gemini/GeminiBodyFormat.php` - Different capabilities
- `Drivers/Anthropic/AnthropicBodyFormat.php` - Cache support

**Request Pool (packages/http-client/):**
- `PendingHttpPool.php` - Deferred pool execution
- `Contracts/CanHandleRequestPool.php` - Pool handler interface
- `Collections/HttpRequestList.php` - Typed request collection
- `Collections/HttpResponseList.php` - Result monad collection
- `Drivers/Guzzle/GuzzlePool.php` - Guzzle concurrent execution
- `Drivers/Symfony/SymfonyPool.php` - Symfony concurrent execution
- `Drivers/Laravel/LaravelPool.php` - Laravel batched execution
- `Drivers/Curl/Pool/CurlPool.php` - Native curl_multi implementation

**MessageStore (packages/messages/src/MessageStore/):**
- `MessageStore.php` - Sectioned context library
- `Section.php` - Named message section
- `Collections/Sections.php` - Section collection
- `Operators/SectionOperator.php` - Fluent section manipulation
- `MessageStoreParameters.php` - Store parameters

**Templates (packages/templates/src/):**
- `Template.php` - Main template class with XML parsing
- `Drivers/TwigDriver.php` - Twig engine integration
- `Drivers/BladeDriver.php` - Blade engine integration
- `Drivers/ArrowpipeDriver.php` - Custom `<|var|>` syntax
- `Data/TemplateInfo.php` - FrontMatter metadata extraction
- `Utils/StringTemplate.php` - ArrowPipe variable substitution
