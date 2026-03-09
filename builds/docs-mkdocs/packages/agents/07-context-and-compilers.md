---
title: 'Context & Compilers'
description: 'Manage conversation data with AgentContext and control message compilation for LLM calls'
---

# Agent Context & Message Compilers

## AgentContext

`AgentContext` is the low-level container for:

- message store
- metadata
- system prompt
- response format

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withMetadata('session_id', 'abc');

$state->context()->systemPrompt();
$state->context()->messages();
$state->context()->metadata();
// @doctest id="fd83"
```

In normal usage, update context through `AgentState`.

## Message Compilers

Before each model call, the driver asks a `CanCompileMessages` implementation for the messages it should send:

```php
interface CanCompileMessages
{
    public function compile(AgentState $state): Messages;
}
// @doctest id="fd04"
```

The default compiler is `ConversationWithCurrentToolTrace`.
It sends the conversation plus trace messages from the current execution.

## Custom Compiler

```php
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Messages\Messages;

class MyCompiler implements CanCompileMessages
{
    public function compile(AgentState $state): Messages
    {
        return $state->store()->toMessages();
    }
}
// @doctest id="c5b5"
```

Use it directly with a driver:

```php
$inference = InferenceRuntime::fromProvider(LLMProvider::new());
$driver = new ToolCallingDriver(
    inference: $inference,
    messageCompiler: new MyCompiler(),
);
$loop = AgentLoop::default()->withDriver($driver);
// @doctest id="a96f"
```

Or install it with `AgentBuilder`:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseContextCompiler;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompiler(new MyCompiler()))
    ->build();
// @doctest id="c0d2"
```

## Common Uses

- trim older messages
- inject temporary context
- restrict the model to selected message-store sections

### Example: Keep Only Recent Messages

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
// @doctest id="c3cd"
```

Wrap the default compiler with `UseContextCompilerDecorator`:

```php
use Cognesy\Agents\Capability\Core\UseContextCompilerDecorator;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompilerDecorator(
        fn(CanCompileMessages $inner) => new TokenLimitCompiler($inner, maxTokens: 4000)
    ))
    ->build();
// @doctest id="91f1"
```
