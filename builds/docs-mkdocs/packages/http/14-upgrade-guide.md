---
title: 'Upgrade Guide (2.0)'
description: 'Quick migration map for remaining deprecated APIs.'
---

## Who Should Read This

Use this page when upgrading from 1.x-style APIs.

2.0 keeps a small compatibility surface. Migrate these paths now.

## Migration Table

| 1.x / compatibility API | 2.0 API to use | 2.0 status |
|---|---|---|
| `HttpClient::withSSEStream()` | `withMiddleware((new EventSourceMiddleware())->withParser(...))` | Deprecated compatibility method |
| `Cognesy\Http\Middleware\ServerSideEvents\*` | `Cognesy\Http\Middleware\EventSource\*` | Deprecated compatibility namespace |

## 1) `HttpClient::withSSEStream()`

Before:

```php
$client = HttpClient::default()->withSSEStream();
// @doctest id="2894"
```

After:

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = HttpClient::default()->withMiddleware(
    (new EventSourceMiddleware())->withParser(
        static fn(string $payload): string|bool => $payload
    )
);
// @doctest id="efe3"
```

## 2) `Middleware\ServerSideEvents\*`

Before:

```php
use Cognesy\Http\Middleware\ServerSideEvents\StreamSSEsMiddleware;

$client = $client->withMiddleware(new StreamSSEsMiddleware());
// @doctest id="dc82"
```

After:

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = $client->withMiddleware(
    (new EventSourceMiddleware())->withParser(
        static fn(string $payload): string|bool => $payload
    )
);
// @doctest id="ccea"
```

Additional import migrations:

| Old class | New class |
|---|---|
| `ServerSideEventStream` | `EventSourceStream` |
| `ServerSideEventResponseDecorator` | `EventSourceResponseDecorator` |
| `StreamSSEsMiddleware` | `EventSourceMiddleware` |
