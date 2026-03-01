---
title: Getting Started
description: 'Run your first prompt with AgentCtrl.'
---

## Install

```bash
composer require cognesy/agent-ctrl
```

You need at least one CLI binary installed and authenticated (`claude`, `codex`, or `opencode`).

## Minimal Execution

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Summarize this repository.');

echo $response->text();
```

## Runtime Agent Selection

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

$agent = AgentType::from('codex');
$response = AgentCtrl::make($agent)->execute('List top risks in this code.');
```
