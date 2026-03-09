---
title: 'Execution Policy'
description: 'Configure timeout, memory, paths, environment, network, and output limits.'
---

`ExecutionPolicy` is immutable. Every `with*()` method returns a new instance.

## Minimal Policy

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;

$policy = ExecutionPolicy::in(__DIR__);
// @doctest id="2c2b"
```

## Common Overrides

```php
$policy = $policy
    ->withTimeout(30)
    ->withIdleTimeout(10)
    ->withMemory('256M')
    ->withNetwork(false)
    ->withOutputCaps(2 * 1024 * 1024, 512 * 1024)
    ->withReadablePaths('/data/shared')
    ->withWritablePaths('/tmp/work')
    ->withEnv(['APP_ENV' => 'test'], inherit: true);
// @doctest id="7971"
```

## Key Notes

- Default timeout is `5` seconds.
- Default memory limit normalizes to `128M`.
- Network is disabled by default.
- Output caps are enforced for stdout and stderr.
