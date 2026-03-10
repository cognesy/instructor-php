---
title: 'Context & Compilers'
description: 'Manage conversation data with AgentContext and control message compilation for LLM calls'
---

# Agent Context & Message Compilers

## Introduction

Every agent maintains a rich context that accumulates messages, metadata, system prompts, and response format preferences throughout its lifetime. Before each LLM call, a **message compiler** decides exactly which messages from this context should be sent to the model.

This separation of storage from presentation is a deliberate architectural choice. The `AgentContext` acts as the single source of truth for all conversation data, while the compiler acts as a lens -- selecting, filtering, and arranging messages for each individual inference call. You can swap compilers without touching the underlying data, and you can modify the data without worrying about how it will be presented.

> **Key Insight:** Think of `AgentContext` as a database and the compiler as a query. The database stores everything; the query decides what the model actually sees.

<a name="agent-context"></a>
## AgentContext

`AgentContext` is the immutable container at the heart of agent state. It is declared as `final readonly`, ensuring that every modification produces a new instance rather than mutating existing data. This immutability guarantee makes agent state safe to pass through hook pipelines and across execution boundaries without risk of unintended side effects.

The context holds four distinct concerns:

| Concern | Type | Description |
|---------|------|-------------|
| **MessageStore** | `MessageStore` | A sectioned store of all conversation messages, organized by named sections (e.g., `messages`, `buffer`, `summary`) |
| **Metadata** | `Metadata` | Arbitrary key-value data carried across the execution -- session IDs, user preferences, feature flags, or any application-specific state |
| **System Prompt** | `string` | The system-level instruction sent to the model that defines its behavior and persona |
| **ResponseFormat** | `ResponseFormat` | Optional structured output format constraints (JSON schema, etc.) that guide the model's response structure |

In normal usage, you interact with context through `AgentState` rather than constructing `AgentContext` directly:

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withMetadata('session_id', 'abc');

// Read context values
$state->context()->systemPrompt();   // 'You are a helpful assistant.'
$state->context()->metadata();       // Metadata instance
$state->context()->messages();       // Messages from the DEFAULT section
$state->context()->store();          // Full MessageStore with all sections
```

### Constructing AgentContext Directly

While most use cases are handled through `AgentState`, you can construct an `AgentContext` directly when you need fine-grained control. The constructor accepts flexible types for convenience:

```php
use Cognesy\Agents\Context\AgentContext;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Utils\Metadata;

$context = new AgentContext(
    store: new MessageStore(),                  // or null for empty store
    metadata: ['session_id' => 'abc'],          // array, Metadata instance, or null
    systemPrompt: 'You are a data analyst.',
    responseFormat: $responseFormat,            // ResponseFormat instance or null
);
```

### Mutating Context

Since `AgentContext` is immutable, all "mutations" return a new instance. The `with()` method provides a convenient way to change multiple properties at once, while dedicated methods handle specific updates:

```php
// Change multiple properties at once
$updated = $context->with(
    systemPrompt: 'New prompt',
    metadata: new Metadata(['key' => 'value']),
);

// Or use dedicated methods
$updated = $context
    ->withSystemPrompt('New prompt')
    ->withMetadataKey('user_id', 42)
    ->withResponseFormat(['type' => 'json_object']);

// Message manipulation
$updated = $context->withMessages($messages);           // Replace all messages in DEFAULT section
$updated = $context->withAppendedMessages($messages);   // Append to DEFAULT section
$updated = $context->withMessageStore($store);          // Replace the entire store
```

<a name="context-sections"></a>
### Context Sections

The `MessageStore` inside `AgentContext` is divided into named sections defined by the `ContextSections` class. Each section holds a distinct category of messages, allowing the system to organize conversation data by purpose:

| Section | Constant | Purpose |
|---------|----------|---------|
| `messages` | `ContextSections::DEFAULT` | Primary conversation history -- user messages, assistant responses, and tool results |
| `buffer` | `ContextSections::BUFFER` | Temporary working messages such as intermediate reasoning steps or ephemeral context |
| `summary` | `ContextSections::SUMMARY` | Condensed summaries of older conversation history, typically produced by summarization capabilities |

When sections are sent to the model, they follow a defined **inference order** -- summary first, then buffer, then the main conversation -- so the model receives context in a logical sequence from oldest/most general to newest/most specific:

```php
use Cognesy\Agents\Context\ContextSections;

