# Basic Agent

The simplest agent uses `AgentLoop` to send a message and get a response.

## Hello World

```php
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Data\AgentState;

$loop = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('What is 2+2?');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
// "2 + 2 equals 4."
```

## What Happens

1. `AgentLoop::execute()` starts the loop
2. The driver (`ToolCallingDriver`) sends messages to the LLM
3. LLM responds with text (no tool calls)
4. The loop detects no tool calls and stops
5. Final response is available via `$result->finalResponse()`

## Customizing the Loop

Use `with()` to swap components on the default loop:

```php
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Drivers\ReAct\ReActDriver;

// Add tools
$loop = AgentLoop::default()->withTool($myTool);

// Swap driver
$loop = AgentLoop::default()->withDriver(new ReActDriver(model: 'gpt-4o'));
```

## System Prompt

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withUserMessage('Hello!');
```
