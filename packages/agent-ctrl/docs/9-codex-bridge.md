---
title: Codex
description: 'Use the Codex bridge when you need Codex sandbox and image options.'
---

Use `AgentCtrl::codex()` when you want OpenAI Codex through the same fluent API as the other bridges.

## Typical Use

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

$response = AgentCtrl::codex()
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->execute('Write tests for this service.');
```

## Main Options

- `withSandbox()`
- `disableSandbox()`
- `fullAuto()`
- `dangerouslyBypass()`
- `skipGitRepoCheck()`
- `continueSession()`
- `resumeSession()`
- `withAdditionalDirs()`
- `withImages()`

## Sandbox Modes

Codex supports:

- `SandboxMode::ReadOnly`
- `SandboxMode::WorkspaceWrite`
- `SandboxMode::DangerFullAccess`

## Notes

- Codex returns usage data when available
- Session IDs are normalized from Codex thread IDs
- Tool activity is normalized into `ToolCall` objects
