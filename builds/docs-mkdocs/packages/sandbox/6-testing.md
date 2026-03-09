---
title: Testing
description: 'Use MockSandbox for deterministic command execution tests.'
---

`MockSandbox` implements the same `CanExecuteCommand` contract.

## Create Mock with Responses

```php
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Sandbox\Testing\MockSandbox;

$sandbox = MockSandbox::withResponses([
    'php -v' => [
        new ExecResult(stdout: 'PHP 8.3', stderr: '', exitCode: 0, duration: 0.01),
    ],
]);
// @doctest id="dfef"
```

## Execute and Assert

```php
$result = $sandbox->execute(['php', '-v']);

echo $result->stdout();
// @doctest id="952e"
```

## Inspect Recorded Commands

```php
$commands = $sandbox->commands();
// @doctest id="d970"
```
