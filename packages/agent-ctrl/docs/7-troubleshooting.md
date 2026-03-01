---
title: Troubleshooting
description: 'Common setup and runtime issues.'
---

## Missing CLI Binary

If `claude`, `codex`, or `opencode` is missing from `PATH`, execution fails early with a runtime error.

Fix:

- install the CLI
- authenticate it
- verify it is available in `PATH`

## Invalid Working Directory

When using `inDirectory('/path')`, the directory must exist.

```php
$response = AgentCtrl::claudeCode()
    ->inDirectory('/existing/path')
    ->execute('List top 3 refactors.');
```

## Stream Parse Failures

Malformed streaming JSON is fail-fast by default.

Use response helpers to inspect tolerant-mode parsing (advanced bridge usage):

- `parseFailures()`
- `parseFailureSamples()`
