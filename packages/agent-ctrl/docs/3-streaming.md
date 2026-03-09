---
title: Streaming
description: 'Handle text, tool activity, completion, and streamed errors in real time.'
---

Use `executeStreaming()` when you want updates while the agent is running.

## Streaming Callbacks

- `onText()` receives incremental text output
- `onToolUse()` receives normalized tool calls
- `onComplete()` receives the final `AgentResponse`
- `onError()` receives streamed agent errors

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::openCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("[{$tool}]"))
    ->onComplete(fn(AgentResponse $response) => print("\nDone\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->executeStreaming('Explain this package.');
```

`executeStreaming()` still returns the final `AgentResponse`, so you can stream output and inspect the result afterward.

## When to Use `execute()`

Use `execute()` when you only care about the final response and do not need incremental updates.
