---
title: OpenCode
description: 'Use the OpenCode bridge for flexible model selection and session sharing.'
---

Use `AgentCtrl::openCode()` when you want OpenCode behind the shared `agent-ctrl` API.

## Typical Use

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->execute('Explain the architecture in short paragraphs.');
```

## Main Options

- `withAgent()`
- `withFiles()`
- `continueSession()`
- `resumeSession()`
- `shareSession()`
- `withTitle()`

## Notes

- OpenCode supports provider-style model IDs such as `anthropic/claude-sonnet-4-5`
- Responses can include both usage and cost data
- Streamed text and tool events use the same callback API as the other bridges
