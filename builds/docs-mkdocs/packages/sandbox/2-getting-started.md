---
title: 'Getting Started'
description: 'Run your first command in a sandbox.'
---

## 1. Create Policy

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;

$policy = ExecutionPolicy::in(__DIR__);
// @doctest id="b7a5"
```

## 2. Create Sandbox

```php
use Cognesy\Sandbox\Sandbox;

$sandbox = Sandbox::host($policy);
// @doctest id="8380"
```

## 3. Execute Command

```php
$result = $sandbox->execute(['php', '-v']);

echo $result->stdout();
echo $result->exitCode();
// @doctest id="9c4c"
```

For enum-based driver selection:

```php
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;

$sandbox = Sandbox::with($policy)->using(SandboxDriver::Host);
// @doctest id="0fd4"
```