ContextSections::inferenceOrder();
// Returns: ['summary', 'buffer', 'messages']
```

This ordering matters when compilers assemble messages from multiple sections. By placing summaries before the primary conversation, the model gets a high-level understanding of past exchanges before diving into the current interaction.

> **Extensibility:** While the framework defines three built-in sections, the `MessageStore` supports arbitrary section names. You can create custom sections for domain-specific needs, though you will need a custom compiler to include them in inference.

<a name="message-compilers"></a>
## Message Compilers

Before each model call, the driver asks a `CanCompileMessages` implementation to select and arrange the messages the model should receive. The compiler reads from `AgentState` and returns a flat `Messages` collection:

```php
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Messages\Messages;

interface CanCompileMessages
{
    public function compile(AgentState $state): Messages;
}
```

The compiler is the **single point** where you control what the model sees. It can filter, reorder, truncate, or inject messages -- all without modifying the underlying message store. This makes compilers the ideal place to implement context window management, message redaction, or any transformation that should only affect the model's view of the conversation.

<a name="built-in-compilers"></a>
### Built-in Compilers

The framework ships with three compilers, each suited to different scenarios. Understanding when to use each one is key to building agents that manage context effectively.

#### ConversationWithCurrentToolTrace (Default)

The default compiler provides intelligent trace filtering for multi-step agent executions. It includes all non-trace conversation messages plus only the trace messages from the **current execution**. This prevents the model from seeing internal tool-calling traces from previous executions, keeping the context clean and focused:

```php
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;

$compiler = new ConversationWithCurrentToolTrace();
```

Messages are distinguished by metadata. Each message carries two metadata flags:

- **`is_trace`** -- a boolean indicating whether the message is an internal trace (e.g., tool call/response pairs within a sub-execution) or a conversation message visible to the user
- **`execution_id`** -- a UUID identifying which execution produced the message

The compiler's logic is straightforward: include a message if it is either not a trace, or if its `execution_id` matches the current execution. When there is no active execution (between executions), all traces are excluded:

```php
// Pseudocode of the filtering logic:
$include = !$message->metadata()->get('is_trace')
    || $message->metadata()->get('execution_id') === $currentExecutionId;
```

This compiler is particularly valuable when building agents that invoke sub-agents or perform multi-step tool calling, as it ensures each execution sees only its own internal state while preserving the full conversational history.

#### AllSections

The simplest compiler -- it sends every message from every section, with no filtering whatsoever. This is useful for debugging, testing, or when you want the model to see the complete, unedited history:

```php
use Cognesy\Agents\Context\Compilers\AllSections;

$compiler = new AllSections();
```

> **Warning:** In production agents with long-running conversations, `AllSections` can quickly exceed the model's context window. Consider using it primarily for development and debugging.

#### SelectedSections

Sends messages from specific sections in a defined order. This compiler is essential when you have a summarization strategy and want to send the summary followed by only recent messages, or when you want to exclude certain sections entirely:

```php
use Cognesy\Agents\Context\Compilers\SelectedSections;

// Use the default inference order (summary, buffer, messages)
$compiler = SelectedSections::default();

// Or specify exactly which sections to include and their order
$compiler = new SelectedSections(['summary', 'messages']);
```

If a named section does not exist in the store, it is silently skipped. When an empty sections array is provided, the compiler falls back to returning just the default section's messages.

This compiler pairs naturally with the [Summarization capability](/docs/agents/context-and-compilers#context-sections) -- as older messages are condensed into summaries, the `SelectedSections` compiler can send the summary section followed by only recent conversation messages, keeping the context compact.

<a name="installing-compiler"></a>
## Installing a Custom Compiler

### Via AgentBuilder (Recommended)

The `UseContextCompiler` capability provides a clean, declarative way to replace the default compiler during agent construction:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseContextCompiler;
use Cognesy\Agents\Context\Compilers\AllSections;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompiler(new AllSections()))
    ->build();
```

### Via Driver (Manual)

When working directly with the loop and driver, pass the compiler at construction time. Any driver implementing the `CanAcceptMessageCompiler` interface supports this:

```php
use Cognesy\Agents\Context\CanAcceptMessageCompiler;

$driver = $driver->withMessageCompiler(new AllSections());
$loop = AgentLoop::default()->withDriver($driver);
```

The `CanAcceptMessageCompiler` interface requires two methods:

```php
interface CanAcceptMessageCompiler
{
    public function messageCompiler(): CanCompileMessages;
    public function withMessageCompiler(CanCompileMessages $compiler): static;
}
```

<a name="custom-compiler"></a>
## Writing a Custom Compiler

