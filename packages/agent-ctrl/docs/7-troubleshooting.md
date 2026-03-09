---
title: Troubleshooting
description: 'Common setup and execution problems.'
---

## CLI Not Found

`agent-ctrl` fails early when the selected CLI binary is not available.

Check that:

- the CLI is installed
- the CLI is authenticated
- the binary is available in `PATH`

## Working Directory Problems

If you call `inDirectory()`, make sure the directory exists and is accessible to the selected sandbox driver.

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::claudeCode()
    ->inDirectory(__DIR__)
    ->execute('List the main files here.');
```

## Non-Zero Exit Codes

The process can finish without throwing an exception and still return a failed response.

Use `isSuccess()` or inspect `exitCode` when you need to decide whether the run completed successfully.

## Streaming Errors

`onError()` only handles streamed agent errors during `executeStreaming()`.

Process failures such as missing binaries, invalid configuration, or execution exceptions are still thrown.

## Parse Failures

If the CLI returns malformed JSON lines, the response keeps a parse failure count and sample payloads:

- `parseFailures()`
- `parseFailureSamples()`
