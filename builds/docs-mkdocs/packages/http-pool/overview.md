---
title: Overview
description: 'Concurrent HTTP execution for fan-out workloads.'
---

# HTTP Pool

`http-pool` is the concurrent side of the HTTP stack.

Use it when you want to run many HTTP requests together.

Keep using `http-client` when you only need one request.

## What It Does

- runs many requests concurrently
- returns a typed `HttpResponseList`
- works with the same request and response objects as `http-client`

## Main Entry Point

```php
use Cognesy\HttpPool\HttpPool;

$pool = HttpPool::default();
// @doctest id="c5e0"
```

## Core Types

- `HttpPool`
- `PendingHttpPool`
- `CanHandleRequestPool`

## Built In Drivers

- `curl`
- `exthttp`
- `guzzle`
- `symfony`
