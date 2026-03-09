---
title: Response Object
description: 'Read text, session, usage, and tool activity from AgentResponse.'
---

Every execution returns an `AgentResponse`.

## Core Data

- `agentType`
- `text`
- `exitCode`
- `usage`
- `cost`
- `toolCalls`
- `rawResponse`
- `parseFailures`

## Common Methods

- `isSuccess(): bool`
- `text(): string`
- `sessionId(): ?AgentSessionId`
- `usage(): ?TokenUsage`
- `cost(): ?float`
- `parseFailures(): int`
- `parseFailureSamples(): array`

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Create a short summary.');

if ($response->isSuccess()) {
    echo $response->text();
}
```

## Tool Calls

`toolCalls` contains normalized `ToolCall` objects across all bridges.

Each tool call gives you:

- `tool`
- `input`
- `output`
- `isError`
- `callId()`
- `isCompleted()`

## Token Usage

When the underlying CLI exposes usage data, `usage()` returns `TokenUsage`.

You can read:

- `input`
- `output`
- `cacheRead`
- `cacheWrite`
- `reasoning`
- `total()`
