---
title: Response Object
description: 'Understand AgentResponse, ToolCall, and TokenUsage.'
---

Every execution returns `AgentResponse`.

## Core Data

- `agentType` (`AgentType`)
- `text` (`string`)
- `exitCode` (`int`)
- `usage` (`TokenUsage|null`)
- `cost` (`float|null`)
- `toolCalls` (`list<ToolCall>`)
- `rawResponse` (`mixed`)

## Methods

- `isSuccess(): bool`
- `text(): string`
- `sessionId(): ?AgentSessionId`
- `usage(): ?TokenUsage`
- `cost(): ?float`
- `parseFailures(): int`
- `parseFailureSamples(): array`

## Minimal Checks

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Create a short changelog from git diff.');

if ($response->isSuccess()) {
    echo $response->text();
}

echo $response->exitCode;
echo $response->sessionId() !== null ? 'has session' : 'no session';
```
