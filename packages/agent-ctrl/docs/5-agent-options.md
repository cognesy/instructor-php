---
title: Agent Options
description: 'Agent-specific builder methods for Claude Code, Codex, and OpenCode.'
---

All builders share:

- `withModel()`
- `withTimeout()`
- `inDirectory()`
- `withSandboxDriver()`

## Claude Code

Extra options:

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
    ->execute('Refactor this service to remove duplication.');
```

## Codex

Extra options:

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
    ->fullAuto()
    ->execute('Write tests for this controller.');
```

## OpenCode

Extra options:

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
    ->withTitle('Repository cleanup')
    ->execute('Find dead code and propose removals.');
```
