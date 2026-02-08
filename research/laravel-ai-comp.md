# Laravel AI vs Instructor PHP Agents: Detailed Comparison

## 1. Agent Loop Design

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **File** | `src/Providers/Concerns/GeneratesText.php` | `packages/agents/src/Core/AgentLoop.php` |
| **Architecture** | Pipeline middleware + Prism gateway delegation | Single loop with hook intercepts |
| **Entry Points** | `agent->prompt()` / `agent->stream()` / `agent->queue()` | `execute()` (blocking) / `iterate()` (generator) |
| **Iteration Model** | Delegated to Prism library (internal loop) | PHP Generator yielding `AgentState` per step |
| **Loop Control** | `#[MaxSteps(n)]` attribute or auto-calculated | Hooks can stop via `StopSignal` |
| **Middleware** | Laravel pipeline pattern for request transformation | Hooks at lifecycle points |

**Key Difference**: Laravel AI delegates the actual agentic loop to the Prism library - it wraps tools and handles events but doesn't control step-by-step execution. Instructor PHP has full control over the loop with generator-based iteration.

---

## 2. Tools

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Definition** | `Tool` interface with `description()`, `handle()`, `schema()` | `ToolInterface` with `__invoke()` + reflection |
| **Schema** | Explicit via `schema(JsonSchema $schema)` method | Auto-generated from method signature via `StructureFactory` |
| **Request Object** | `ToolRequest` wraps arguments | Arguments passed directly to `__invoke()` |
| **Result Type** | Must return `Stringable\|string` | `Result` (Success/Failure monad) |
| **Provider Tools** | Built-in: `WebSearch`, `WebFetch`, `FileSearch` (vendor-specific) | `Webpage` class with scraper drivers (WebFetch equiv); no native LLM search |
| **Built-in Tools** | None (relies on provider tools) | Rich set: Bash, File (read/write/edit/list/search), Metadata, Skills, Subagents, Tasks, ToolRegistry |
| **Tool Events** | `InvokingTool`, `ToolInvoked` events | `BeforeToolUse`, `AfterToolUse` hooks |

**Laravel AI Tool Definition**:
```php
interface Tool {
    public function description(): Stringable|string;
    public function handle(Request $request): Stringable|string;
    public function schema(JsonSchema $schema): array;
}
```

**Instructor PHP Tool Definition**:
```php
interface ToolInterface {
    public function __invoke(mixed ...$args): Result;
    public function toToolSchema(): array;
    public function name(): string;
    public function description(): string;
}
```

**Instructor PHP Built-in Tools** (via Capabilities):

| Capability | Tools |
|------------|-------|
| `UseBash` | `BashTool` - Execute shell commands |
| `UseFileTools` | `ReadFileTool`, `WriteFileTool`, `EditFileTool`, `ListDirTool`, `SearchFilesTool` |
| `UseMetadataTools` | `MetadataReadTool`, `MetadataWriteTool`, `MetadataListTool` |
| `UseSkills` | `LoadSkillTool` - Dynamic skill loading |
| `UseSubagents` | `SpawnSubagentTool`, `ResearchSubagentTool` |
| `UseSelfCritique` | `SelfCriticSubagentTool` |
| `UseTaskPlanning` | `TodoWriteTool` |
| `UseToolRegistry` | `ToolsTool` - Browse/search available tools |
| `UseStructuredOutputs` | `StructuredOutputTool` |

**Key Difference**: Laravel AI has vendor-specific provider tools (WebSearch uses OpenAI/Anthropic/Gemini native search). Instructor PHP has equivalent capabilities via general-purpose libraries: `Webpage`/`Scraper` classes (in `packages/auxiliary/src/Web/`) with multiple drivers (Firecrawl, Browsershot, JinaReader, etc.) for web fetching, and `SearchFilesTool` for filesystem glob-based search. Both frameworks use explicit schema definition, but Instructor PHP can also generate schemas from method signatures via reflection.

