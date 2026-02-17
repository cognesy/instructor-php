---
title: 'Context & Compilers'
description: 'Manage conversation data with AgentContext and control message compilation for LLM calls'
---

# Agent Context & Message Compilers

## AgentContext

`AgentContext` holds the conversation data: messages, system prompt, metadata, and an optional response format.

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withMetadata('session_id', 'abc');

// Access
$state->context()->systemPrompt();
$state->context()->messages();
$state->context()->metadata();
```

## Message Compilers

Before each LLM call, the driver uses a `CanCompileMessages` implementation to transform `AgentState` into the final `Messages` sent to the LLM.

```php
interface CanCompileMessages
{
    public function compile(AgentState $state): Messages;
}
```

The default compiler assembles messages from the agent's context. You can replace it to control exactly what the LLM sees.

## Custom Compiler

Implement `CanCompileMessages` for custom message assembly:

```php
use Cognesy\Agents\Context\CanCompileMessages;use Cognesy\Agents\Data\AgentState;use Cognesy\Messages\Messages;

class MyCompiler implements CanCompileMessages
{
    public function compile(AgentState $state): Messages
    {
        // Custom logic to build messages from state
        return $state->messages();
    }
}
```

Inject it into the loop's driver:

```php
$inference = InferenceRuntime::fromProvider(LLMProvider::new());
$driver = new ToolCallingDriver(
    inference: $inference,
    messageCompiler: new MyCompiler(),
);
$loop = AgentLoop::default()->withDriver($driver);
```

Or via `AgentBuilder`:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseContextCompiler;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompiler(new MyCompiler()))
    ->build();
```

## Use Cases

Custom compilers give you full control over what the LLM sees on each step. Common patterns include:

- **Context window management** — trim or summarize older messages when the conversation exceeds the model's token limit, keeping the most recent and most relevant exchanges
- **Filtering** — exclude internal tool traces, metadata messages, or debug output that the LLM doesn't need
- **Injection** — prepend dynamic instructions, inject retrieved context (RAG), or append reminders based on the current state
- **Format transformation** — restructure messages for a specific model's expected format or optimize token usage

### Example: Trimming to Token Limit

A compiler that keeps only the most recent messages within a token budget:

```php
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

        // Walk backwards, keeping recent messages first
        foreach (array_reverse($messages->toArray()) as $message) {
            $estimate = (int) ceil(strlen($message->content()) / 4);
            if ($tokens + $estimate > $this->maxTokens) {
                break;
            }
            $tokens += $estimate;
            array_unshift($kept, $message);
        }

        return Messages::fromArray($kept);
    }
}
```

Use as a decorator via `UseContextCompilerDecorator`:

```php
use Cognesy\Agents\Capability\Core\UseContextCompilerDecorator;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompilerDecorator(
        fn(CanCompileMessages $inner) => new TokenLimitCompiler($inner, maxTokens: 4000)
    ))
    ->build();
```

The decorator pattern wraps the default compiler, so you get standard message assembly plus your custom logic on top.
