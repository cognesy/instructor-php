---
title: Record and Replay (Extras)
description: Capture HTTP interactions once and replay them for deterministic tests.
---

## Record Mode

```php
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

$client = $client->withMiddleware(
    new RecordReplayMiddleware(
        mode: RecordReplayMiddleware::MODE_RECORD,
        storageDir: __DIR__ . '/recordings',
    ),
    'record-replay'
);
```

## Replay Mode

```php
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

$client = $client->withMiddleware(
    new RecordReplayMiddleware(
        mode: RecordReplayMiddleware::MODE_REPLAY,
        storageDir: __DIR__ . '/recordings',
        fallbackToRealRequests: false,
    ),
    'record-replay'
);
```

`fallbackToRealRequests: false` makes missing recordings fail fast.

## Pass-Through Mode

```php
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

$client = $client->withMiddleware(
    new RecordReplayMiddleware(
        mode: RecordReplayMiddleware::MODE_PASS,
    )
);
```

Use pass-through when you want to keep middleware wiring but disable recording and replay behavior.

## See Also

- [Middleware](10-middleware.md)
- [Reliability middleware (extras)](12-reliability-middleware.md)
