---
title: Streaming
description: 'Handle text, tool, completion, and error events in real time.'
---

Use `executeStreaming()` when you want incremental output.

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::openCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n[tool:$tool]\n"))
    ->onComplete(fn(AgentResponse $response) => print("\n[done]\n"))
    ->onError(fn(string $message, ?string $code) => print("\n[error:$message]\n"))
    ->executeStreaming('Explain this package architecture.');
```

`execute()` is a non-streaming convenience method.
