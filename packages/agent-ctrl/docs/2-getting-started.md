---
title: Getting Started
description: 'Send a prompt to any supported code agent.'
---

## Install

```bash
composer require cognesy/agent-ctrl
```

You also need at least one authenticated CLI installed in `PATH`:

- `claude`
- `codex`
- `opencode`

## First Request

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Summarize this repository.');

echo $response->text();
```

## Choose a Different Agent

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::claudeCode()->execute('List the main packages in this monorepo.');
```

## Select the Agent at Runtime

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

$agent = AgentType::from('opencode');

$response = AgentCtrl::make($agent)->execute('Explain the package layout.');
```

## Common Configuration

All builders support the same core methods:

- `withModel()`
- `withTimeout()`
- `inDirectory()`
- `withSandboxDriver()`

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()
    ->withTimeout(300)
    ->inDirectory(__DIR__)
    ->execute('Review the current directory.');
```
