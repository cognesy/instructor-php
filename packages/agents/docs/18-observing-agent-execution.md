---
title: 'Observing Agent Execution'
docname: 'observing_agent_execution'
order: 18
id: 'observing-agent-execution'
---

## Introduction

For responsive chat interfaces, it is usually not enough to wait for `execute()` to finish and then render the final answer. The Agents package exposes the execution lifecycle through events, and the `AgentEventBroadcaster` converts selected events into UI-friendly envelopes that can be forwarded to SSE, WebSocket, or any custom transport.

This gives your application a simple observation layer:

- live text chunks while the LLM is producing output
- step and tool status updates while the agent is working
- execution status transitions such as `processing`, `completed`, and `failed`

The broadcaster is observation-only. It does not change agent behavior or persist any state. It listens to events already emitted by the agent loop and forwards them in a consistent format for your UI.

## When to Use It

Use `AgentEventBroadcaster` when your app needs to reflect agent progress while execution is still in flight:

- chat UIs that should show text as it arrives
- TUIs that need progress indicators and tool activity
- web apps that stream agent status over SSE or WebSockets
- observability dashboards that track step, tool, and continuation events

If you only need the final answer, `execute()` is enough. If you need step-by-step inspection, use `iterate()`. If you need UI updates during execution, attach a broadcaster.

## How It Works

`AgentLoop`, the active driver, and the underlying inference runtime share the same event dispatcher. `AgentEventBroadcaster` listens to that dispatcher and translates selected events into envelopes such as:

- `agent.status`
- `agent.step.started`
- `agent.step.completed`
- `agent.tool.started`
- `agent.tool.completed`
- `agent.stream.chunk`

Your application provides the final transport by implementing `CanBroadcastAgentEvents`.

## Required Steps

To make agent events available to your application, set up four pieces:

1. Create the agent with a shared event bus. The default builder and `AgentLoop::default()` already do this.
2. Implement `CanBroadcastAgentEvents` to forward envelopes to your transport.
3. Add `UseAgentBroadcasting` to the builder so the relevant listeners are registered for you.
4. If you want streamed text chunks, make sure the LLM request is created with `stream: true`.

Minimal example:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Capability\Broadcasting\UseAgentBroadcasting;
use Cognesy\Agents\Data\AgentState;

final class SseTransport implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void {
        // Forward the envelope to SSE, WebSocket, Redis, etc.
    }
}

$agent = AgentBuilder::base()
    ->withCapability(new UseAgentBroadcasting(
        broadcaster: new SseTransport(),
        sessionId: 'chat-42',
    ))
    ->build();

$result = $agent->execute(
    AgentState::empty()->withUserMessage('Explain closures in PHP.')
);
```

Once installed, the capability listens to the relevant execution, step, tool, continuation, and streaming events and emits normalized envelopes through your transport. Your app consumes those envelopes and updates the UI.

## Enabling Live Text Chunks

`agent.stream.chunk` is emitted only when the underlying inference request is streamed. Attaching a broadcaster alone is not enough: the LLM request must be created with streaming enabled.

In practice, that means configuring the driver so its inference request includes `stream: true` in the request options.

Minimal example with an explicit driver:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$events = new EventDispatcher('agent');
$llm = LLMProvider::new();

$agent = AgentLoop::default()->withDriver(
    new ToolCallingDriver(
        llm: $llm,
        inference: InferenceRuntime::fromProvider($llm, events: $events),
        options: ['stream' => true],
        events: $events,
    )
);
```

Without streamed inference, you still receive step, tool, and status envelopes, but not incremental text chunks.

## Transport Integration

`AgentEventBroadcaster` emits envelopes through a simple contract:

```php
interface CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void;
}
```

This keeps the integration boundary small. The broadcaster does not require a framework or network stack. Your implementation decides how envelopes leave the process.

Typical patterns:

- SSE endpoint writes each envelope as a server-sent event
- WebSocket handler publishes envelopes to a client-specific channel
- queue worker forwards envelopes to Redis or another pub/sub layer
- CLI/TUI adapter renders envelopes directly to the terminal

## Advanced Option

If you need lower-level control, you can create `AgentEventBroadcaster` yourself and attach it via `$agent->wiretap($broadcaster->wiretap())`. `UseAgentBroadcasting` is just the prewired integration path for the event set that is usually useful in interactive applications.

## Choosing a Broadcast Configuration

`BroadcastConfig` provides three presets:

- `minimal()` for status-only tracking
- `standard()` for status plus streamed text chunks
- `debug()` for status, stream chunks, continuation trace, and tool arguments

Minimal example:

```php
use Cognesy\Agents\Broadcasting\BroadcastConfig;

$broadcaster = new AgentEventBroadcaster(
    broadcaster: new SseTransport(),
    sessionId: 'chat-42',
    executionId: 'exec-1',
    config: BroadcastConfig::standard(),
);
```

For most user-facing apps, `standard()` is the right default.

## What the UI Can Rely On

The broadcaster emits stable, app-facing event types. A typical UI flow looks like this:

1. `agent.status` changes to `processing`
2. `agent.step.started` appears
3. zero or more `agent.stream.chunk` envelopes arrive
4. zero or more `agent.tool.started` / `agent.tool.completed` envelopes arrive
5. `agent.step.completed` appears
6. `agent.status` changes to `completed`, `failed`, `cancelled`, or `stopped`

This is usually enough to drive:

- typing indicators
- streaming assistant text
- tool activity rows
- execution status badges

## Notes

- `AgentEventBroadcaster` is transport-agnostic. It formats envelopes but does not send HTTP responses or manage sockets.
- The broadcaster does not replace `iterate()`. Use `iterate()` for application-side control flow, and use broadcasting for UI observation.
- If you need additional behavior, you can attach your own listeners alongside the broadcaster with `onEvent()`.
