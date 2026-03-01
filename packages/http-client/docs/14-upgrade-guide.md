---
title: Upgrade Guide (2.0)
description: 'Quick migration map for deprecated aliases and SSE namespace changes.'
---

## Who Should Read This

Use this page when upgrading from 1.x-style APIs.

2.0 keeps deprecated compatibility aliases, but they should be treated as temporary and migrated now.
All rows marked as compatibility are still available in 2.0 and tagged `@deprecated` in source.

## Migration Table

| 1.x / compatibility API | 2.0 API to use | 2.0 status |
|---|---|---|
| `HttpClient::withSSEStream()` | `withMiddleware((new EventSourceMiddleware())->withParser(...))` | Deprecated compatibility method |
| `HttpClientBuilder::using()` | `withPreset()` | Deprecated compatibility method |
| `HttpClientBuilder::withDebugPreset()` | `withHttpDebugPreset()` | Deprecated compatibility method |
| `Cognesy\Http\Middleware\ServerSideEvents\*` | `Cognesy\Http\Middleware\EventSource\*` | Deprecated compatibility namespace |

## 1) `HttpClient::withSSEStream()`

Before:

```php
$client = HttpClient::default()->withSSEStream();
```

After:

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = HttpClient::default()->withMiddleware(
    (new EventSourceMiddleware())->withParser(
        static fn(string $payload): string|bool => $payload
    )
);
```

## 2) `HttpClientBuilder::using()`

Before:

```php
$client = (new HttpClientBuilder())
    ->using('guzzle')
    ->create();
```

After:

```php
$client = (new HttpClientBuilder())
    ->withPreset('guzzle')
    ->create();
```

## 3) `HttpClientBuilder::withDebugPreset()`

Before:

```php
$client = (new HttpClientBuilder())
    ->withDebugPreset('verbose')
    ->create();
```

After:

```php
$client = (new HttpClientBuilder())
    ->withHttpDebugPreset('verbose')
    ->create();
```

## 4) `Middleware\ServerSideEvents\*`

Before:

```php
use Cognesy\Http\Middleware\ServerSideEvents\StreamSSEsMiddleware;

$client = $client->withMiddleware(new StreamSSEsMiddleware());
```

After:

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = $client->withMiddleware(
    (new EventSourceMiddleware())->withParser(
        static fn(string $payload): string|bool => $payload
    )
);
```

Additional import migrations:

| Old class | New class |
|---|---|
| `ServerSideEventStream` | `EventSourceStream` |
| `ServerSideEventResponseDecorator` | `EventSourceResponseDecorator` |
| `StreamSSEsMiddleware` | `EventSourceMiddleware` |