---

## 3. Hooks / Lifecycle Events

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **System** | Laravel Event Dispatcher | `HookStack` with priority ordering |
| **Hook Points** | `PromptingAgent`, `AgentPrompted`, `InvokingTool`, `ToolInvoked`, `StreamingAgent`, `AgentStreamed`, `AgentFailedOver` | 8 triggers: `BeforeExecution`, `BeforeStep`, `BeforeToolUse`, `AfterToolUse`, `AfterStep`, `OnStop`, `AfterExecution`, `OnError` |
| **Registration** | Laravel event listeners (config or `Event::listen()`) | `HookStack->with(hook, triggers, priority)` |
| **State Mutation** | Events are informational, cannot modify state | Hooks can fully modify `AgentState` via `HookContext->withState()` |
| **Priority** | Laravel listener priority | Numeric priority (lower numbers run later) |
| **Middleware** | Pipeline middleware can transform `AgentPrompt` | No middleware pattern - hooks only |

**Laravel AI Event Example**:
```php
// Events are dispatched but cannot modify the request
$this->events->dispatch(new PromptingAgent($invocationId, $prompt));
// ... execution happens ...
$this->events->dispatch(new AgentPrompted($invocationId, $prompt, $response));
```

**Instructor PHP Hook Example**:
```php
// Hooks can modify state
public function handle(HookContext $context): HookContext {
    return $context->withState($context->state()->withMessages(...));
}
```

**Key Difference**: Laravel AI events are observational (cannot modify execution). Instructor PHP hooks can intercept and modify the entire agent state.

---

## 4. Skills and Extension Points

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Extension Model** | Trait composition + interface implementation | `AgentCapability` interface with `install(builder)` |
| **Agent Definition** | Class implementing interfaces + using traits | Builder pattern with capabilities |
| **Configuration** | PHP Attributes (`#[MaxSteps]`, `#[Provider]`, etc.) | Builder methods or capability configuration |
| **Middleware** | `HasMiddleware` interface, returns `Closure[]` | No middleware - hooks instead |
| **Skills** | Not supported | `SkillLibrary` with Markdown+YAML skills |
| **Provider Tools** | Built-in `WebSearch`, `WebFetch`, `FileSearch` | `Webpage`/`Scraper` (WebFetch equiv), `SearchFilesTool` (filesystem glob, not vector) |

**Laravel AI Agent Definition**:
```php
#[Provider('anthropic')]
#[MaxSteps(20)]
#[Temperature(0.7)]
class MyAgent implements Agent, Conversational, HasTools {
    use Promptable, RemembersConversations;

    public function instructions(): string { ... }
    public function tools(): iterable { ... }
}
```

**Instructor PHP Agent Definition**:
```php
AgentBuilder::base()
    ->withCapability(new UseBash($policy))
    ->withCapability(new UseSummarization($policy))
    ->withCapability(new UseSkills($library))
    ->build();
```

**Key Difference**: Laravel AI uses traditional OOP (traits, interfaces, attributes). Instructor PHP uses composable Capabilities that bundle tools + hooks + config.

---

## 5. Error Handling

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Exception Base** | `AiException` | `AgentException` |
| **Failover** | `FailoverableException` triggers provider/model failover | No automatic failover |
| **Rate Limiting** | `RateLimitedException` triggers failover | No specific handling |
| **Tool Errors** | Caught, returned as string to LLM | `ToolExecutionException` -> `Failure` result |
| **Error Events** | `AgentFailedOver` event dispatched | `OnError` hook with `ErrorList` |
| **Retry** | Provider failover only | `forNextExecution()` clears execution, keeps context |

**Laravel AI Failover**:
```php
// Automatic failover on FailoverableException
try {
    return $callback($provider, $model);
} catch (FailoverableException $e) {
    event(new AgentFailedOver($this, $provider, $model, $e));
    continue; // Try next provider
}
```

