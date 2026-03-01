---
title: Getting Started
description: 'Run your first command in a sandbox.'
---

## 1. Create Policy

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;

$policy = ExecutionPolicy::in(__DIR__);
```

## 2. Create Sandbox

```php
use Cognesy\Sandbox\Sandbox;

$sandbox = Sandbox::host($policy);
```

## 3. Execute Command

```php
$result = $sandbox->execute(['php', '-v']);

echo $result->stdout();
echo $result->exitCode();
```

For enum-based driver selection:

```php
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;

$sandbox = Sandbox::with($policy)->using(SandboxDriver::Host);
```
