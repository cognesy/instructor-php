---
title: Events
description: 'Observe requests and streaming updates.'
---

Attach listeners on `StructuredOutputRuntime` when you want visibility into execution.

```php
$runtime = StructuredOutputRuntime::fromConfig($config)
    ->wiretap(function (object $event): void {
        // inspect event stream
    });
// @doctest id="b950"
```

The package emits request, response, extraction, validation, and streaming-related events. Use this for logging and debugging, not for ordinary request construction.
