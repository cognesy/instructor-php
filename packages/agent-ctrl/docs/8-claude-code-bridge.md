---
title: Claude Code Bridge (Internals)
description: 'Low-level Claude Code CLI bridge flow for advanced use cases.'
---

> Most users should use `AgentCtrl::claudeCode()` from the high-level API.
> Use this page when you need direct request/command/parser control.

## Core Flow

```php
use Cognesy\AgentCtrl\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

$request = new ClaudeRequest(
    prompt: 'Summarize this repository.',
    outputFormat: OutputFormat::Json,
);

$spec = (new ClaudeCommandBuilder())->buildHeadless($request);
$result = SandboxCommandExecutor::forClaudeCode()->execute($spec);
$response = (new ResponseParser())->parse($result, OutputFormat::Json);

echo $response->messageText();
```

## Streaming

For incremental events, use `OutputFormat::StreamJson` and `includePartialMessages: true`.

```php
$request = new ClaudeRequest(
    prompt: 'Explain the current architecture.',
    outputFormat: OutputFormat::StreamJson,
    includePartialMessages: true,
);
```

## Key Types

- Request DTO: `ClaudeRequest`
- Command builder: `ClaudeCommandBuilder`
- Parser: `ResponseParser`
- Stream events: `MessageEvent`, `ResultEvent`, `ErrorEvent`

## Permission Modes

Current values:

- `PermissionMode::DefaultMode`
- `PermissionMode::Plan`
- `PermissionMode::AcceptEdits`
- `PermissionMode::BypassPermissions`
