---
title: Agent Options
description: 'Configure shared and provider-specific builder options.'
---

## Shared Options

Every builder supports:

- `withModel()`
- `withTimeout()`
- `inDirectory()`
- `withSandboxDriver()`
- `onText()`
- `onToolUse()`
- `onComplete()`
- `onError()`

## Claude Code

Claude Code adds:

- `withSystemPrompt()`
- `appendSystemPrompt()`
- `withMaxTurns()`
- `withPermissionMode()`
- `verbose()`
- `continueSession()`
- `resumeSession()`
- `withAdditionalDirs()`

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;

$response = AgentCtrl::claudeCode()
    ->withPermissionMode(PermissionMode::BypassPermissions)
    ->withMaxTurns(10)
    ->execute('Refactor this service.');
```

## Codex

Codex adds:

- `withSandbox()`
- `disableSandbox()`
- `fullAuto()`
- `dangerouslyBypass()`
- `skipGitRepoCheck()`
- `continueSession()`
- `resumeSession()`
- `withAdditionalDirs()`
- `withImages()`

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

$response = AgentCtrl::codex()
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->withImages(['/tmp/mockup.png'])
    ->execute('Review this screenshot and describe the UI.');
```

## OpenCode

OpenCode adds:

- `withAgent()`
- `withFiles()`
- `continueSession()`
- `resumeSession()`
- `shareSession()`
- `withTitle()`

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::openCode()
    ->withAgent('coder')
    ->withTitle('Repository review')
    ->execute('Summarize the architecture.');
```