**Key Difference**: Laravel AI has built-in provider/model failover. Instructor PHP has richer error aggregation but no automatic failover.

---

## 6. Agent State Data Model

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Response Type** | `AgentResponse` extends `TextResponse` | `AgentState` (readonly class) |
| **State Fields** | `invocationId`, `conversationId`, `text`, `usage`, `meta`, `steps`, `toolCalls`, `toolResults` | `agentId`, `parentAgentId`, `context`, `execution` |
| **Step Tracking** | `Step[]` with `text`, `toolCalls`, `toolResults`, `finishReason`, `usage` | `AgentStep` with `inputMessages`, `outputMessages`, `toolExecutions` |
| **Immutability** | Mutable objects | Fully immutable (`readonly` + `with*()`) |
| **Streaming State** | Separate `StreamableAgentResponse` | No streaming state in core |

**Laravel AI Response Structure**:
```php
class AgentResponse extends TextResponse {
    public string $invocationId;
    public ?string $conversationId;
    public string $text;
    public Usage $usage;
    public Meta $meta;
    public Collection $messages;
    public Collection $toolCalls;
    public Collection $steps;  // Step[] - full loop history
}
```

**Instructor PHP State Structure**:
```php
final readonly class AgentState {
    private string $agentId;
    private AgentContext $context;
    private ?ExecutionState $execution;
    // All immutable with with*() methods
}
```

**Key Difference**: Laravel AI returns response objects after execution. Instructor PHP maintains immutable state throughout execution with clear session/execution separation.

---

## 7. Agent Context Data Model

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Message Types** | `Message`, `UserMessage`, `AssistantMessage`, `ToolResultMessage` | Universal `Message` with `role`, `Content`, `Metadata` |
| **Message Storage** | Flat array from `agent->messages()` | `MessageStore` with named sections |
| **Attachments** | `UserMessage` has `attachments` array (files/images) | `Content` with `ContentParts` (text, image_url, file, audio) |
| **Conversation** | `Conversational` interface + `RemembersConversations` trait | `AgentContext` with `MessageStore` |
| **Persistence** | `ConversationStore` interface (database implementation) | `toArray()` / `fromArray()` serialization |
| **Metadata** | Per-message in database (`meta` column) | Two levels: `AgentContext->metadata()` + `Message->metadata()` |

**Laravel AI Messages**:
```php
class Message {
    public MessageRole $role;  // user|assistant|tool
    public ?string $content;
}

class UserMessage extends Message {
    public array $attachments;  // Attachment[]
}
```

**Instructor PHP Messages**:
```php
final readonly class Message {
    protected string $role;
    protected Content $content;  // ContentParts with text/image/audio/file
    protected Metadata $metadata;
}
```

**Key Difference**: Laravel AI has database-backed conversation storage. Instructor PHP has richer in-memory message structure with sections for context management.

---

## 8. Context Compaction Mechanism

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Mechanism** | Message count limit only | Two-phase: move-to-buffer + summarize-buffer |
| **Configuration** | `maxConversationMessages()` method (default 100) | `SummarizationPolicy` with token thresholds |
| **Token Counting** | None - relies on provider limits | `Tokenizer::tokenCount()` |
| **Summarization** | None | LLM-based summarization via `CanSummarizeMessages` |
| **Hooks** | None | `MoveMessagesToBufferHook` + `SummarizeBufferHook` |

**Laravel AI Compaction**:
```php
// Just limits message count
public function messages(): iterable {
    return $store->getLatestConversationMessages(
        $this->conversationId,
        $this->maxConversationMessages()  // Default: 100
    );
}
```

**Instructor PHP Compaction**:
```php
// Token-based two-phase compaction
new UseSummarization(new SummarizationPolicy(
    maxMessageTokens: 4000,   // Triggers move-to-buffer
    maxBufferTokens: 8000,    // Triggers summarization
    maxSummaryTokens: 1000,   // Summary output size
));
```

