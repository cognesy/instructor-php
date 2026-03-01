---
title: OpenCode Bridge (Internals)
description: 'Low-level OpenCode CLI bridge flow for advanced use cases.'
---

> Most users should use `AgentCtrl::openCode()` from the high-level API.
> Use this page when you need direct request/command/parser control.

## Core Flow

```php
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;

$request = new OpenCodeRequest(
    prompt: 'Summarize the architecture in short bullets.',
    outputFormat: OutputFormat::Json,
);

$spec = (new OpenCodeCommandBuilder())->buildRun($request);
$result = SandboxCommandExecutor::forOpenCode()->execute($spec);
$response = (new ResponseParser())->parse($result, OutputFormat::Json);

echo $response->messageText();
```

## Session Handling

`OpenCodeRequest` supports:

- `continueSession: true` (resume most recent)
- `sessionId: '...'` (resume a specific session)

## Key Types

- Request DTO: `OpenCodeRequest`
- Command builder: `OpenCodeCommandBuilder`
- Parser: `ResponseParser`
- Stream events: `TextEvent`, `ToolUseEvent`, `StepFinishEvent`, `ErrorEvent`