Implement the `CanCompileMessages` interface to build your own message selection strategy. The `compile` method receives the full `AgentState`, giving you access to the message store, metadata, execution state, and all other agent data:

```php
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Messages\Messages;

class RecentMessagesCompiler implements CanCompileMessages
{
    public function __construct(
        private int $maxMessages = 20,
    ) {}

    public function compile(AgentState $state): Messages
    {
        $all = $state->store()->toMessages()->all();
        $recent = array_slice($all, -$this->maxMessages);
        return new Messages(...$recent);
    }
}
```

<a name="decorating-compiler"></a>
### Decorating the Default Compiler

Often you want to enhance the default compiler rather than replace it entirely. The `UseContextCompilerDecorator` capability wraps the existing compiler, letting you post-process its output. The decorator receives whatever compiler is currently configured and returns a new one that wraps it:

```php
use Cognesy\Agents\Capability\Core\UseContextCompilerDecorator;
use Cognesy\Agents\Context\CanCompileMessages;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompilerDecorator(
        fn(CanCompileMessages $inner) => new TokenLimitCompiler($inner, maxTokens: 4000)
    ))
    ->build();
```

This approach composes naturally -- multiple decorators can be stacked, and each one wraps the result of the previous. This is the recommended pattern when you want to add constraints (like token limits or message filtering) on top of an existing compilation strategy.

### Example: Token-Limited Compiler

A common pattern is to limit the messages sent to the model based on an estimated token budget. This decorator wraps any inner compiler and keeps only the most recent messages that fit within the budget, working backward from the newest message:

```php
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Messages\Messages;

class TokenLimitCompiler implements CanCompileMessages
{
    public function __construct(
        private CanCompileMessages $inner,
        private int $maxTokens = 8000,
    ) {}

    public function compile(AgentState $state): Messages
    {
        $messages = $this->inner->compile($state);
        $kept = [];
        $tokens = 0;

        // Walk backward from newest messages, accumulating until budget is exhausted
        foreach (array_reverse($messages->all()) as $message) {
            $estimate = (int) ceil(strlen($message->content()->toString()) / 4);
            if ($tokens + $estimate > $this->maxTokens) {
                break;
            }
            $tokens += $estimate;
            array_unshift($kept, $message);
        }

        return new Messages(...$kept);
    }
}
```

> **Note:** The token estimate here uses a simple `strlen / 4` heuristic. For production use, consider integrating a proper tokenizer for your target model.

### Example: Injecting Retrieved Documents

Another common pattern is injecting ephemeral context (such as RAG-retrieved documents) into the message stream without permanently storing them:

```php
class RAGCompiler implements CanCompileMessages
{
    public function __construct(
        private CanCompileMessages $inner,
        private DocumentRetriever $retriever,
    ) {}

    public function compile(AgentState $state): Messages
    {
        $messages = $this->inner->compile($state);

        // Get the last user message to use as a retrieval query
        $lastUserMessage = $messages->lastOfRole('user');
        if ($lastUserMessage === null) {
            return $messages;
        }

        $documents = $this->retriever->search($lastUserMessage->content()->toString());
        $contextMessage = Message::system("Relevant documents:\n" . $documents);

        // Prepend the context before the conversation
        return new Messages($contextMessage, ...$messages->all());
    }
}
```

<a name="serialization"></a>
## Serialization

`AgentContext` supports full serialization through `toArray()` and `fromArray()`, making it straightforward to persist and restore agent context across requests, sessions, or process boundaries:

```php
// Serialize to array (e.g., for storage in a database or cache)
$data = $context->toArray();
// Returns: ['metadata' => [...], 'systemPrompt' => '...', 'responseFormat' => [...], 'messageStore' => [...]]

// Restore from array
$restored = AgentContext::fromArray($data);
```

<a name="use-cases"></a>
## Common Use Cases

Compilers are the right tool when you need to:

- **Trim older messages** to stay within the model's context window while preserving recent conversation flow
- **Inject ephemeral context** (e.g., retrieved documents, real-time data) without permanently storing them in the message history
- **Exclude internal traces** from multi-agent orchestration so child agent tool-calling details do not leak into the parent's view
- **Prioritize sections** by sending summaries before raw history, giving the model a structured overview
- **Redact sensitive content** before it reaches the model, such as stripping PII or credentials from tool outputs
- **Implement sliding windows** that keep only the most recent N messages or N tokens of conversation
- **Support hybrid strategies** by combining summarized older history with full recent messages for optimal context utilization
