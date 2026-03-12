---
title: 'Request Pooling'
description: 'Send multiple requests concurrently using the dedicated http-pool package.'
---

Concurrent request execution has been moved to its own dedicated package at `packages/http-pool`. This separation keeps the core HTTP client focused on single-request transport while giving the pooling layer its own lifecycle and dependencies.

## Where to Find Pooling

The pooling API is provided by the `http-pool` package. The key types are:

| Type | Namespace | Role |
|------|-----------|------|
| `HttpPool` | `Cognesy\HttpPool` | Executes a batch of requests concurrently |
| `PendingHttpPool` | `Cognesy\HttpPool` | Deferred pool execution wrapper |
| `HttpPoolBuilder` | `Cognesy\HttpPool\Creation` | Builder for constructing pool instances |

Refer to `packages/http-pool/docs/` for full documentation on configuring concurrency limits, handling per-request errors, and collecting results.

## Collection Types

The request and response collection types remain in the `http-client` package since they are useful beyond pooling:

### HttpRequestList

A typed, immutable collection of `HttpRequest` objects:

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$requests = HttpRequestList::of(
    new HttpRequest(url: 'https://api.example.com/a', method: 'GET', headers: [], body: '', options: []),
    new HttpRequest(url: 'https://api.example.com/b', method: 'GET', headers: [], body: '', options: []),
);

// Access methods
$requests->count();    // 2
$requests->first();    // First HttpRequest
$requests->last();     // Last HttpRequest
$requests->isEmpty();  // false
$requests->all();      // Array of all requests

// Immutable mutation
$requests = $requests->withAppended($newRequest);
$requests = $requests->filter(fn($r) => $r->method() === 'POST');
// @doctest id="8744"
```

### HttpResponseList

A typed, immutable collection of `Result` objects, where each result wraps either a successful `HttpResponse` or an error:

```php
use Cognesy\Http\Collections\HttpResponseList;

// After pool execution, you get an HttpResponseList
$responses->count();          // Total results
$responses->successful();     // Array of HttpResponse objects
$responses->failed();         // Array of error values
$responses->hasFailures();    // true if any request failed
$responses->successCount();   // Number of successful responses
$responses->failureCount();   // Number of failed responses
// @doctest id="d4aa"
```

This design lets you handle partial failures gracefully -- some requests in a batch may succeed while others fail, and you can inspect each result independently.