**Key Difference**: Laravel AI uses simple message count limits. Instructor PHP has sophisticated token-based compaction with LLM summarization.

---

## 9. Tool Discovery Mechanism

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Discovery** | `HasTools` interface, `tools()` returns iterable | `ToolRegistry` with instances + factories |
| **Lazy Loading** | No - tools instantiated when returned | Factory pattern - instantiated on `resolve()` |
| **Metadata** | None | Three levels: metadata -> fullSpec -> instance |
| **Search** | None | `ToolRegistry->search(query)` full-text search |
| **Dynamic Tools** | None | `ToolsTool` exposes registry to agent |
| **Provider Tools** | Auto-detected `WebSearch`, `WebFetch`, `FileSearch` | Web scraping via `Webpage`/`Scraper`; filesystem search via `SearchFilesTool` |

**Laravel AI Tool Discovery**:
```php
// Simple - agent returns tools directly
interface HasTools {
    public function tools(): iterable;  // Tool[]
}
```

**Instructor PHP Tool Discovery**:
```php
// Registry with lazy loading and search
$registry->registerFactory('skill.learn', fn() => new LoadSkillTool(...));
$registry->search('file');  // Full-text search
$registry->listMetadata();  // Lightweight browse
```

**Key Difference**: Laravel AI has direct tool provision. Instructor PHP has sophisticated discovery with lazy loading, metadata levels, and full-text search.

---

## 10. Serialization and Suspend/Resume

| Aspect | Laravel AI | Instructor PHP |
|--------|------------|----------------|
| **Queue Support** | Built-in via `queue()`, `broadcastOnQueue()` | No built-in queue support |
| **Job Classes** | `InvokeAgent`, `BroadcastAgent` | None |
| **Conversation Persistence** | `ConversationStore` (database) | `toArray()` / `fromArray()` |
| **Mid-execution Suspend** | No | No |
| **Resume** | Via `continue($conversationId)` | `forNextExecution()` + `iterate()` |
| **Broadcasting** | Laravel Broadcasting integration | None |

**Laravel AI Queue Support**:
```php
// Queue for background processing
$response = $agent->queue($prompt);

// Queue with broadcasting
$response = $agent->broadcastOnQueue($prompt, $channel);

// Response callbacks
$response->then(fn($result) => ...);
```

**Laravel AI Conversation Resume**:
```php
// Continue existing conversation
$agent->continue($conversationId, $user)->prompt($message);
```

**Instructor PHP Resume**:
```php
// Serialize state
$json = json_encode($state->toArray());

// Resume later
$state = AgentState::fromArray(json_decode($json, true));
$state = $state->forNextExecution();
$agent->iterate($state);
```

**Key Difference**: Laravel AI has built-in Laravel queue/broadcast integration. Instructor PHP has manual serialization but no queue support.

---

## Summary Matrix

| Dimension | Laravel AI Advantage | Instructor PHP Advantage |
|-----------|---------------------|-------------------------|
| **Loop** | Prism handles complexity, queue/broadcast support | Full loop control, generator iteration |
| **Tools** | Provider tools (native LLM search/fetch) | Rich built-in tools (Bash, File, Metadata, Skills, Subagents, Tasks), web scraping via `Webpage`/`Scraper`, reflection-based schema, Result monad |
| **Hooks** | Laravel events, middleware pipeline | State mutation, explicit priority |
| **Extension** | Familiar Laravel patterns (traits, attributes) | Composable Capabilities |
| **Errors** | Automatic provider failover | ErrorList aggregation |
| **State** | Database conversation storage | Immutable, session/execution separation |
| **Context** | Simple message array | Multi-section MessageStore |
| **Compaction** | Simple message limits | Token-based summarization |
| **Discovery** | Provider tools auto-detected | Three-level metadata, search |
| **Built-in Tools** | Relies on provider tools only | Rich set: Bash, File, Metadata, Skills, Subagents, Tasks, Web scraping (auxiliary) |
| **Persistence** | Queue jobs, database storage, broadcasting | JSON serialization |

