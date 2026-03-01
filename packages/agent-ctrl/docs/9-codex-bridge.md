---
title: Codex Bridge (Internals)
description: 'Low-level Codex CLI bridge flow for advanced use cases.'
---

> Most users should use `AgentCtrl::codex()` from the high-level API.
> Use this page when you need direct request/command/parser control.

## Core Flow

```php
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\AgentCtrl\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

$request = new CodexRequest(
    prompt: 'List top code risks in this project.',
    outputFormat: OutputFormat::Json,
    sandboxMode: SandboxMode::ReadOnly,
);

$spec = (new CodexCommandBuilder())->buildExec($request);
$result = SandboxCommandExecutor::forCodex()->execute($spec);
$response = (new ResponseParser())->parse($result, OutputFormat::Json);

echo $response->messageText();
```

## Streaming

Codex emits JSONL events in `OutputFormat::Json`.

Common event types:

- `thread.started`
- `turn.started`
- `turn.completed`
- `item.completed`
- `error`

## Key Types

- Request DTO: `CodexRequest`
- Command builder: `CodexCommandBuilder`
- Parser: `ResponseParser`
- Output enums: `OutputFormat`, `SandboxMode`
