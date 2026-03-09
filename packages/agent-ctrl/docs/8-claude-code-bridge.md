---
title: Claude Code
description: 'Use the Claude Code bridge for general coding workflows.'
---

Use `AgentCtrl::claudeCode()` when you want Claude Code behind the unified `agent-ctrl` API.

## Typical Use

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::claudeCode()
    ->withModel('claude-sonnet-4-5')
    ->execute('Review this package and summarize the design.');
```

## Main Options

- `withSystemPrompt()`
- `appendSystemPrompt()`
- `withMaxTurns()`
- `withPermissionMode()`
- `continueSession()`
- `resumeSession()`
- `withAdditionalDirs()`

## Permission Modes

Claude Code supports these permission modes:

- `PermissionMode::DefaultMode`
- `PermissionMode::Plan`
- `PermissionMode::AcceptEdits`
- `PermissionMode::BypassPermissions`

## Notes

- Streaming uses the same builder and callback API as the other bridges
- Responses are normalized into `AgentResponse`
- Session IDs from Claude Code are exposed through `sessionId()`