---

## Key Architectural Differences

1. **Framework coupling**: Laravel AI is tightly coupled to Laravel (events, queues, broadcasting, Eloquent). Instructor PHP is framework-agnostic.

2. **Loop control**: Laravel AI delegates to Prism library. Instructor PHP owns the full loop with step-by-step control.

3. **State philosophy**: Laravel AI uses mutable response objects. Instructor PHP uses fully immutable state with `with*()` methods.

4. **Extension model**: Laravel AI uses OOP (traits, interfaces, attributes). Instructor PHP uses composable Capabilities.

5. **Event vs Hook**: Laravel AI events are observational. Instructor PHP hooks can modify execution.

6. **Persistence**: Laravel AI has database-first conversation storage. Instructor PHP has in-memory with manual serialization.

7. **Compaction**: Laravel AI uses message count limits. Instructor PHP uses token-based summarization.

8. **Web/search tools**: Laravel AI uses "provider tools" - vendor-native LLM capabilities (OpenAI/Anthropic/Google web search). Instructor PHP uses general-purpose PHP libraries (`Webpage`/`Scraper` with multiple drivers) that can be wrapped as agent tools.

---

## Key File References

### Laravel AI
- Agent prompt: `src/Promptable.php`
- Text generation: `src/Providers/Concerns/GeneratesText.php`
- Tool interface: `src/Contracts/Tool.php`
- Tool execution: `src/Gateway/Prism/Concerns/AddsToolsToPrismRequests.php`
- Events: `src/Events/`
- Response: `src/Responses/AgentResponse.php`, `src/Responses/TextResponse.php`
- Messages: `src/Messages/`
- Conversation storage: `src/Storage/DatabaseConversationStore.php`
- RemembersConversations: `src/Concerns/RemembersConversations.php`
- Middleware: `src/Middleware/RememberConversation.php`
- Queue jobs: `src/Jobs/InvokeAgent.php`, `src/Jobs/BroadcastAgent.php`
- Streaming: `src/Responses/StreamableAgentResponse.php`, `src/Streaming/Events/`
- Attributes: `src/Attributes/`

### Instructor PHP
- Agent loop: `packages/agents/src/Core/AgentLoop.php`
- Agent state: `packages/agents/src/Core/Data/AgentState.php`
- Agent context: `packages/agents/src/Core/Context/AgentContext.php`
- Base tool: `packages/agents/src/Core/Tools/BaseTool.php`
- Tool registry: `packages/agents/src/AgentBuilder/Capabilities/Tools/ToolRegistry.php`
- Hooks: `packages/agents/src/Hooks/`
- Capabilities: `packages/agents/src/AgentBuilder/Capabilities/`
- Bash tools: `packages/agents/src/AgentBuilder/Capabilities/Bash/`
- File tools: `packages/agents/src/AgentBuilder/Capabilities/File/`
- Metadata tools: `packages/agents/src/AgentBuilder/Capabilities/Metadata/`
- Subagent tools: `packages/agents/src/AgentBuilder/Capabilities/Subagent/`
- Task tools: `packages/agents/src/AgentBuilder/Capabilities/Tasks/`
- Skills: `packages/agents/src/AgentBuilder/Capabilities/Skills/`
- Summarization: `packages/agents/src/AgentBuilder/Capabilities/Summarization/`
- Messages: `packages/messages/src/`
- MessageStore: `packages/messages/src/MessageStore/MessageStore.php`
- Web scraping: `packages/auxiliary/src/Web/Webpage.php`, `packages/auxiliary/src/Web/Scraper.php`
- Scraper drivers: `packages/auxiliary/src/Web/Scrapers/` (Firecrawl, Browsershot, JinaReader, ScrapFly, ScrapingBee)
